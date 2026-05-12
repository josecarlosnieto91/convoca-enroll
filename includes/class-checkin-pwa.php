<?php
/**
 * PWA support for the QR Check-in page.
 * Handles manifest.json and service worker registration.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if (!defined('ABSPATH')) {
    exit;
}

class Checkin_PWA
{
    /**
     * Initialize PWA hooks.
     */
    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_rewrites']);
        // Priority 1: run before the Checkin_Handler (which fires at 10)
        add_action('template_redirect', [__CLASS__, 'serve_manifest'], 1);
        add_action('init', [__CLASS__, 'serve_sw'], 0); // High priority, before everything
        add_action('wp_head', [__CLASS__, 'add_meta_tags']);

        // Register rest route for online check-in queue flush
        add_action('rest_api_init', function () {
            register_rest_route('biodevas-enroll/v1', '/checkin/flush', [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'rest_flush_queue'],
                'permission_callback' => function () {
                    return current_user_can('manage_inscripciones') || in_array('voluntario_aprobado', (array) wp_get_current_user()->roles, true);
                },
            ]);
        });
    }

    /**
     * Register rewrite rules for PWA assets (not needed — served via template_redirect + URI check).
     * Kept as no-op for backward compatibility.
     */
    public static function register_rewrites(): void
    {
        // Assets are served via template_redirect by matching $_SERVER["REQUEST_URI"].
        // No rewrite rules needed.
    }

    /**
     * Serve manifest.json.
     */
    public static function serve_manifest(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/checkin/manifest.json') === false) {
            return;
        }

        $logo_url = \Convoca\Core\Utils::get_logo_url('checkin');

        header('Content-Type: application/json');
        echo json_encode([
            'name'             => __('Check-in Biodevas', 'convoca-enroll'),
            'short_name'       => __('Check-in', 'convoca-enroll'),
            'description'      => __('Escáner QR de asistencia para monitores de Biodevas', 'convoca-enroll'),
            'start_url'        => home_url('/checkin/'),
            'scope'            => '/checkin/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#0f172a',
            'theme_color'      => '#0f172a',
            'categories'       => ['utilities', 'productivity'],
            'icons'            => self::get_icons($logo_url),
            'shortcuts' => [
                [
                    'name' => __('Escáner QR', 'convoca-enroll'),
                    'short_name' => __('Escanear', 'convoca-enroll'),
                    'url' => '/checkin/',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Serve the service worker script.
     */
    public static function serve_sw(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $query = parse_url($uri, PHP_URL_QUERY) ?? '';
        if (strpos($query, 'sw=1') === false) {
            return;
        }

        $sw_path = BDE_DIR . 'public/assets/pwa/sw.js';
        if (file_exists($sw_path)) {
            header('Content-Type: application/javascript');
            header('Cache-Control: no-cache');
            header('Service-Worker-Allowed: /checkin/');
            readfile($sw_path);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo '// Service worker not found';
        }
        exit;
    }

    /**
     * Add PWA meta tags to the scanner page.
     */
    public static function add_meta_tags(): void
    {
        if (!get_query_var('bde_checkin_page')) {
            return;
        }

        $logo_url = \Convoca\Core\Utils::get_logo_url('checkin');
        ?>
        <link rel="manifest" href="<?php echo esc_url(home_url('/checkin/manifest.json')); ?>">
        <?php /* SW served via PHP (no .js suffix to avoid nginx static file handling) */ ?>
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Check-in Biodevas">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="theme-color" content="#0f172a">
        <?php if ($logo_url): ?>
        <link rel="apple-touch-icon" href="<?php echo esc_url($logo_url); ?>">
        <?php endif; ?>
        <!-- Service worker registration -->
        <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/checkin/?sw=1', { scope: '/checkin/' })
                    .then(reg => console.log('SW registered:', reg.scope))
                    .catch(err => console.warn('SW registration failed:', err));
            });
        }
        </script>
        <?php
    }

    /**
     * Get icon list for the manifest.
     */
    private static function get_icons(string $logo_url = ''): array
    {
        $icons = [];

        if ($logo_url) {
            $icons[] = [
                'src'   => $logo_url,
                'sizes' => '192x192',
                'type'  => 'image/png',
                'purpose' => 'any',
            ];
            $icons[] = [
                'src'   => $logo_url,
                'sizes' => '512x512',
                'type'  => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        // Fallback: generate from site icon
        $site_icon = get_site_icon_url(192);
        if ($site_icon) {
            $icons[] = [
                'src'   => $site_icon,
                'sizes' => '192x192',
                'type'  => 'image/png',
            ];
            $icons[] = [
                'src'   => get_site_icon_url(512),
                'sizes' => '512x512',
                'type'  => 'image/png',
            ];
        }

        return $icons;
    }

    /**
     * REST endpoint: flush pending offline check-in queue.
     */
    public static function rest_flush_queue(\WP_REST_Request $request): \WP_REST_Response
    {
        $items = $request->get_json_params()['items'] ?? [];
        $results = [];

        foreach ($items as $item) {
            $body = $item['body'] ?? '';
            parse_str($body, $data);

            $token = sanitize_text_field($data['id'] ?? '');
            $nonce = sanitize_text_field($data['nonce'] ?? '');

            if (!$token || !$nonce) {
                $results[] = ['token' => $token, 'status' => 'invalid'];
                continue;
            }

            // Verify nonce
            if (!wp_verify_nonce($nonce, 'bde_qr_checkin')) {
                $results[] = ['token' => $token, 'status' => 'expired_nonce'];
                continue;
            }

            $checkin = new Checkin_Handler();
            $result = $checkin->mark_as_attended_by_token($token);

            if (is_wp_error($result)) {
                $results[] = ['token' => $token, 'status' => 'error', 'message' => $result->get_error_message()];
            } else {
                $results[] = ['token' => $token, 'status' => 'success'];
            }
        }

        return new \WP_REST_Response([
            'processed' => count($results),
            'results'   => $results,
        ]);
    }
}
