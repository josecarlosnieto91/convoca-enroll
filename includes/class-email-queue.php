<?php
/**
 * Email Queue management.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class Email_Queue
{
    private const TABLE_NAME = 'bde_email_queue';

    public function __construct()
    {
        add_action('biodevas_enroll_process_email_queue', [$this, 'process_queue']);
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

        // Using user suggested schema
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            body_html longtext NOT NULL,
            body_text longtext DEFAULT NULL,
            attachments text DEFAULT NULL,
            inscripcion_id bigint(20) DEFAULT NULL,
            status enum('pending','sent','failed','sending') DEFAULT 'pending',
            retries tinyint(4) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            next_retry_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY next_retry_at (next_retry_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function enqueue(array $data): int|bool
    {
        global $wpdb;
        $table_name = self::get_table_name();

        $inserted = $wpdb->insert(
            $table_name,
            [
                'recipient'      => $data['to'],
                'subject'        => $data['subject'],
                'body_html'      => $data['body'],
                'body_text'      => $data['body_text'] ?? wp_strip_all_tags($data['body']),
                'attachments'    => wp_json_encode($data['attachments'] ?? []),
                'inscripcion_id' => $data['inscripcion_id'] ?? null,
                'status'         => 'pending',
                'created_at'     => current_time('mysql'),
                'next_retry_at'  => current_time('mysql'),
            ]
        );

        if ($inserted) {
            \Convoca\Core\Logger::log('Email encolado para ' . $data['to'] . ' (' . $data['subject'] . ')', 'info', 'Enroll/Email', (int) ($data['inscripcion_id'] ?? 0));
            return $wpdb->insert_id;
        }

        return false;
    }

    public function process_queue(): void
    {
        $start_time = microtime(true);
        $max_exec = (int) @ini_get('max_execution_time');
        if ($max_exec <= 0) $max_exec = 30;
        $limit = $max_exec - 5; // 5 second margin

        // 0. Acquire lock to prevent concurrent runs
        if (!\Convoca\Core\Utils::acquire_lock('bde_email_queue_lock', 300)) {
            return;
        }

        try {
            global $wpdb;
            $table_name = self::get_table_name();
            
            $settings = get_option('bde_settings', []);
            $batch_size = (int) ($settings['email_batch_size'] ?? 20);
            $max_retries = (int) ($settings['email_max_retries'] ?? 3);
            $hourly_limit = (int) ($settings['email_hourly_limit'] ?? 100);

            // Rate limiting check
            $hour_key = 'bde_email_hourly_count_' . gmdate('YmdH');
            $current_count = (int) get_transient($hour_key);
            if ($current_count >= $hourly_limit) {
                 \Convoca\Core\Logger::log('Límite horario de envío de emails alcanzado (' . $hourly_limit . '). Suspendiendo procesamiento.', 'warning', 'Enroll/Email');
                 return;
            }
            
            // Adjust batch size if near limit
            $remaining = $hourly_limit - $current_count;
            if ($batch_size > $remaining) {
                $batch_size = $remaining;
            }

            // 1. Mark expired emails as failed (older than 7 days)
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET status = 'failed' WHERE status = 'pending' AND created_at < %s",
                wp_date('Y-m-d H:i:s', strtotime('-7 days'))
            ));

            // 1b. Reset stuck 'sending' emails back to pending (older than 30 minutes)
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET status = 'pending', next_retry_at = NULL WHERE status = 'sending' AND created_at < %s",
                wp_date('Y-m-d H:i:s', strtotime('-30 minutes'))
            ));

            // 2. Select pending emails and mark as sending (atomic under the outer lock)
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $table_name 
                WHERE status = 'pending' AND retries < %d 
                AND (next_retry_at IS NULL OR next_retry_at <= %s)
                ORDER BY retries ASC, created_at ASC 
                LIMIT %d",
                $max_retries,
                current_time('mysql'),
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
            $emails = $wpdb->get_results("SELECT * FROM $table_name WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");

            if (empty($emails)) {
                return;
            }

            $processed_ids = [];
            $heartbeat_interval = 30; // Extend lock every 30 seconds
            $last_heartbeat = $start_time;

            foreach ($emails as $email) {
                // Heartbeat: extend lock while processing
                if (microtime(true) - $last_heartbeat > $heartbeat_interval) {
                    \Convoca\Core\Utils::release_lock('bde_email_queue_lock');
                    \Convoca\Core\Utils::acquire_lock('bde_email_queue_lock', 300);
                    $last_heartbeat = microtime(true);
                }

                // Check time limit
                if (microtime(true) - $start_time > $limit) {
                    \Convoca\Core\Logger::log('Procesamiento de cola de emails interrumpido por tiempo límite.', 'warning', 'Enroll/Email');
                    break;
                }

                $headers = ['Content-Type: text/html; charset=UTF-8'];
                $attachments = json_decode($email->attachments, true) ?? [];

                $sent = wp_mail($email->recipient, $email->subject, $email->body_html, $headers, $attachments);

                if ($sent) {
                    $wpdb->update(
                        $table_name,
                        ['status' => 'sent', 'sent_at' => current_time('mysql')],
                        ['id' => $email->id]
                    );

                    // Update rate limit counter atomically
                    $option_name = '_transient_' . $hour_key;
                    $affected = $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s",
                        $option_name
                    ));
                    if ($affected === 0) {
                        set_transient($hour_key, 1, HOUR_IN_SECONDS);
                    }

                    \Convoca\Core\Logger::log('Email enviado: ' . $email->recipient . ' (ID: ' . $email->id . ')', 'info', 'Enroll/Email', (int) $email->inscripcion_id);
                } else {
                    $new_retries = $email->retries + 1;
                    $status = ($new_retries >= $max_retries) ? 'failed' : 'pending';
                    
                    // Exponential backoff: 1 min, 5 min, 15 min, 30 min, 1 hour...
                    $delays = [1 => 60, 2 => 300, 3 => 900, 4 => 1800, 5 => 3600];
                    $delay = $delays[$new_retries] ?? (pow(2, $new_retries) * 60);
                    $next_retry = gmdate('Y-m-d H:i:s', time() + $delay);

                    $wpdb->update(
                        $table_name,
                        [
                            'retries'       => $new_retries, 
                            'status'        => $status,
                            'next_retry_at' => ($status === 'pending') ? $next_retry : null
                        ],
                        ['id' => $email->id]
                    );
                    
                    $log_level = ($status === 'failed') ? 'error' : 'warning';
                    \Convoca\Core\Logger::log('Fallo envío email (Intento ' . $new_retries . '/' . $max_retries . '): ' . $email->recipient, $log_level, 'Enroll/Email', (int) $email->inscripcion_id);
                }
                $processed_ids[] = $email->id;
            }

            // 5. Cleanup: revert any emails still in 'sending' status (unprocessed due to timeout)
            $remaining_ids = array_diff($ids, $processed_ids);
            if (!empty($remaining_ids)) {
                $rem_placeholders = implode(',', array_fill(0, count($remaining_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET status = 'pending' WHERE id IN ($rem_placeholders) AND status = 'sending'",
                    $remaining_ids
                ));
            }

        } finally {
            // Always release the lock using the proper mechanism
            \Convoca\Core\Utils::release_lock('bde_email_queue_lock');
        }
    }
}
