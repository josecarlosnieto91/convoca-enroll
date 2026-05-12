<?php
/**
 * Webhook Dispatcher management.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class Webhook_Dispatcher
{
    private const TABLE_NAME = 'bde_webhook_queue';

    public function __construct()
    {
        // Add action for processing the queue via Cron.
        add_action('biodevas_enroll_process_webhook_queue', [$this, 'process_queue']);

        // Listeners for state changes.
        add_action('biodevas_enroll_inscripcion_nueva', [$this, 'on_inscripcion_state_change'], 10, 3);
        add_action('biodevas_enroll_inscripcion_cancelada', [$this, 'on_inscripcion_state_change'], 10, 2);
        add_action('biodevas_enroll_inscripcion_confirmada', [$this, 'on_inscripcion_state_change'], 10, 2);
        add_action('biodevas_enroll_inscripcion_promovida', [$this, 'on_inscripcion_state_change'], 10, 2);
        add_action('biodevas_enroll_asistencia_cambiada', [$this, 'on_asistencia_cambiada'], 10, 2);
    }

    public static function get_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    public static function create_table(): void
    {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            payload longtext NOT NULL,
            event_type varchar(50) NOT NULL,
            inscripcion_id bigint(20) DEFAULT NULL,
            status enum('pending','sent','failed','sending') DEFAULT 'pending',
            retries tinyint(4) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Enqueue a webhook payload to be sent.
     * Prefers Webhook_Manager (Common) if available, falls back to legacy queue.
     */
    public static function enqueue(string $event_type, array $payload, ?int $inscripcion_id = null): int|bool
    {
        // Try to use Webhook_Manager from Common if available
        if (class_exists('Convoca\Core\Webhook_Manager')) {
            $settings = get_option('bde_settings', []);
            $webhook_url = $settings['webhook_url'] ?? '';

            if (!empty($webhook_url) && filter_var($webhook_url, FILTER_VALIDATE_URL)) {
                try {
                    $wm = new \Convoca\Core\Webhook_Manager();

                    // Add webhook on-the-fly if not registered
                    $existing = $wm::get_webhooks();
                    $found = false;
                    foreach ($existing as $wh) {
                        if ($wh['url'] === $webhook_url) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $wm::add_webhook([
                            'url' => $webhook_url,
                            'events' => ["enroll_$event_type"],
                            'label' => 'Enroll Dispatcher',
                        ]);
                    }

                    $wm->dispatch("enroll_$event_type", $payload);
                    \Convoca\Core\Logger::info(
                        "Webhook dispatched via Webhook_Manager: $event_type",
                        'Enroll/Webhook',
                        $inscripcion_id
                    );
                    return true;
                } catch (\Throwable $e) {
                    error_log('BD VWebhook_Dispatcher error: ' . $e->getMessage());
                }
            }
        }

        // Fallback to legacy queue
        return self::enqueue_legacy($event_type, $payload, $inscripcion_id);
    }

    /**
     * Legacy queue implementation (fallback when Webhook_Manager unavailable).
     */
    private static function enqueue_legacy(string $event_type, array $payload, ?int $inscripcion_id = null): bool
    {
        $settings = get_option('bde_settings', []);
        $webhook_url = $settings['webhook_url'] ?? '';

        if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            return false;
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $inserted = $wpdb->insert(
            $table_name,
            [
                'url'            => $webhook_url,
                'payload'        => wp_json_encode($payload),
                'event_type'     => $event_type,
                'inscripcion_id' => $inscripcion_id,
                'status'         => 'pending',
                'created_at'     => current_time('mysql'),
            ]
        );

        if ($inserted) {
            \Convoca\Core\Logger::log('Webhook encolado (legacy) para evento: ' . $event_type, 'info', 'Enroll/Webhook', (int) $inscripcion_id);
            return $wpdb->insert_id;
        }

        return false;
    }

    public function on_inscripcion_state_change($inscripcion_id, $actividad_id, $estado = null): void
    {
        $this->queue_webhook_for_inscripcion('state_change', $inscripcion_id, $actividad_id);
    }

    public function on_asistencia_cambiada($inscripcion_id, $asistencia): void
    {
        $actividad_id = (int) CPT_Inscripcion::get_meta($inscripcion_id, 'actividad_id');
        $this->queue_webhook_for_inscripcion('attendance_change', $inscripcion_id, $actividad_id);
    }

    private function queue_webhook_for_inscripcion(string $event_type, int $inscripcion_id, int $actividad_id): void
    {
        $post = get_post($inscripcion_id);
        if (!$post) return;

        $payload = [
            'event'          => $event_type,
            'inscripcion_id' => $inscripcion_id,
            'actividad_id'   => $actividad_id,
            'actividad_name' => get_the_title($actividad_id),
            'email'          => CPT_Inscripcion::get_meta($inscripcion_id, 'email'),
            'nombre'         => CPT_Inscripcion::get_meta($inscripcion_id, 'nombre'),
            'estado'         => CPT_Inscripcion::get_meta($inscripcion_id, 'estado'),
            'asistencia'     => CPT_Inscripcion::get_meta($inscripcion_id, 'asistencia'),
            'es_socio'       => CPT_Inscripcion::get_meta($inscripcion_id, 'es_socio'),
            'fecha'          => current_time('mysql'),
        ];

        self::enqueue($event_type, $payload, $inscripcion_id);
    }

    public function process_queue(): void
    {
        // 0. Acquire lock to prevent concurrent runs
        if (!\Convoca\Core\Utils::acquire_lock('bde_webhook_queue_lock', 300)) {
            return;
        }

        try {
            global $wpdb;
            $table_name = self::get_table_name();
            
            $settings = get_option('bde_settings', []);
            $secret = $settings['webhook_secret'] ?? '';
            
            $batch_size = 20;
            $max_retries = 3;

            // Expire old pending
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET status = 'failed' WHERE status = 'pending' AND created_at < %s",
                wp_date('Y-m-d H:i:s', strtotime('-7 days'))
            ));

            // 2. Select pending webhooks and mark as sending (atomic under the outer lock)
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $table_name 
                WHERE status = 'pending' AND retries < %d 
                ORDER BY retries ASC, created_at ASC 
                LIMIT %d",
                $max_retries,
                $batch_size
            ));

            if (empty($ids)) {
                return;
            }

            // 3. Mark them as 'sending'
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET status = 'sending' 
                 WHERE id IN ($placeholders)",
                $ids
            ));

            // 4. Retrieve locked rows
            $queue = $wpdb->get_results("SELECT * FROM $table_name WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");

            if (empty($queue)) {
                return;
            }

            foreach ($queue as $item) {
                $args = [
                    'body'    => $item->payload,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'timeout' => 15,
                ];

                if ($secret) {
                    $args['headers']['X-Assoc-Signature'] = hash_hmac('sha256', $item->payload, $secret);
                }

                $response = wp_remote_post($item->url, $args);

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300) {
                    $wpdb->update(
                        $table_name,
                        ['status' => 'sent', 'sent_at' => current_time('mysql')],
                        ['id' => $item->id]
                    );
                    \Convoca\Core\Logger::log('Webhook enviado: ' . $item->event_type . ' (ID: ' . $item->id . ')', 'info', 'Enroll/Webhook', (int) $item->inscripcion_id);
                } else {
                    $new_retries = $item->retries + 1;
                    $status = ($new_retries >= $max_retries) ? 'failed' : 'pending';
                    
                    $wpdb->update(
                        $table_name,
                        ['retries' => $new_retries, 'status' => $status],
                        ['id' => $item->id]
                    );
                    
                    $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
                    
                    $log_level = ($status === 'failed') ? 'error' : 'warning';
                    \Convoca\Core\Logger::log('Fallo envío webhook (Intento ' . $new_retries . '/' . $max_retries . '): ' . $error_msg, $log_level, 'Enroll/Webhook', (int) $item->inscripcion_id);
                }
            }
        } finally {
            \Convoca\Core\Utils::release_lock('bde_webhook_queue_lock');
        }
    }
}
