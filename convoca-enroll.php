<?php
/**
 * Plugin Name:       Convoca Enroll
 * Plugin URI:        https://convoca.org
 * Description:       Centralized activity enrollment system.
 * Version: 2.5.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Jose Carlos Nieto Ramos
 * Author URI:        https://josecarlosnietoramos.wordpress.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       convoca-enroll
 * Domain Path:       /languages
 * Requires Plugins:  convoca-core
 * Network:           true
 */

if (!defined('ABSPATH')) {

    exit;
}

// Compatibility Check: Ensure Biodevas Common is loaded.
if (!class_exists('\\Convoca\\Core\\Utils')) {
    add_action('admin_notices', function () {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            'Biodevas Enroll requiere el plugin Biodevas Common Utilities activo para funcionar.'
        );
    });
    return;
}

/* ── Constants ────────────────────────────────────────────── */
if (!defined('BDE_VERSION')) {
    define('BDE_VERSION', '2.5.1');
}
if (!defined('BDE_DB_VERSION')) {
    define('BDE_DB_VERSION', '1.3.0');
}
if (!defined('BDE_FILE')) {
    define('BDE_FILE', __FILE__);
}
if (!defined('BDE_DIR')) {
    define('BDE_DIR', plugin_dir_path(__FILE__));
}
if (!defined('BDE_URL')) {
    define('BDE_URL', plugin_dir_url(__FILE__));
}

/* ── Autoloader ───────────────────────────────────────────── */
spl_autoload_register(function (string $class): void {
    $prefix = 'Convoca\\Enroll\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace($prefix, '', $class);
    $relative = strtolower(str_replace('_', '-', $relative));

    foreach (['includes/', 'admin/', 'public/'] as $dir) {
        // Standard WP convention: class-name.php
        $wp_file = BDE_DIR . $dir . 'class-' . $relative . '.php';
        if (file_exists($wp_file)) {
            require_once $wp_file;
            return;
        }

        // PSR-4 style: ClassName.php
        $psr_file = BDE_DIR . $dir . str_replace($prefix, '', $class) . '.php';
        $psr_file = str_replace('\\', '/', $psr_file); // Handle sub-namespaces if any
        if (file_exists($psr_file)) {
            require_once $psr_file;
            return;
        }
    }
});

/* ── Activation ───────────────────────────────────────────── */
register_activation_hook(__FILE__, function (): void {
    Convoca\Enroll\CPT_Actividad::register();
    Convoca\Enroll\CPT_Inscripcion::register();
    flush_rewrite_rules();

    Convoca\Enroll\Email_Automation::install_defaults();
    Convoca\Enroll\Email_Queue::create_table();
    Convoca\Enroll\Webhook_Dispatcher::create_table();
    Convoca\Enroll\Motor_Inscripcion::create_reservation_codes_table();

    if (false === get_option('bde_settings')) {
        update_option('bde_settings', [
            'admin_email' => get_option('admin_email'),
            'rgpd_version' => '1.0',
            'sheets_enabled' => false,
            'sheets_api_key' => '',
        ]);
    }

    // Auto-create "Panel de reservas" page if it doesn't exist.
    if (!get_option('bde_panel_page_id')) {
        $page_id = wp_insert_post([
            'post_title'   => 'Panel de reservas',
            'post_content' => '<!-- wp:shortcode -->[convoca_panel_reservas]<!-- /wp:shortcode -->',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'panel-reservas',
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            update_option('bde_panel_page_id', $page_id);
            update_post_meta($page_id, '_bde_panel_page', '1');
        }
    }

    // Roles and capabilities.
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_inscripciones');
    }

    add_role('monitor_actividad', __('Monitor de Actividad', 'convoca-enroll'), [
        'read' => true,
        'manage_inscripciones' => true,
        'edit_posts' => true,
        'gestionar_miembros' => true,
    ]);

    // Schedule cron events with appropriate intervals.
    if (!wp_next_scheduled('convoca_enroll_reminder_7dias')) {
        wp_schedule_event(time(), 'bdv_weekly', 'convoca_enroll_reminder_7dias');
    }
    if (!wp_next_scheduled('convoca_enroll_reminder_24h')) {
        wp_schedule_event(time(), 'daily', 'convoca_enroll_reminder_24h');
    }
    if (!wp_next_scheduled('convoca_enroll_reminder_1hora')) {
        wp_schedule_event(time(), 'hourly', 'convoca_enroll_reminder_1hora');
    }
    if (!wp_next_scheduled('convoca_enroll_feedback')) {
        wp_schedule_event(time(), 'daily', 'convoca_enroll_feedback');
    }
    if (!wp_next_scheduled('convoca_enroll_google_photos_share')) {
        wp_schedule_event(time(), 'daily', 'convoca_enroll_google_photos_share');
    }
    if (!wp_next_scheduled('convoca_enroll_process_email_queue')) {
        wp_schedule_event(time(), 'every_minute', 'convoca_enroll_process_email_queue');
    }
    if (!wp_next_scheduled('convoca_enroll_process_webhook_queue')) {
        wp_schedule_event(time(), 'every_minute', 'convoca_enroll_process_webhook_queue');
    }
    if (!wp_next_scheduled('convoca_enroll_cleanup_orphan_codes')) {
        wp_schedule_event(time(), 'daily', 'convoca_enroll_cleanup_orphan_codes');
    }

    // Save initial DB version.
    add_option('bde_db_version', BDE_DB_VERSION, '', false);
});

/* ── Deactivation ─────────────────────────────────────────── */
register_deactivation_hook(__FILE__, function (): void {
    wp_clear_scheduled_hook('convoca_enroll_reminder_7dias');
    wp_clear_scheduled_hook('convoca_enroll_reminder_24h');
    wp_clear_scheduled_hook('convoca_enroll_reminder_1hora');
    wp_clear_scheduled_hook('convoca_enroll_feedback');
    wp_clear_scheduled_hook('convoca_enroll_google_photos_share');
    wp_clear_scheduled_hook('convoca_enroll_process_email_queue');
    wp_clear_scheduled_hook('convoca_enroll_process_webhook_queue');
    wp_clear_scheduled_hook('convoca_enroll_eval_reminder');
    wp_clear_scheduled_hook('bde_daily_maintenance');
    flush_rewrite_rules();
});

/* ── Boot ─────────────────────────────────────────────────── */
add_action('plugins_loaded', function (): void {

    // External dependencies.
    if (file_exists(BDE_DIR . 'vendor/autoload.php')) {
        require_once BDE_DIR . 'vendor/autoload.php';
    }

    // Core.
    new Convoca\Enroll\CPT_Actividad();
    new Convoca\Enroll\CPT_Inscripcion();
    new Convoca\Enroll\Motor_Inscripcion();
    new Convoca\Enroll\Email_Automation();
    new Convoca\Enroll\Google_Photos();
    new Convoca\Enroll\Google_Calendar();
    new Convoca\Enroll\Rest_API();
    new Convoca\Enroll\Block_Inscripcion();
    new Convoca\Enroll\Google_Sheets();
    new Convoca\Enroll\Payment_Listener();
    new Convoca\Enroll\Webhook_Dispatcher();
    Convoca\Enroll\Volunteer_Hour_Tracker::init();
    Convoca\Enroll\CPT_Evaluacion::init();
    Convoca\Enroll\Eval_Reminder_Cron::init();
    Convoca\Enroll\PDF_Compromiso::init();

    // Upgrade Manager (checks for DB version upgrades on admin_init).
    new Convoca\Enroll\Enroll_Upgrade_Manager();

    if (!function_exists('bde_ensure_capabilities')) {
        /**
         * Ensure all necessary roles and capabilities are present.
         * Called on init to prevent race conditions or missing caps after updates.
         */
        function bde_ensure_capabilities() {
            // 1. Ensure Roles exist
            if (!get_role('monitor_actividad')) {
                add_role('monitor_actividad', __('Monitor de Actividad', 'convoca-enroll'), [
                    'read' => true,
                    'manage_inscripciones' => true,
                    'edit_posts' => true,
                    'gestionar_miembros' => true,
                    'view_reports' => true,
                ]);
            }

            // 2. Assign Capabilities
            $roles_to_check = [
                'administrator' => [
                    'manage_inscripciones',
                    'gestionar_miembros',
                    'gestionar_documentos_voluntariado',
                    'view_reports',
                    'manage_convoca_logs',
                    'manage_convoca_templates',
                    'manage_convoca_gateway',
                    'cst_manage_turnos',
                    'cst_view_stats',
                    'cst_audit_hours',
                    'bde_manage_checkin',
                    'bde_manage_evaluations',
                    'bde_view_reports',
                    'bdv_manage_hours',
                    'bdv_export_members',
                    'bdv_manage_webhooks',
                    'bdg_view_payments',
                    'bdg_manage_payments',
                    'common_view_logs',
                    'common_manage_backup',
                ],
                'monitor_actividad' => [
                    'manage_inscripciones',
                    'gestionar_miembros',
                    'gestionar_documentos_voluntariado',
                    'view_reports',
                    'cst_manage_turnos',
                    'cst_view_stats',
                    'cst_audit_hours',
                    'bde_manage_checkin',
                    'bde_manage_evaluations',
                    'bde_view_reports',
                    'bdv_manage_hours',
                ]
            ];

            foreach ($roles_to_check as $role_name => $caps) {
                $role = get_role($role_name);
                if (!$role) continue;
                
                foreach ($caps as $cap) {
                    if (!$role->has_cap($cap)) {
                        $role->add_cap($cap);
                    }
                }
            }
        }
    }
    add_action('init', 'bde_ensure_capabilities', 1);

    /**
     * Only run ensure_capabilities if the version hash has changed
     * (avoids writing to options table on every request).
     */
    add_action('init', function () {
        $version_hash = md5(BDE_VERSION . '_caps_v2');
        if (get_option('bde_caps_hash') !== $version_hash) {
            if (function_exists('bde_ensure_capabilities')) {
                bde_ensure_capabilities();
            }
            update_option('bde_caps_hash', $version_hash, false);
        }
    }, 0);

    // WP-CLI Commands.
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('bde webhooks', \Convoca\Enroll\CLI_Webhooks::class);
        \WP_CLI::add_command('bde migrate', \Convoca\Enroll\WP_CLI_Migration::class);
    }

    // Admin.
    if (is_admin()) {
        add_action('init', function() {
            new Convoca\Enroll\Admin_Page();
            new Convoca\Enroll\Admin_Settings();
            new Convoca\Enroll\Admin_Inscripcion_Form();
            new Convoca\Enroll\CSV_Exporter();
            new Convoca\Enroll\Admin_Monitor_CRM();
            new Convoca\Enroll\Admin_Reports();
            new Convoca\Enroll\Admin_Actividades();
            new Convoca\Enroll\Admin_Evaluaciones_Editor();
            Convoca\Enroll\Admin_Evaluaciones_List::init();
            Convoca\Enroll\Admin_Evaluaciones_Meta_Box::init();
            Convoca\Enroll\Admin_Evaluacion_Fields::init();
        });
    }

    // Public.
    new Convoca\Enroll\Form_Inscripcion();
    new Convoca\Enroll\Panel_Reservas();
    new Convoca\Enroll\Pagina_Inscripcion();
    new Convoca\Enroll\Checkin_Handler();
    Convoca\Enroll\Checkin_PWA::init();
    new Convoca\Enroll\Maintenance();
    new Convoca\Enroll\Email_Queue();
    Convoca\Enroll\Formulario_Evaluacion::init();

    // Clean up orphan reservation codes daily
    add_action('convoca_enroll_cleanup_orphan_codes', function () {
        \Convoca\Enroll\Motor_Inscripcion::cleanup_orphan_codes();
    });
});

// Register every_minute cron schedule at top level (must be available during activation)
add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['every_minute'])) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display'  => __('Cada minuto', 'convoca-enroll'),
        ];
    }
    if (!isset($schedules['bdv_weekly'])) {
        $schedules['bdv_weekly'] = [
            'interval' => 7 * DAY_IN_SECONDS,
            'display'  => __('Cada 7 días', 'convoca-enroll'),
        ];
    }
    return $schedules;
});
