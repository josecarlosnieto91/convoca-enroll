<?php
/**
 * Plugin Name:       Convoca Enroll
 * Plugin URI:        https://convoca.org.
 * Description:       Centralized activity enrollment system.
 * Version: 2.6.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Tested up to:      7.0
 * Author:            Jose Carlos Nieto Ramos
 * Author URI:        https://josecarlosnietoramos.wordpress.com.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html.
 * Text Domain:       convoca-enroll
 * Domain Path:       /languages
 * Requires Plugins:  convoca-core
 * Network:           true
 */
namespace Convoca\Enroll;


// Load translations.
add_action(
	'init',
	function () {
		wp_set_script_translations( 'convoca-enroll-scripts', 'convoca-enroll', plugin_dir_path( __FILE__ ) . 'languages/' );
	load_plugin_textdomain( 'convoca-enroll', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Composer autoload ─────────────────────────────── */
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

/* ── Convoca Core fallback ────────────────────────── */
// Core classes auto-loaded via Convoca Core's Composer PSR-4

// Compatibility Check: Ensure Convoca Common is loaded.
if ( ! class_exists( '\\Convoca\\Core\\Utils' ) ) {
	add_action(
		'admin_notices',
		function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				'Convoca Enroll requiere el plugin Convoca Common Utilities activo para funcionar.'
			);
		}
	);
	return;
}

/* ── Constants ────────────────────────────────────────────── */
if ( ! defined( 'CONV_ENROLL_VERSION' ) ) {
	define( 'CONV_ENROLL_VERSION', '2.5.1' );
}
if ( ! defined( 'CONV_ENROLL_DB_VERSION' ) ) {
	define( 'CONV_ENROLL_DB_VERSION', '1.3.0' );
}
if ( ! defined( 'CONV_ENROLL_FILE' ) ) {
	define( 'CONV_ENROLL_FILE', __FILE__ );
}
if ( ! defined( 'CONV_ENROLL_DIR' ) ) {
	define( 'CONV_ENROLL_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CONV_ENROLL_URL' ) ) {
	define( 'CONV_ENROLL_URL', plugin_dir_url( __FILE__ ) );
}

/* ── Autoloader ───────────────────────────────────────────── */
// PSR-4 autoloading handled by Composer (vendor/autoload.php)

/* ── Activation ───────────────────────────────────────────── */
/**
 * Check convoca-core is active at activation.
 */
register_activation_hook(
	__FILE__,
	function (): void {
		if ( ! class_exists( '\\Convoca\\Core\\Utils' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( 'Convoca Enroll requires Convoca Core to be active. Please activate Convoca Core first.' );
		}
		CPT_Actividad::register();
		CPT_Inscripcion::register();
		flush_rewrite_rules();

		Email_Automation::install_defaults();
		Email_Queue::create_table();
		Webhook_Dispatcher::create_table();
		Motor_Inscripcion::create_reservation_codes_table();

		// Media & Social Suite tables.
		Media\Media_Installer::install();
		Media\Media_Capabilities::ensure();
		if ( false === get_option( 'conv_enroll_settings' ) ) {
			update_option(
				'conv_enroll_settings',
				array(
					'admin_email'    => get_option( 'admin_email' ),
					'rgpd_version'   => '1.0',
					'sheets_enabled' => false,
					'sheets_api_key' => '',
				)
			);
		}

		// Auto-create "Panel de reservas" page if it doesn't exist.
		if ( ! get_option( 'conv_enroll_panel_page_id' ) ) {
			$page_id = wp_insert_post(
				array(
					'post_title'   => 'Panel de reservas',
					'post_content' => '<!-- wp:shortcode -->[convoca_panel_reservas]<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_name'    => 'panel-reservas',
				)
			);
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_option( 'conv_enroll_panel_page_id', $page_id );
				update_post_meta( $page_id, '_conv_panel_page', '1' );
			}
		}

		// Roles and capabilities.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_inscripciones' );
		}

		add_role(
			'monitor_actividad',
			__( 'Monitor de Actividad', 'convoca-enroll' ),
			array(
				'read'                 => true,
				'manage_inscripciones' => true,
				'edit_posts'           => true,
				'gestionar_miembros'   => true,
			)
		);

		// Schedule cron events with appropriate intervals.
		if ( ! wp_next_scheduled( 'convoca_enroll_reminder_7dias' ) ) {
			wp_schedule_event( time(), 'conv_weekly', 'convoca_enroll_reminder_7dias' );
		}
		if ( ! wp_next_scheduled( 'convoca_enroll_reminder_24h' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_enroll_reminder_24h' );
		}
		if ( ! wp_next_scheduled( 'convoca_enroll_reminder_1hora' ) ) {
			wp_schedule_event( time(), 'hourly', 'convoca_enroll_reminder_1hora' );
		}
		if ( ! wp_next_scheduled( 'convoca_enroll_feedback' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_enroll_feedback' );
		}
		if ( ! wp_next_scheduled( 'convoca_enroll_google_photos_share' ) ) {
			if ( ! wp_next_scheduled( 'convoca_social_token_healthcheck' ) ) {
				wp_schedule_event( time(), 'weekly', 'convoca_social_token_healthcheck' );
			}
			wp_schedule_event( time(), 'daily', 'convoca_enroll_google_photos_share' );
			if ( ! wp_next_scheduled( 'convoca_social_token_healthcheck' ) ) {
				wp_schedule_event( time(), 'weekly', 'convoca_social_token_healthcheck' );
			}
		}
		if ( ! wp_next_scheduled( 'convoca_enroll_process_email_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'convoca_enroll_process_email_queue' );
		}
		if ( ! wp_next_scheduled( 'convoca_enroll_process_webhook_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'convoca_enroll_process_webhook_queue' );
		}
		if ( ! wp_next_scheduled( 'convoca_enroll_cleanup_orphan_codes' ) ) {
			wp_schedule_event( time(), 'daily', 'convoca_enroll_cleanup_orphan_codes' );
		}

		// Save initial DB version.
		add_option( 'conv_enroll_db_version', CONV_ENROLL_DB_VERSION, '', false );
	}
);

/* ── Deactivation ─────────────────────────────────────────── */
register_deactivation_hook(
	__FILE__,
	function (): void {
		wp_clear_scheduled_hook( 'convoca_enroll_reminder_7dias' );
		wp_clear_scheduled_hook( 'convoca_enroll_reminder_24h' );
		wp_clear_scheduled_hook( 'convoca_enroll_reminder_1hora' );
		wp_clear_scheduled_hook( 'convoca_enroll_feedback' );
		wp_clear_scheduled_hook( 'convoca_enroll_google_photos_share' );
		if ( ! wp_next_scheduled( 'convoca_social_token_healthcheck' ) ) {
			wp_schedule_event( time(), 'weekly', 'convoca_social_token_healthcheck' );
		}
		wp_clear_scheduled_hook( 'convoca_enroll_process_email_queue' );
		wp_clear_scheduled_hook( 'convoca_enroll_process_webhook_queue' );
		wp_clear_scheduled_hook( 'convoca_enroll_eval_reminder' );
		wp_clear_scheduled_hook( 'conv_enroll_daily_maintenance' );
		flush_rewrite_rules();
	}
);

/* ── Boot ─────────────────────────────────────────────────── */
add_action(
	'plugins_loaded',
	function (): void {

		// External dependencies.
		if ( file_exists( CONV_ENROLL_DIR . 'vendor/autoload.php' ) ) {
			require_once CONV_ENROLL_DIR . 'vendor/autoload.php';
		}

		// Core.
		
		new CPT_Actividad();
		new CPT_Inscripcion();
		new Motor_Inscripcion();
		new Email_Automation();
		new Google_Photos();
		new Google_Calendar();
		new Rest_API();
		new Block_Inscripcion();
		new Google_Sheets();
		new Payment_Listener();
		new Webhook_Dispatcher();
		Volunteer_Hour_Tracker::init();
		CPT_Evaluacion::init();
		Eval_Reminder_Cron::init();
		PDF_Compromiso::init();

		// Upgrade Manager (checks for DB version upgrades on admin_init).
		new Enroll_Upgrade_Manager();

		// Media & Social Suite.
		new Media\Media_Upgrade_Manager();
		new Media\Media_Rest_API();
		Social\Social_OAuth::class;
		new Social\Social_Rest_API();
		Social\Social_Healthcheck::init();

		if ( ! function_exists( 'Convoca\Enroll\conv_ensure_enroll_capabilities' ) ) {
			/**
			 * Ensure all necessary roles and capabilities are present.
			 * Called on init to prevent race conditions or missing caps after updates.
			 */
			function conv_ensure_enroll_capabilities() {
				// 1. Ensure Roles exist
				if ( ! get_role( 'monitor_actividad' ) ) {
					add_role(
						'monitor_actividad',
						__( 'Monitor de Actividad', 'convoca-enroll' ),
						array(
							'read'                 => true,
							'manage_inscripciones' => true,
							'edit_posts'           => true,
							'gestionar_miembros'   => true,
							'view_reports'         => true,
						)
					);
				}

				// 2. Assign Capabilities
				$roles_to_check = array(
					'administrator'     => array(
						'manage_inscripciones',
						'gestionar_miembros',
						'gestionar_documentos_voluntariado',
						'view_reports',
						'manage_convoca_logs',
						'manage_convoca_templates',
						'manage_convoca_gateway',
						'convoca_shifts_manage_turnos',
						'convoca_shifts_view_stats',
						'convoca_shifts_audit_hours',
						'conv_manage_checkin',
						'conv_manage_evaluations',
						'conv_view_reports',
						'conv_manage_hours',
						'conv_export_members',
						'conv_manage_webhooks',
						'conv_view_payments',
						'conv_manage_payments',
						'common_view_logs',
						'common_manage_backup',
					),
					'monitor_actividad' => array(
						'manage_inscripciones',
						'gestionar_miembros',
						'gestionar_documentos_voluntariado',
						'view_reports',
						'convoca_shifts_manage_turnos',
						'convoca_shifts_view_stats',
						'convoca_shifts_audit_hours',
						'conv_manage_checkin',
						'conv_manage_evaluations',
						'conv_view_reports',
						'conv_manage_hours',
					),
				);

				foreach ( $roles_to_check as $role_name => $caps ) {
					$role = get_role( $role_name );
					if ( ! $role ) {
						continue;
					}

					foreach ( $caps as $cap ) {
						if ( ! $role->has_cap( $cap ) ) {
							$role->add_cap( $cap );
						}
					}
				}
			}
		}
		add_action( 'init', 'Convoca\Enroll\conv_ensure_enroll_capabilities', 1 );

		/**
		 * Only run ensure_capabilities if the version hash has changed
		 * (avoids writing to options table on every request).
		 */
		add_action(
			'init',
			function () {
				$version_hash = md5( CONV_ENROLL_VERSION . '_caps_v2' );
				if ( get_option( 'conv_enroll_caps_hash' ) !== $version_hash ) {
					if ( function_exists( 'Convoca\Enroll\conv_ensure_enroll_capabilities' ) ) {
						conv_ensure_enroll_capabilities();
					}
					update_option( 'conv_enroll_caps_hash', $version_hash, false );
				}
			},
			0
		);

		// WP-CLI Commands.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'bde webhooks', \Convoca\Enroll\CLI_Webhooks::class );
			\WP_CLI::add_command( 'bde migrate', \Convoca\Enroll\WP_CLI_Migration::class );
		}

		// Admin.
		if ( is_admin() ) {
			add_action(
				'init',
				function () {
					new Admin_Page();
					new Admin_Settings();
					new Admin_Inscripcion_Form();
					new CSV_Exporter();
					new Admin_Monitor_CRM();
					new Admin_Reports();
					new Admin_Actividades();
					new Admin_Evaluaciones_Editor();
					Admin_Evaluaciones_List::init();
					Admin_Evaluaciones_Meta_Box::init();
					Admin_Evaluacion_Fields::init();
				}
			);
		}

		// Public.
		new Form_Inscripcion();
		new Panel_Reservas();
		new Pagina_Inscripcion();
		new Checkin_Handler();
		Checkin_PWA::init();
		new Maintenance();
		new Email_Queue();
		Formulario_Evaluacion::init();

		// Clean up orphan reservation codes daily.
		add_action(
			'convoca_enroll_cleanup_orphan_codes',
			function () {
				\Convoca\Enroll\Motor_Inscripcion::cleanup_orphan_codes();
			}
		);
	}
);

// Register Action Scheduler hook for social publishing.
add_action(
	'convoca_social_publish',
	function ( $queue_id ) {
		Social\Social_Scheduler::process( $queue_id );
	} 
);

// Register every_minute cron schedule at top level (must be available during activation).
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		if ( ! isset( $schedules['every_minute'] ) ) {
			$schedules['every_minute'] = array(
				'interval' => 60,
				'display'  => __( 'Cada minuto', 'convoca-enroll' ),
			);
		}
		if ( ! isset( $schedules['conv_weekly'] ) ) {
			$schedules['conv_weekly'] = array(
				'interval' => 7 * DAY_IN_SECONDS,
				'display'  => __( 'Cada 7 días', 'convoca-enroll' ),
			);
		}
		return $schedules;
	}
);
