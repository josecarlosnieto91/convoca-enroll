<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Admin
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Admin settings: tabs for General, Normas, Protección de datos, Emails.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings {

	use Admin_Settings_General;

	use Admin_Settings_Content;

	use Admin_Settings_Google;

	private const CACHE_KEY = 'convoca_enroll_diagnostic_cache';
	private const OPTION    = 'convoca_enroll_settings';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_save' ) );
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
		add_action( 'wp_ajax_convoca_preview_email', array( $this, 'ajax_preview' ) );
	}

	/* ── Render ────────────────────────────────── */

	public static function render(): void {
		// Security Guard: Check if critical dependencies are missing.
		if ( ! post_type_exists( 'actividad' ) && ! \Convoca\Core\Utils::is_plugin_active_safe( 'convoca-enroll/convoca-enroll.php' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Error crítico: El motor de inscripciones no parece estar registrado correctamente.', 'convoca-enroll' ) . '</p></div>';
		}

		if ( ! \Convoca\Core\Utils::is_plugin_active_safe( 'convoca-core/convoca-core.php' ) ) {
			echo '<div class="notice notice-warning"><p>⚠️ ' . esc_html__( 'Convoca Core no está activo. Algunas funciones podrían no estar disponibles.', 'convoca-enroll' ) . '</p></div>';
		}

		$s         = get_option( self::OPTION, array() );
		$templates = Email_Automation::get_templates();
		$tab       = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'general' ) );
		$tabs      = array(
			'general'         => __( 'General', 'convoca-enroll' ),
			'normas'          => __( 'Normas de inscripción', 'convoca-enroll' ),
			'rgpd'            => __( 'Protección de datos', 'convoca-enroll' ),
			'emails'          => __( 'Emails', 'convoca-enroll' ),
			'eval_reminders'  => __( 'Recordatorios de evaluación', 'convoca-enroll' ),
			'google_photos'   => __( 'Google Photos', 'convoca-enroll' ),
			'google_calendar' => __( 'Google Calendar', 'convoca-enroll' ),
			'status'          => __( 'Estado', 'convoca-enroll' ),
		);
		?>
		<div class="wrap conv-settings-wrap">
			<div class="conv-admin-header" style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
				<img src="<?php echo esc_url( CONVOCA_IMAGES_URL . 'logo.png' ); ?>" alt="Convoca Enroll" style="width: 80px; height: 80px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
				<div>
					<h1 style="margin: 0; padding: 0;"><?php esc_html_e( 'Ajustes de Inscripciones', 'convoca-enroll' ); ?></h1>
					<p style="margin: 5px 0 0; color: #666; font-size: 1.1em;"><?php esc_html_e( 'Gestión de actividades y motor de reservas', 'convoca-enroll' ); ?></p>
				</div>
			</div>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=conv-ajustes&tab=' . $slug ) ); ?>"
						class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php \Convoca\Core\Utils::render_stored_notices(); ?>

			<form method="post">
				<?php wp_nonce_field( 'convoca_enroll_settings_save', 'convoca_enroll_settings_nonce' ); ?>
				<input type="hidden" name="conv_enroll_active_tab" value="<?php echo esc_attr( $tab ); ?>">

				<?php
				match ( $tab ) {
					'normas' => self::render_tab_normas( $s ),
					'rgpd' => self::render_tab_rgpd( $s ),
					'emails' => self::render_tab_emails( $templates ),
					'eval_reminders' => self::render_tab_eval_reminders( $s ),
					'google_photos' => self::render_tab_google_photos( $s ),
					'google_calendar' => self::render_tab_google_calendar( $s ),
					'status' => self::render_tab_status(),
					default => self::render_tab_general( $s ),
				};

		?>
				<div style="margin-top:30px;">
					<button type="submit" class="convoca-btn convoca-btn-primary"><?php esc_html_e( 'Guardar ajustes', 'convoca-enroll' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	/* ── Tab: General ─────────────────────────── */


	/* ── Tab: Normas ──────────────────────────── */


	/* ── Tab: Protección de datos ─────────────── */


	/* ── Tab: Emails ──────────────────────────── */


	/* ── Tab: Recordatorios de Evaluación ─────────────── */


	/* ── Tab: Google Photos ───────────────────────── */


	/* ── Tab: Google Calendar ─────────────────────── */


	/* ── Save ──────────────────────────────────── */

	public function maybe_save(): void {
		if (
			! isset( $_POST['convoca_enroll_settings_nonce'] ) ||
			! wp_verify_nonce( wp_unslash( $_POST['convoca_enroll_settings_nonce'] ), 'convoca_enroll_settings_save' )
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = sanitize_text_field( wp_unslash( $_POST['convoca_enroll_active_tab'] ?? 'general' ) );

		// Load existing settings to merge.
		$settings = get_option( self::OPTION, array() );

		$conv = wp_unslash( $_POST['conv'] ?? array() );

		if ( $tab === 'general' ) {
			$settings['admin_email']            = sanitize_email( $conv['admin_email'] ?? '' );
			$settings['rgpd_version']           = sanitize_text_field( $conv['rgpd_version'] ?? '1.0' );
			$settings['limite_una_reserva']     = ! empty( $conv['limite_una_reserva'] ) ? 1 : 0;
			$settings['permitir_menores']       = ! empty( $conv['permitir_menores'] ) ? 1 : 0;
			$settings['bloquear_dni_duplicado'] = ! empty( $conv['bloquear_dni_duplicado'] ) ? '1' : '0';
			$settings['plazas_por_defecto']     = absint( $conv['plazas_por_defecto'] ?? 20 );
			$settings['cancel_window_hours']    = absint( $conv['cancel_window_hours'] ?? 24 );
			$settings['url_panel_reservas']     = esc_url_raw( $conv['url_panel_reservas'] ?? '' );
			$settings['sheets_enabled']         = absint( $conv['sheets_enabled'] ?? 0 );
			if ( ! defined( 'CONV_ENROLL_GOOGLE_SHEETS_API_KEY' ) ) {
				$settings['sheets_api_key'] = sanitize_text_field( $conv['sheets_api_key'] ?? '' );
			}
			$settings['log_retention_days'] = absint( $conv['log_retention_days'] ?? 30 );
			$settings['webhook_url']        = esc_url_raw( $conv['webhook_url'] ?? '' );
			$settings['webhook_secret']     = sanitize_text_field( $conv['webhook_secret'] ?? '' );

			if ( isset( $_POST['convoca_enroll_run_maintenance'] ) ) {
				if ( $_POST['convoca_enroll_run_maintenance'] === 'repair' ) {
					Maintenance::reparar_integridad();
					\Convoca\Core\Utils::set_admin_notice( 'Integridad reparada.', 'success' );
				} else {
					wp_safe_redirect( admin_url( 'admin.php?page=conv-ajustes&tab=general&maint_res=1' ) );
					exit;
				}
			}
		}

		if ( $tab === 'normas' ) {
			$settings['texto_introduccion'] = sanitize_textarea_field( $conv['texto_introduccion'] ?? '' );
			$settings['normas_inscripcion'] = wp_kses_post( $conv['normas_inscripcion'] ?? '' );
		}

		if ( $tab === 'rgpd' ) {
			$settings['url_privacidad']       = esc_url_raw( $conv['url_privacidad'] ?? '' );
			$settings['url_imagenes']         = esc_url_raw( $conv['url_imagenes'] ?? '' );
			$settings['url_proteccion_datos'] = esc_url_raw( $conv['url_proteccion_datos'] ?? '' );
		}

		if ( $tab === 'google_photos' ) {
			$settings['google_photos_refresh_token'] = sanitize_text_field( $conv['google_photos_refresh_token'] ?? '' );
			$settings['google_photos_album_prefix']  = sanitize_text_field( $conv['google_photos_album_prefix'] ?? 'Convoca - ' );
		}

		if ( $tab === 'google_calendar' ) {
			$settings['google_calendar_auto_sync']     = ! empty( $conv['google_calendar_auto_sync'] ) ? 1 : 0;
			$settings['google_calendar_id']            = sanitize_text_field( $conv['google_calendar_id'] ?? 'primary' );
			$settings['google_calendar_client_id']     = sanitize_text_field( $conv['google_calendar_client_id'] ?? '' );
			$settings['google_calendar_client_secret'] = sanitize_text_field( $conv['google_calendar_client_secret'] ?? '' );
			$settings['google_calendar_refresh_token'] = sanitize_text_field( $conv['google_calendar_refresh_token'] ?? '' );
		}

		if ( $tab === 'eval_reminders' ) {
			$eval                      = $conv['eval_reminder'] ?? array();
			$settings['eval_reminder'] = array(
				'active'    => ! empty( $eval['active'] ),
				'days'      => absint( $eval['days'] ?? 3 ),
				'subject'   => sanitize_text_field( $eval['subject'] ?? '' ),
				'body'      => wp_kses_post( $eval['body'] ?? '' ),
				'cc'        => sanitize_email( $eval['cc'] ?? '' ),
				'link_base' => esc_url_raw( $eval['link_base'] ?? '' ),
			);

			// Manage Cron.
			if ( ! empty( $eval['active'] ) ) {
				if ( ! wp_next_scheduled( 'convoca_enroll_eval_reminder' ) ) {
					wp_schedule_event( time(), 'daily', 'convoca_enroll_eval_reminder' );
				}
			} else {
				wp_clear_scheduled_hook( 'convoca_enroll_eval_reminder' );
			}
		}

		update_option( self::OPTION, $settings );
		delete_option( self::CACHE_KEY );

		// Templates (only on emails tab).
		if ( $tab === 'emails' ) {
			$settings['email_batch_size']  = absint( $conv['email_batch_size'] ?? 20 );
			$settings['email_max_retries'] = absint( $conv['email_max_retries'] ?? 3 );

			$tpl_raw   = wp_unslash( $_POST['tpl'] ?? array() );
			$templates = array();
			foreach ( Email_Automation::TEMPLATES as $slug ) {
				$templates[ $slug ] = array(
					'subject'       => sanitize_text_field( $tpl_raw[ $slug ]['subject'] ?? '' ),
					'body'          => wp_kses_post( $tpl_raw[ $slug ]['body'] ?? '' ),
					'attachment_id' => isset( $tpl_raw[ $slug ]['attachment_id'] ) ? absint( $tpl_raw[ $slug ]['attachment_id'] ) : '',
				);
			}
			Email_Automation::save_templates( $templates );
		}

		// Redirect back to same tab.
		\Convoca\Core\Utils::set_admin_notice( 'Ajustes guardados.', 'success' );
	}

	public function ajax_preview(): void {
		check_ajax_referer( 'convoca_enroll_preview_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'No tienes permisos.', 'convoca-enroll' ) );
		}

		$slug = sanitize_text_field( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( ! in_array( $slug, Email_Automation::TEMPLATES, true ) ) {
			wp_send_json_error( __( 'Plantilla no válida.', 'convoca-enroll' ) );
		}

		$html = Email_Automation::preview_html( $slug );
		wp_send_json_success( array( 'html' => $html ) );
	}

	public function handle_oauth_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['code'] ) ) {
			return;
		}

		if ( wp_unslash( $_GET['tab'] ?? '' ) === 'google_photos' ) {
			$google_photos = new Google_Photos();
			$result        = $google_photos->handle_oauth_callback( wp_unslash( $_GET['code'] ), wp_unslash( $_GET['state'] ?? '' ) );
			$redirect_tab  = 'google_photos';
		} elseif ( wp_unslash( $_GET['tab'] ?? '' ) === 'google_calendar' ) {
			$google_calendar = new Google_Calendar();
			$result          = $google_calendar->handle_oauth_callback( wp_unslash( $_GET['code'] ), wp_unslash( $_GET['state'] ?? '' ) );
			$redirect_tab    = 'google_calendar';
		} else {
			return;
		}

		if ( $result ) {
			\Convoca\Core\Utils::set_admin_notice( 'Autenticación con Google completada correctamente.', 'success' );
		} else {
			\Convoca\Core\Utils::set_admin_notice( 'Error en la autenticación con Google. Verifica las credenciales.', 'danger' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=conv-ajustes&tab=' . $redirect_tab ) );
		exit;
	}

	private static function render_tab_status(): void {
		$checks = self::get_system_checks( true );
		\Convoca\Core\Utils::render_diagnostic_panel( $checks, __( 'Estado del Sistema', 'convoca-enroll' ) );
	}

	/**
	 * Aporta las comprobaciones de este módulo al diagnóstico del núcleo.
	 *
	 * El método real es privado y el núcleo lo llamaba por reflexión; ahora esta es la puerta pública y
	 * el registro va por el filtro `convoca_system_checks`.
	 *
	 * @param array<string, array<int, array<string, string>>> $bloques Bloques ya aportados.
	 * @param bool                                             $force   Si se ignoran las cachés.
	 * @return array<string, array<int, array<string, string>>>
	 */
	public static function aportar_checks( array $bloques, $force = false ): array {
		$bloques['enroll'] = self::get_system_checks( (bool) $force );

		return $bloques;
	}

	/**
	 * Comprobaciones del sistema de este plugin.
	 *
	 * @return array<int, mixed>
	 */
	private static function get_system_checks( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_option( self::CACHE_KEY );
			if ( $cached && isset( $cached['expires'] ) && $cached['expires'] > time() ) {
				return $cached['results'];
			}
		}

		$checks = array();

		// 1. Plugins
		$plugin_definitions = array(
			'convoca-core'    => array(
				'name'     => 'Convoca Core',
				'class'    => '\\Convoca\\Core\\Utils',
				'severity' => 'error',
			),
			'convoca-members' => array(
				'name'     => 'Convoca Members',
				'class'    => '\\Convoca\\Members\\Process_Member',
				'severity' => 'warning',
			),
			'convoca-gateway' => array(
				'name'     => 'Convoca Gateway',
				'class'    => '\\Convoca\\Gateway\\Payment_Handler',
				'severity' => 'warning',
			),
		);

		foreach ( $plugin_definitions as $slug => $data ) {
			$is_active = class_exists( $data['class'] );
			$checks[]  = array(
				/* translators: %s: plugin name */
				'title'   => sprintf( __( 'Plugin: %s', 'convoca-enroll' ), $data['name'] ),
				'status'  => $is_active ? 'ok' : $data['severity'],
				'message' => $is_active ? __( 'Activo y funcionando.', 'convoca-enroll' ) : __( 'Plugin no detectado o inactivo.', 'convoca-enroll' ),
				/* translators: %s: plugin name */
				'fix'     => ! $is_active ? sprintf( __( 'Instala y activa el plugin %s.', 'convoca-enroll' ), $data['name'] ) : '',
			);
		}

		// 2. Pages. Los shortcodes deben ser los REALES de Enroll: recomendar uno
		// que nadie registra hace que el admin publique una página con el texto
		// literal a la vista del visitante (pasaba con [convoca_mis_inscripciones],
		// [convoca_checkin] y [convoca_pago_actividad]).
		$required_pages = array(
			'convoca_inscripcion_page' => array(
				'title'     => __( 'Página: Actividades', 'convoca-enroll' ),
				'shortcode' => '[convoca_inscripcion_page]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_inscripcion_page] para mostrar el listado de actividades.', 'convoca-enroll' ),
			),
			'convoca_panel_reservas'   => array(
				'title'     => __( 'Página: Mis Inscripciones', 'convoca-enroll' ),
				'shortcode' => '[convoca_panel_reservas]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_panel_reservas] para que los usuarios vean sus reservas.', 'convoca-enroll' ),
			),
			'convoca_evaluacion'       => array(
				'title'     => __( 'Página: Formulario de Evaluación', 'convoca-enroll' ),
				'shortcode' => '[convoca_evaluacion]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_evaluacion] para que los monitores evalúen las actividades.', 'convoca-enroll' ),
			),
		);

		foreach ( $required_pages as $slug => $data ) {
			$page     = self::find_page_by_shortcode( $data['shortcode'] );
			$checks[] = array(
				'title'   => $data['title'],
				'status'  => $page ? 'ok' : 'error',
				/* translators: %s: page title */
				'message' => $page ? sprintf( __( 'Detectada: %s', 'convoca-enroll' ), get_the_title( $page ) ) : __( 'No se ha encontrado ninguna página con este shortcode.', 'convoca-enroll' ),
				'fix'     => ! $page ? $data['fix'] : '',
			);
		}

		// 3. Check-in. NO es una página con shortcode: es la ruta /checkin/ que
		// registra Checkin_Handler por rewrite rule (+ PWA). Buscar una página con
		// un shortcode inexistente daba un error permanente e inarreglable.
		$rules = (array) get_option( 'rewrite_rules' );
		$regla = (string) ( $rules['^checkin/?$'] ?? '' );
		// No basta con que la regla exista: si apunta a una query var que el handler no
		// lee, `/checkin/` responde con la web normal y el escáner queda inalcanzable
		// mientras este diagnóstico decía «Activa». Hay que comprobar el destino.
		$checkin_ok = str_contains( $regla, 'convoca_enroll_checkin_page' );
		$checks[]   = array(
			'title'   => __( 'Ruta: Control de Asistencia (Check-in)', 'convoca-enroll' ),
			'status'  => $checkin_ok ? 'ok' : 'warning',
			'message' => $checkin_ok
				? sprintf( /* translators: %s: URL */ __( 'Activa en %s', 'convoca-enroll' ), home_url( '/checkin/' ) )
				: __( 'No se encuentra la regla de reescritura /checkin/.', 'convoca-enroll' ),
			'fix'     => $checkin_ok ? '' : __( 'Guarda Ajustes → Enlaces permanentes para regenerar las reglas (el check-in no funciona con enlaces simples).', 'convoca-enroll' ),
		);

		update_option(
			self::CACHE_KEY,
			array(
				'results' => $checks,
				'expires' => time() + HOUR_IN_SECONDS,
			)
		);

		return $checks;
	}

	/**
	 * Busca una página publicada por su shortcode.
	 *
	 * @return mixed
	 */
	private static function find_page_by_shortcode( string $shortcode ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s AND post_status = 'publish' AND post_type = 'page' LIMIT 1", '%' . $wpdb->esc_like( $shortcode ) . '%' ) );
	}
}
