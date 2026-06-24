<?php
/**
 * Google Photos integration for activity albums.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class Google_Photos
{
    private const OPTION = 'convoca_enroll_settings';

    private $client  = null;
    private $service = null;

    public function __construct()
    {
        $this->init_client();
    }

    private function init_client(): void
    {
        $settings      = get_option(self::OPTION, []);
        $client_id     = $settings['google_photos_client_id'] ?? '';
        $client_secret = $settings['google_photos_client_secret'] ?? '';
        $refresh_token = $settings['google_photos_refresh_token'] ?? '';

        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            return;
        }

        $this->client = new \Google\Client();
        $this->client->setClientId($client_id);
        $this->client->setClientSecret($client_secret);
        $this->client->refreshToken($refresh_token);

        if ($this->client->getAccessToken()) {
            $this->service = new \Google\Service\PhotosLibrary($this->client);
        }
    }

    public function is_configured(): bool
    {
        return $this->service !== null;
    }

    public function get_auth_url(): string
    {
        $settings  = get_option(self::OPTION, []);
        $client_id = $settings['google_photos_client_id'] ?? '';

        if (empty($client_id)) {
            return '';
        }

        $this->client = new \Google\Client();
        $this->client->setClientId($client_id);
        $this->client->setRedirectUri(admin_url('admin.php?page=conv-ajustes&tab=google_photos'));
        $this->client->addScope([
            'https://www.googleapis.com/auth/photoslibrary.appendonly',
            'https://www.googleapis.com/auth/photoslibrary.sharing',
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        $state = wp_generate_password(32, false);
        set_transient('convoca_enroll_oauth_state_' . get_current_user_id(), $state, HOUR_IN_SECONDS);
        $this->client->setState($state);

        return $this->client->createAuthUrl();
    }

    public function handle_oauth_callback(string $code, string $received_state = ''): bool
    {
        $settings      = get_option(self::OPTION, []);
        $client_id     = $settings['google_photos_client_id'] ?? '';
        $client_secret = $settings['google_photos_client_secret'] ?? '';

        if (empty($client_id) || empty($client_secret)) {
            return false;
        }

        $expected_state = get_transient('convoca_enroll_oauth_state_' . get_current_user_id());
        delete_transient('convoca_enroll_oauth_state_' . get_current_user_id());

        if (empty($expected_state) || !hash_equals($expected_state, $received_state)) {
            error_log('[Google Photos] OAuth State mismatch or expired.');
            return false;
        }

        $client = new \Google\Client();
        $client->setClientId($client_id);
        $client->setClientSecret($client_secret);
        $client->setRedirectUri(admin_url('admin.php?page=conv-ajustes&tab=google_photos'));

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['refresh_token'])) {
            $settings['google_photos_refresh_token'] = $token['refresh_token'];
            update_option(self::OPTION, $settings);
            return true;
        }

        return false;
    }

    public function create_album(int $actividad_id): ?array
    {
        if (!$this->is_configured()) {
            return null;
        }

        $album_id = get_post_meta($actividad_id, '_conv_google_album_id', true);
        if (!empty($album_id)) {
            return [
                'id'  => $album_id,
                'url' => get_post_meta($actividad_id, '_conv_google_album_url', true),
            ];
        }

        $actividad = get_post($actividad_id);
        $settings  = get_option(self::OPTION, []);
        $prefix    = $settings['google_photos_album_prefix'] ?? get_bloginfo('name') . ' - ';
        
        $fecha           = get_post_meta($actividad_id, '_conv_fecha_inicio', true);
        $fecha_formatted = $fecha ? wp_date('d/m/Y', strtotime($fecha)) : '';
        
        $album_title = $prefix . $actividad->post_title . ($fecha_formatted ? ' - ' . $fecha_formatted : '');

        $album = new \Google\Service\PhotosLibrary\Album();
        $album->setTitle($album_title);

        try {
            $created = $this->service->albums->create($album);
            
            update_post_meta($actividad_id, '_conv_google_album_id', $created->getId());
            update_post_meta($actividad_id, '_conv_google_album_created_at', current_time('mysql'));

            return [
                'id'  => $created->getId(),
                'url' => $created->getProductUrl(),
            ];
        } catch (\Exception $e) {
            error_log('[Google Photos] Error creating album: ' . $e->getMessage());
            return null;
        }
    }

    public function share_album(int $actividad_id): ?string
    {
        if (!$this->is_configured()) {
            return null;
        }

        $album_id = get_post_meta($actividad_id, '_conv_google_album_id', true);
        if (empty($album_id)) {
            return null;
        }

        $already_shared = get_post_meta($actividad_id, '_conv_google_album_shared', true);
        if ($already_shared) {
            return get_post_meta($actividad_id, '_conv_google_album_url', true);
        }

        try {
            $share_request  = new \Google\Service\PhotosLibrary\ShareAlbumRequest();
            $share_response = $this->service->albums->share($album_id, $share_request);
            
            $shareable_url = $share_response->getShareInfo()->getShareableUrl();
            
            update_post_meta($actividad_id, '_conv_google_album_url', $shareable_url);
            update_post_meta($actividad_id, '_conv_google_album_shared', '1');

            return $shareable_url;
        } catch (\Exception $e) {
            error_log('[Google Photos] Error sharing album: ' . $e->getMessage());
            return null;
        }
    }

    public function get_participants_emails(int $actividad_id): array
    {
        $inscriptions = get_posts([
            'post_type'      => 'inscripcion',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_conv_actividad_id', 'value' => $actividad_id],
                ['key' => '_conv_estado', 'value' => 'confirmada'],
            ],
        ]);

        $emails = [];
        foreach ($inscriptions as $insc) {
            $email = get_post_meta($insc->ID, '_conv_email', true);
            if ($email && !in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    public function get_coordinator_email(int $actividad_id): ?string
    {
        $responsables = get_post_meta($actividad_id, '_conv_responsables', true);
        if (empty($responsables)) {
            return null;
        }

        $ids      = array_map('trim', explode(',', $responsables));
        $first_id = isset($ids[0]) ? absint($ids[0]) : 0;

        if ($first_id > 0) {
            $user = get_userdata($first_id);
            return $user ? $user->user_email : null;
        }

        return null;
    }

    public function notify_coordinator(int $actividad_id): bool
    {
        $album_url = $this->share_album($actividad_id);
        if (!$album_url) {
            $album_url = get_post_meta($actividad_id, '_conv_google_album_url', true);
        }

        $coordinator_email = $this->get_coordinator_email($actividad_id);
        if (empty($coordinator_email)) {
            return false;
        }

        $actividad = get_post($actividad_id);
        $templates = Email_Automation::get_templates();
        $tpl       = $templates['google_photos_album_creado'] ?? [
            'subject' => '[' . get_bloginfo('name') . '] ' . __('Álbum de fotos', 'convoca-enroll') . ' "{actividad}"',
            'body'    => "Hola,\n\nSe ha creado un álbum de Google Photos para la actividad \"{actividad}\".\n\nPuedes acceder y subir fotos aquí:\n{album_url}\n\nUna vez el evento haya terminado, puedes compartir el álbum con los participantes desde este mismo panel.\n\n— Equipo Convoca",
        ];

        $vars = [
            '{actividad}' => $actividad->post_title,
            '{album_url}' => $album_url ?: 'No disponible',
        ];

        $subject = str_replace(array_keys($vars), array_values($vars), $tpl['subject']);
        $body    = str_replace(array_keys($vars), array_values($vars), $tpl['body']);

        $email_automation = new Email_Automation();
        $html_body        = $email_automation->get_html_layout($body, $subject);

        Email_Queue::enqueue([
            'to'      => $coordinator_email,
            'subject' => $subject,
            'body'    => $html_body,
            'headers' => ['Content-Type: text/html; charset=UTF-8'],
        ]);

        return true;
    }

    public function notify_participants(int $actividad_id): int
    {
        // 1. Concurrency lock to avoid spam if cron runs multiple times or manually
        $lock_key = "conv_gp_notify_lock_{$actividad_id}";
        if (get_transient($lock_key)) {
            return 0;
        }
        set_transient($lock_key, '1', HOUR_IN_SECONDS);

        try {
            $album_url = $this->share_album($actividad_id);
            if (empty($album_url)) {
                delete_transient($lock_key);
                return 0;
            }

            // Ensure meta is updated BEFORE the loop to avoid duplicates if script times out.
            update_post_meta($actividad_id, '_conv_google_album_shared', '1');

            $emails = $this->get_participants_emails($actividad_id);
            if (empty($emails)) {
                delete_transient($lock_key);
                return 0;
            }

            $actividad = get_post($actividad_id);
            $templates = Email_Automation::get_templates();
            $tpl       = $templates['google_photos_album_compartido'] ?? [
                'subject' => '[' . get_bloginfo('name') . '] ' . __('Fotos de la actividad', 'convoca-enroll') . ' "{actividad}"',
                'body'    => "Hola,\n\n¡Ya puedes ver las fotos de la actividad \"{actividad}\"!\n\nHemos subido un álbum con los mejores momentos. Puedes verlo aquí:\n{album_url}\n\n¡Gracias por participar en nuestras actividades!\n\n— Equipo Convoca",
            ];

            $vars = [
                '{actividad}' => $actividad->post_title,
                '{album_url}' => $album_url,
            ];

            $subject = str_replace(array_keys($vars), array_values($vars), $tpl['subject']);
            $body    = str_replace(array_keys($vars), array_values($vars), $tpl['body']);
            
            $email_automation = new Email_Automation();
            $html_body        = $email_automation->get_html_layout($body, $subject);

            $sent = 0;
            foreach ($emails as $email) {
                Email_Queue::enqueue([
                    'to'      => $email,
                    'subject' => $subject,
                    'body'    => $html_body,
                    'headers' => ['Content-Type: text/html; charset=UTF-8'],
                ]);
                ++$sent;
            }

            delete_transient($lock_key);
            return $sent;
        } catch (\Throwable $e) {
            delete_transient($lock_key);
            \Convoca\Core\Logger::error("Error notificando participantes Google Photos: " . $e->getMessage(), 'Enroll/GP');
            return 0;
        }
    }

    public static function cron_share_albums(): void
    {
        $instance = new self();
        
        if (!$instance->is_configured()) {
            return;
        }

        $yesterday = gmdate('Y-m-d\T00:00', strtotime('-1 day'));
        $today     = gmdate('Y-m-d\T23:59', strtotime('-1 day'));

        $activities = get_posts([
            'post_type'      => 'actividad',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_conv_fecha_fin',
                    'value'   => [$yesterday, $today],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME',
                ],
                [
                    'key'     => '_conv_google_album_id',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => '_conv_google_album_shared',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        foreach ($activities as $activity) {
            $create_option = get_post_meta($activity->ID, '_conv_google_create_album', true);
            
            if ($create_option !== '0') {
                $instance->notify_participants($activity->ID);
            }
        }
    }
}