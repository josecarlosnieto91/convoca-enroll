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

		if ( ! \Convoca\Core\Utils::is_plugin_active_safe( 'convoca-common/convoca-common.php' ) ) {
			echo '<div class="notice notice-warning"><p>⚠️ ' . esc_html__( 'Convoca Common no está activo. Algunas funciones podrían no estar disponibles.', 'convoca-enroll' ) . '</p></div>';
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

	private static function render_tab_general( array $s ): void {
		?>
		<h2><?php esc_html_e( 'Configuración General', 'convoca-enroll' ); ?></h2>

		<div class="convoca-field">
			<label for="admin_email"><?php esc_html_e( 'Email del administrador', 'convoca-enroll' ); ?></label>
			<input type="email" id="admin_email" name="conv[admin_email]"
				value="<?php echo esc_attr( $s['admin_email'] ?? '' ); ?>">
		</div>

		<div class="convoca-field">
			<label for="rgpd_version"><?php esc_html_e( 'Versión RGPD', 'convoca-enroll' ); ?></label>
			<input type="text" id="rgpd_version" name="conv[rgpd_version]"
				value="<?php echo esc_attr( $s['rgpd_version'] ?? '1.0' ); ?>">
		</div>

		<div class="convoca-field">
			<div class="convoca-check-group">
				<input type="checkbox" id="limite_una_reserva" name="conv[limite_una_reserva]" value="1" <?php checked( ! empty( $s['limite_una_reserva'] ) ); ?>>
				<label for="limite_una_reserva"><?php esc_html_e( 'Cada persona adulta solo puede hacer una reserva de taller', 'convoca-enroll' ); ?></label>
			</div>
		</div>

		<div class="convoca-field">
			<div class="convoca-check-group">
				<input type="checkbox" id="permitir_menores" name="conv[permitir_menores]" value="1" <?php checked( $s['permitir_menores'] ?? true ); ?>>
				<label for="permitir_menores"><?php esc_html_e( 'Permitir que un adulto inscriba a menores o personas a su cargo', 'convoca-enroll' ); ?></label>
			</div>
		</div>

		<div class="convoca-field">
			<div class="convoca-check-group">
				<input type="checkbox" id="bloquear_dni_duplicado" name="conv[bloquear_dni_duplicado]" value="1" <?php checked( ( $s['bloquear_dni_duplicado'] ?? '1' ) === '1' ); ?>>
				<label for="bloquear_dni_duplicado"><?php esc_html_e( 'Evitar que se inscriba el mismo DNI más de una vez en la misma actividad', 'convoca-enroll' ); ?></label>
			</div>
		</div>

		<div class="convoca-field">
			<label for="plazas_por_defecto"><?php esc_html_e( 'Plazas por defecto', 'convoca-enroll' ); ?></label>
			<input type="number" id="plazas_por_defecto" name="conv[plazas_por_defecto]" min="0"
				value="<?php echo esc_attr( $s['plazas_por_defecto'] ?? '20' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Plazas que se asignarán por defecto al crear una nueva actividad.', 'convoca-enroll' ); ?></small>
		</div>

		<div class="convoca-field">
			<label for="url_panel_reservas"><?php esc_html_e( 'URL Panel de Reservas', 'convoca-enroll' ); ?></label>
			<input type="url" id="url_panel_reservas" name="conv[url_panel_reservas]"
				value="<?php echo esc_attr( $s['url_panel_reservas'] ?? home_url( '/panel-de-reservas/' ) ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Enlace a la página donde está incrustado el shortcode [convoca_panel_reservas].', 'convoca-enroll' ); ?></small>
		</div>

		<h2><?php esc_html_e( 'Webhooks', 'convoca-enroll' ); ?></h2>

		<?php if ( \Convoca\Core\License_Manager::has_pro( 'webhooks' ) ) : ?>
		<div class="convoca-field">
			<label for="webhook_url"><?php esc_html_e( 'URL del Webhook', 'convoca-enroll' ); ?></label>
			<input type="url" id="webhook_url" name="conv[webhook_url]"
				value="<?php echo esc_attr( $s['webhook_url'] ?? '' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'URL a la que se enviarán peticiones POST (make.com, zapier, etc.) al cambiar el estado de las inscripciones.', 'convoca-enroll' ); ?></small>
		</div>

		<div class="convoca-field">
			<label for="webhook_secret"><?php esc_html_e( 'Secreto del Webhook', 'convoca-enroll' ); ?></label>
			<input type="text" id="webhook_secret" name="conv[webhook_secret]"
				value="<?php echo esc_attr( $s['webhook_secret'] ?? '' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Firma opcional que se enviará en la cabecera X-Convoca-Signature con el hash SHA-256 del payload crudo.', 'convoca-enroll' ); ?></small>
		</div>
		<?php else : ?>
		<div class="convoca-alert convoca-alert--info" style="display:block;margin-bottom:20px;padding:12px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;">
			<p style="margin:0;">🔒 <strong>Webhooks salientes</strong> es una funcionalidad PRO. 
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-license' ) ); ?>" style="font-weight:600;">Activa tu licencia</a> para conectarte con make.com, Zapier y otros sistemas externos.</p>
		</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Google Sheets', 'convoca-enroll' ); ?></h2>

		<div class="convoca-field">
			<label for="sheets_enabled"><?php esc_html_e( 'Activar sincronización', 'convoca-enroll' ); ?></label>
			<select id="sheets_enabled" name="conv[sheets_enabled]">
				<option value="0" <?php selected( $s['sheets_enabled'] ?? '', '0' ); ?>><?php esc_html_e( 'Desactivado', 'convoca-enroll' ); ?></option>
				<option value="1" <?php selected( $s['sheets_enabled'] ?? '', '1' ); ?>><?php esc_html_e( 'Activado', 'convoca-enroll' ); ?></option>
			</select>
		</div>

		<div class="convoca-field">
			<label for="sheets_api_key"><?php esc_html_e( 'API Key de Google Sheets', 'convoca-enroll' ); ?></label>
			<?php
			$has_constant = defined( 'CONV_ENROLL_GOOGLE_SHEETS_API_KEY' );
			$display_val  = $has_constant ? '****************' . substr( CONV_ENROLL_GOOGLE_SHEETS_API_KEY, -4 ) : ( $s['sheets_api_key'] ?? '' );
			?>
			<input type="text" id="sheets_api_key" name="conv[sheets_api_key]"
				value="<?php echo esc_attr( $display_val ); ?>" <?php echo $has_constant ? 'disabled' : ''; ?>>
			<?php if ( $has_constant ) : ?>
				<small class="convoca-small" style="color:green;">✓ <?php esc_html_e( 'Definida vía constante en wp-config.php.', 'convoca-enroll' ); ?></small>
			<?php else : ?>
				<small class="convoca-small">⚠️ <?php esc_html_e( 'Los datos personales se enviarán a Google. Asegúrate de cumplir la RGPD.', 'convoca-enroll' ); ?></small>
				<small class="convoca-small"><em><?php esc_html_e( 'Recomendación: define CONV_ENROLL_GOOGLE_SHEETS_API_KEY en tu wp-config.php.', 'convoca-enroll' ); ?></em></small>
			<?php endif; ?>
		</div>

		<h2><?php esc_html_e( 'Mantenimiento y Logs', 'convoca-enroll' ); ?></h2>

		<div class="convoca-field">
			<label for="log_retention_days"><?php esc_html_e( 'Retención de logs (días)', 'convoca-enroll' ); ?></label>
			<input type="number" id="log_retention_days" name="conv[log_retention_days]" min="1" max="365"
				value="<?php echo esc_attr( $s['log_retention_days'] ?? '30' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Días que se conservarán los logs antes de ser eliminados automáticamente.', 'convoca-enroll' ); ?></small>
		</div>

		<div class="convoca-field">
			<label><?php esc_html_e( 'Integridad de datos', 'convoca-enroll' ); ?></label>
			<div style="display:flex;gap:10px;">
				<button type="submit" name="conv_enroll_run_maintenance" value="validate" class="convoca-btn convoca-btn-outline"><?php esc_html_e( 'Validar integridad', 'convoca-enroll' ); ?></button>
				<button type="submit" name="conv_enroll_run_maintenance" value="repair" class="convoca-btn convoca-btn--danger"
					onclick="return confirm('<?php esc_attr_e( 'Esto borrará inscripciones huérfanas y recontará plazas. ¿Continuar?', 'convoca-enroll' ); ?>');">
					<?php esc_html_e( 'Reparar integridad', 'convoca-enroll' ); ?>
				</button>
			</div>
			
			<?php
			if ( isset( $_GET['maint_res'] ) ) :
				$res = Maintenance::validar_integridad();
				?>
				<div class="convoca-alert convoca-alert--warning" style="margin-top:10px;">
					<p><strong><?php esc_html_e( 'Resultados de validación:', 'convoca-enroll' ); ?></strong></p>
					<ul>
						<li><?php /* translators: %s: number of orphan records */ printf( esc_html__( 'Huérfanos: %s', 'convoca-enroll' ), esc_html( $res['orphans'] ) ); ?></li>
							<li><?php /* translators: %s: number of capacity mismatches */ printf( esc_html__( 'Descuadres de plazas: %s', 'convoca-enroll' ), esc_html( $res['capacity_mismatch'] ) ); ?></li>
					</ul>
					<?php if ( ! empty( $res['errors'] ) ) : ?>
						<pre style="max-height:150px;overflow:auto;background:#f0f0f1;padding:5px;"><?php echo esc_html( implode( "\n", $res['errors'] ) ); ?></pre>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- PRO Features Section -->
		<div class="conv-pro-section" style="margin-top:40px;padding:25px;background:#fefce8;border:2px dashed #eab308;border-radius:12px;">
			<h2 style="margin-top:0;color:#a16207;">✨ Funcionalidades PRO</h2>
			<p style="color:#713f12;">Las siguientes funcionalidades están disponibles con una licencia PRO. <a href="<?php echo esc_url( admin_url( 'admin.php?page=convoca-license' ) ); ?>">Activa tu licencia</a> para desbloquearlas.</p>
			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-top:15px;">
				<div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;opacity:0.7;">
					<span style="font-size:1.2rem;">📱</span>
					<span style="font-weight:600;margin-left:8px;">PWA Check-in QR</span>
					<span style="display:block;font-size:11px;color:#94a3b8;margin-top:4px;">Registro QR por móvil para participantes</span>
				</div>
				<div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;opacity:0.7;">
					<span style="font-size:1.2rem;">📄</span>
					<span style="font-weight:600;margin-left:8px;">PDF Memories</span>
					<span style="display:block;font-size:11px;color:#94a3b8;margin-top:4px;">Exportación PDF y memoria de actividades</span>
				</div>
			</div>
		</div>
		<?php
	}

	/* ── Tab: Normas ──────────────────────────── */

	private static function render_tab_normas( array $s ): void {
		$default_normas = '<h3>Normas de inscripción</h3> <!-- UTF-8 Fix -->
<p>En esta primera fase de inscripción cada persona podrá realizar una única reserva de taller.</p>
<p>Las reservas múltiples realizadas por una misma persona podrán ser canceladas por la organización.</p>
<p><strong>Importante:</strong> La inscripción debe realizarla siempre una persona adulta. En el caso de menores o personas a su cargo, la reserva deberá hacerla su padre, madre, tutor/a o persona responsable.</p>
<p><strong>Excepción:</strong> Si realizas la inscripción para un menor o una persona a tu cargo, el adulto podrá aparecer asociado a más de una inscripción, ya que actúa como responsable de la reserva.</p>
<p>Al realizar la inscripción aceptas el tratamiento de tus datos personales para la gestión de la actividad y autorizas la posible toma de contenido gráfico durante el desarrollo de los talleres, destinado a la comunicación y difusión de Convoca.</p>';

		$normas = $s['normas_inscripcion'] ?? $default_normas;
		$intro  = $s['texto_introduccion'] ?? 'Antes de realizar tu inscripción te recomendamos explorar primero todos los talleres disponibles para descubrir las propuestas que más te interesan.';
		?>
		<h2>Texto de introducción</h2>
		<p class="description">Se muestra antes del formulario de inscripción.</p>
		<textarea name="conv[texto_introduccion]" rows="3" class="large-text"><?php echo esc_textarea( $intro ); ?></textarea>

		<h2>Normas de inscripción</h2>
		<p class="description">Contenido que se muestra como normas en la página de inscripción. Puedes usar HTML.</p>
		<?php
		wp_editor(
			$normas,
			'convoca_enroll_normas_inscripcion',
			array(
				'textarea_name' => __( 'conv[normas_inscripcion]', 'convoca-enroll' ),
				'textarea_rows' => 12,
				'media_buttons' => false,
				'teeny'         => false,
			)
		);
	}

	/* ── Tab: Protección de datos ─────────────── */

	private static function render_tab_rgpd( array $s ): void {
		?>
		<div class="convoca-field">
			<label for="url_privacidad"><?php esc_html_e( 'URL Política de Privacidad', 'convoca-enroll' ); ?></label>
			<input type="url" id="url_privacidad" name="conv[url_privacidad]"
				value="<?php echo esc_attr( $s['url_privacidad'] ?? home_url( '/politica-de-privacidad/' ) ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Enlace a la página de política de privacidad.', 'convoca-enroll' ); ?></small>
		</div>

		<div class="convoca-field">
			<label for="url_imagenes"><?php esc_html_e( 'URL Uso de Imágenes', 'convoca-enroll' ); ?></label>
			<input type="url" id="url_imagenes" name="conv[url_imagenes]"
				value="<?php echo esc_attr( $s['url_imagenes'] ?? '' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Enlace a la información de uso de imágenes/contenido gráfico.', 'convoca-enroll' ); ?></small>
		</div>

		<div class="convoca-field">
			<label for="url_proteccion_datos"><?php esc_html_e( 'Enlace «aquí» (protección de datos)', 'convoca-enroll' ); ?></label>
			<input type="url" id="url_proteccion_datos" name="conv[url_proteccion_datos]"
				value="<?php echo esc_attr( $s['url_proteccion_datos'] ?? '' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'URL a la que apunta «aquí» en la frase «Puedes consultar la información completa sobre protección de datos y uso de imágenes aquí.»', 'convoca-enroll' ); ?></small>
		</div>
		<?php
	}

	/* ── Tab: Emails ──────────────────────────── */

	private static function render_tab_emails( array $templates ): void {
		$labels = array(
			'recepcion'              => __( 'Inscripción recibida', 'convoca-enroll' ),
			'lista_espera'           => 'En lista de espera',
			'promocion_lista_espera' => __( 'Promoción de lista de espera', 'convoca-enroll' ),
			'confirmacion_plaza'     => __( 'Confirmación de plaza', 'convoca-enroll' ),
			'cancelacion_reserva'    => __( 'Cancelación de reserva', 'convoca-enroll' ),
			'recordatorio_24h'       => 'Recordatorio 24h',
			'feedback_post'          => __( 'Post-evento (feedback)', 'convoca-enroll' ),
		);
		?>
		<p>Variables disponibles:
			<code><?php echo esc_html( implode( '</code> <code>', Email_Automation::VARIABLES ) ); ?></code>
		</p>

		<?php
		foreach ( Email_Automation::TEMPLATES as $slug ) :
			$tpl            = $templates[ $slug ] ?? array(
				'subject'       => '',
				'body'          => '',
				'attachment_id' => '',
			);
			$attachment_id  = $tpl['attachment_id'] ?? '';
			$attachment_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
			?>
			<div class="conv-template-card" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; background: #fff;">
				<div style="display: flex; justify-content: space-between; align-items: flex-start;">
					<h3><?php echo esc_html( $labels[ $slug ] ?? $slug ); ?></h3>
					<button type="button" class="button conv-preview-email" data-slug="<?php echo esc_attr( $slug ); ?>">
						👁️ Previsualizar
					</button>
				</div>
				
				<p>
					<label><strong>Asunto</strong></label><br>
					<input type="text" name="tpl[<?php echo esc_attr( $slug ); ?>][subject]"
						value="<?php echo esc_attr( $tpl['subject'] ); ?>" class="large-text">
				</p>
				<p>
					<label><strong>Cuerpo</strong></label><br>
					<textarea name="tpl[<?php echo esc_attr( $slug ); ?>][body]" rows="6"
						class="large-text"><?php echo esc_textarea( $tpl['body'] ); ?></textarea>
				</p>

				<div class="conv-attachment-row" style="margin-top: 10px;">
					<label><strong>Adjunto</strong></label><br>
					<div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
						<input type="hidden" name="tpl[<?php echo esc_attr( $slug ); ?>][attachment_id]" value="<?php echo esc_attr( $attachment_id ); ?>" class="conv_enroll_attachment_id">
						<button type="button" class="convoca-btn convoca-btn-outline conv-upload-attachment"><?php echo esc_html__( 'Seleccionar archivo', 'convoca-enroll' ); ?></button>
						<button type="button" class="convoca-btn convoca-btn-outline convoca-btn--danger conv-remove-attachment" <?php echo ! $attachment_id ? 'style="display:none"' : ''; ?>><?php echo esc_html__( 'Quitar', 'convoca-enroll' ); ?></button>
						<span class="convoca-badge convoca-badge--info conv_enroll_attachment_name"><?php echo $attachment_url ? esc_html( basename( $attachment_url ) ) : esc_html__( 'Ninguno', 'convoca-enroll' ); ?></span>
					</div>
				</div>
			</div>
		<?php endforeach; ?>

		<h2>Configuración de la cola</h2>
		<table class="form-table">
			<?php $settings = get_option( self::OPTION, array() ); ?>
			<tr>
				<th><label for="email_batch_size">Número de correos por lote</label></th>
				<td>
					<input type="number" id="email_batch_size" name="conv[email_batch_size]" min="1" max="100"
						value="<?php echo esc_attr( $settings['email_batch_size'] ?? '20' ); ?>" class="small-text">
					<p class="description">Número máximo de correos a enviar en cada ejecución del cron (cada minuto).</p>
				</td>
			</tr>
			<tr>
				<th><label for="email_max_retries">Reintentos máximos</label></th>
				<td>
					<input type="number" id="email_max_retries" name="conv[email_max_retries]" min="0" max="10"
						value="<?php echo esc_attr( $settings['email_max_retries'] ?? '3' ); ?>" class="small-text">
					<p class="description">Número de veces que se intentará reenviar un correo si falla antes de marcarlo como fallido.</p>
				</td>
			</tr>
		</table>

<script>
			document.addEventListener('DOMContentLoaded', function() {
				window._convActiveUploadRow = null;

				// WP Media Upload.
				let frame;
				document.querySelectorAll('.conv-upload-attachment').forEach(function(btn) {
					btn.addEventListener('click', function(e) {
						e.preventDefault();
						const row = this.closest('.conv-attachment-row');
						
						if (!frame) {
							frame = wp.media({
								title: 'Seleccionar adjunto para email',
								multiple: false
							});
							frame.on('select', function() {
								const attachment = frame.state().get('selection').first().toJSON();
								if (window._convActiveUploadRow) {
									window._convActiveUploadRow.querySelector('.conv_enroll_attachment_id').value = attachment.id;
									window._convActiveUploadRow.querySelector('.conv_enroll_attachment_name').textContent = attachment.filename;
									window._convActiveUploadRow.querySelector('.conv-remove-attachment').style.display = 'inline-block';
								}
								// Cleanup active row reference after selection.
								window._convActiveUploadRow = null;
							});
						}
						window._convActiveUploadRow = row;
						frame.open();
					});
				});

				// Remove Attachment.
				document.querySelectorAll('.conv-remove-attachment').forEach(function(btn) {
					btn.addEventListener('click', function() {
						const row = this.closest('.conv-attachment-row');
						row.querySelector('.conv_enroll_attachment_id').value = '';
						row.querySelector('.conv_enroll_attachment_name').textContent = 'Ningún archivo seleccionado';
						this.style.display = 'none';
					});
				});
			});
		</script>
		<?php
	}

	/* ── Tab: Recordatorios de Evaluación ─────────────── */

	private static function render_tab_eval_reminders( array $s ): void {
		$settings       = get_option( self::OPTION, array() );
		$eval_reminders = $settings['eval_reminder'] ?? array();
		$is_active      = ! empty( $eval_reminders['active'] );
		$days           = $eval_reminders['days'] ?? '3';
		$subject        = $eval_reminders['subject'] ?? '¿Cómo fue tu experiencia en {nombre_actividad}?';
		$body           = $eval_reminders['body'] ?? "Hola {evaluador_nombre},\n\nGracias por asistir a \"{nombre_actividad}\" el pasado {fecha_actividad}. Nos encantaría conocer tu opinión para seguir mejorando.\n\nPor favor, dedica un minuto a evaluar la actividad aquí:\n{link_evaluacion}\n\n¡Gracias por tu colaboración!";
		$cc             = $eval_reminders['cc'] ?? '';
		$link_base      = $eval_reminders['link_base'] ?? '';

		$next_run      = wp_next_scheduled( 'convoca_enroll_eval_reminder' );
		$next_run_text = $next_run ? wp_date( 'd/m/Y H:i:s', $next_run ) : 'No programado';
		?>
		<p class="description">
			Configura el envío automático de emails recordando a los <strong>monitores y voluntarios que asistieron</strong> que deben evaluar la actividad.
		</p>

		<table class="form-table">
			<tr>
				<th><label for="eval_reminder_active">Activar recordatorios</label></th>
				<td>
					<label>
						<input type="checkbox" id="eval_reminder_active" name="conv[eval_reminder][active]" value="1" <?php checked( $is_active ); ?>>
						Enviar emails automáticamente
					</label>
					<p class="description">Próxima ejecución del cron: <strong><?php echo esc_html( $next_run_text ); ?></strong></p>
				</td>
			</tr>
			<tr>
				<th><label for="eval_reminder_days">Días después de finalizar</label></th>
				<td>
					<input type="number" id="eval_reminder_days" name="conv[eval_reminder][days]" min="0" value="<?php echo esc_attr( $days ); ?>" class="small-text"> días
					<p class="description">Ej: 3 significa que se enviará 3 días después de la fecha de fin de la actividad.</p>
				</td>
			</tr>
			<tr>
				<th><label for="eval_reminder_subject">Asunto del email</label></th>
				<td>
					<input type="text" id="eval_reminder_subject" name="conv[eval_reminder][subject]" value="<?php echo esc_attr( $subject ); ?>" class="large-text">
				</td>
			</tr>
			<tr>
				<th><label for="eval_reminder_body">Cuerpo del email</label></th>
				<td>
					<textarea id="eval_reminder_body" name="conv[eval_reminder][body]" rows="8" class="large-text"><?php echo esc_textarea( $body ); ?></textarea>
					<p class="description">
						Variables permitidas:<br>
						<code>{nombre_actividad}</code>, <code>{fecha_actividad}</code>, <code>{evaluador_nombre}</code>, <code>{link_evaluacion}</code>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="eval_reminder_cc">Correo de copia (CC)</label></th>
				<td>
					<input type="email" id="eval_reminder_cc" name="conv[eval_reminder][cc]" value="<?php echo esc_attr( $cc ); ?>" class="regular-text">
					<p class="description">Opcional. Recibirá una copia de cada recordatorio enviado.</p>
				</td>
			</tr>
			<tr>
				<th><label for="eval_reminder_link_base">URL base para el formulario</label></th>
				<td>
					<input type="url" id="eval_reminder_link_base" name="conv[eval_reminder][link_base]" value="<?php echo esc_attr( $link_base ); ?>" class="regular-text">
					<p class="description">Página donde has colocado el shortcode <code>[formulario_evaluacion]</code>. Si lo dejas en blanco, se usará el enlace de la propia actividad más el parámetro <code>?evaluar=1</code>.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/* ── Tab: Google Photos ───────────────────────── */

	private static function render_tab_google_photos( array $s ): void {
		$google_photos = new Google_Photos();
		$auth_url      = $google_photos->get_auth_url();
		$is_configured = $google_photos->is_configured();
		?>
		<p class="description">
			Configura la integración con Google Photos para crear automáticamente álbumes de fotos de tus actividades.
			<br>Para usar esta funcionalidad necesitas un proyecto en Google Cloud Console con la API de Photos Library habilitada.
		</p>

		<table class="form-table">
			<tr>
				<th><label for="google_photos_enabled">Activar Google Photos</label></th>
				<td>
					<select id="google_photos_enabled" name="conv[google_photos_enabled]">
						<option value="0" <?php selected( $s['google_photos_enabled'] ?? '', '0' ); ?>>Desactivado</option>
						<option value="1" <?php selected( $s['google_photos_enabled'] ?? '', '1' ); ?>>Activado</option>
					</select>
					<p class="description">Cuando esté activado, las actividades podrán crear álbumes automáticamente.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_client_id">Client ID (OAuth 2.0)</label></th>
				<td>
					<input type="text" id="google_photos_client_id" name="conv[google_photos_client_id]"
						value="<?php echo esc_attr( $s['google_photos_client_id'] ?? '' ); ?>" class="regular-text">
					<p class="description">ID de cliente de OAuth 2.0 de Google Cloud Console.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_client_secret">Client Secret</label></th>
				<td>
					<input type="password" id="google_photos_client_secret" name="conv[google_photos_client_secret]"
						value="<?php echo esc_attr( $s['google_photos_client_secret'] ?? '' ); ?>" class="regular-text">
					<p class="description">Secreto de cliente de OAuth 2.0.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_refresh_token">Refresh Token</label></th>
				<td>
					<input type="password" id="google_photos_refresh_token" name="conv[google_photos_refresh_token]"
						value="<?php echo esc_attr( $s['google_photos_refresh_token'] ?? '' ); ?>" class="regular-text">
					<p class="description">Token de acceso automático. Obtén uno autenticándote abaixo.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_photos_album_prefix">Prefijo de álbum</label></th>
				<td>
					<input type="text" id="google_photos_album_prefix" name="conv[google_photos_album_prefix]"
						value="<?php echo esc_attr( $s['google_photos_album_prefix'] ?? 'Convoca - ' ); ?>" class="regular-text">
					<p class="description">Prefijo que se añadirá al nombre del álbum (ej. "Convoca - Taller de Bosque").</p>
				</td>
			</tr>
		</table>

		<h3>Autenticación</h3>
		<table class="form-table">
			<tr>
				<th>Estado de la conexión</th>
				<td>
					<?php if ( $is_configured ) : ?>
						<span style="color: green; font-weight: bold;">✓ Conectado</span>
						<p class="description">La integración con Google Photos está lista.</p>
					<?php else : ?>
						<span style="color: red; font-weight: bold;">✗ No configurado</span>
						<p class="description">Completa los datos de arriba y autentícate para usar la integración.</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( ! empty( $auth_url ) && ! $is_configured ) : ?>
			<tr>
				<th>Autenticarse</th>
				<td>
					<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
						Autenticar con Google
					</a>
					<p class="description">Al hacer clic serás redirigido a Google para autorizar el acceso a tu cuenta.</p>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<h3>Scopes necesarios</h3>
		<p class="description">
			En tu proyecto de Google Cloud Console, asegurate de habilitar la <strong>Photos Library API</strong> y configurar los siguientes scopes:
		</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><code>https://www.googleapis.com/auth/photoslibrary.appendonly</code></li>.
			<li><code>https://www.googleapis.com/auth/photoslibrary.sharing</code></li>.
		</ul>

		<p class="description" style="margin-top: 20px;">
			<strong>URI de redirección autorizada:</strong><br>
			<code><?php echo esc_html( admin_url( 'admin.php?page=conv-ajustes&tab=google_photos' ) ); ?></code>
		</p>
		<?php
	}

	/* ── Tab: Google Calendar ─────────────────────── */

	private static function render_tab_google_calendar( array $s ): void {
		$google_calendar = new Google_Calendar();
		$auth_url        = $google_calendar->get_auth_url();
		$is_configured   = $google_calendar->is_configured();
		?>
		<p class="description">
			Configura la integración con Google Calendar para sincronizar automáticamente tus actividades.
			<br>Puedes reutilizar el mismo proyecto de Google Cloud de Google Photos habilitando la <strong>Google Calendar API</strong>.
		</p>

		<table class="form-table">
			<tr>
				<th><label for="google_calendar_auto_sync">Sincronización automática</label></th>
				<td>
					<label>
						<input type="checkbox" id="google_calendar_auto_sync" name="conv[google_calendar_auto_sync]" value="1"
							<?php checked( ! empty( $s['google_calendar_auto_sync'] ) ); ?>>
						Crear y actualizar eventos automáticamente en Google Calendar al guardar una actividad.
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_id">ID del Calendario</label></th>
				<td>
					<input type="text" id="google_calendar_id" name="conv[google_calendar_id]"
						value="<?php echo esc_attr( $s['google_calendar_id'] ?? 'primary' ); ?>" class="regular-text">
					<p class="description">Usa <code>primary</code> para el calendario principal o el ID de un calendario específico.</p>
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_client_id">Client ID (OAuth 2.0)</label></th>
				<td>
					<input type="text" id="google_calendar_client_id" name="conv[google_calendar_client_id]"
						value="<?php echo esc_attr( $s['google_calendar_client_id'] ?? $s['google_photos_client_id'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_client_secret">Client Secret</label></th>
				<td>
					<input type="password" id="google_calendar_client_secret" name="conv[google_calendar_client_secret]"
						value="<?php echo esc_attr( $s['google_calendar_client_secret'] ?? $s['google_photos_client_secret'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="google_calendar_refresh_token">Refresh Token</label></th>
				<td>
					<input type="password" id="google_calendar_refresh_token" name="conv[google_calendar_refresh_token]"
						value="<?php echo esc_attr( $s['google_calendar_refresh_token'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
		</table>

		<h3>Autenticación</h3>
		<table class="form-table">
			<tr>
				<th>Estado de la conexión</th>
				<td>
					<?php if ( $is_configured ) : ?>
						<span style="color: green; font-weight: bold;">✓ Conectado</span>
					<?php else : ?>
						<span style="color: red; font-weight: bold;">✗ No configurado</span>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( ! empty( $auth_url ) && ! $is_configured ) : ?>
			<tr>
				<th>Autenticarse</th>
				<td>
					<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
						Autenticar con Google Calendar
					</a>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<p class="description" style="margin-top: 20px;">
			<strong>URI de redirección autorizada:</strong><br>
			<code><?php echo esc_html( admin_url( 'admin.php?page=conv-ajustes&tab=google_calendar' ) ); ?></code>
		</p>
		<?php
	}

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
					wp_redirect( admin_url( 'admin.php?page=conv-ajustes&tab=general&maint_res=1' ) );
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

		wp_redirect( admin_url( 'admin.php?page=conv-ajustes&tab=' . $redirect_tab ) );
		exit;
	}

	private static function render_tab_status(): void {
		$checks = self::get_system_checks( true );
		\Convoca\Core\Utils::render_diagnostic_panel( $checks, __( 'Estado del Sistema', 'convoca-enroll' ) );
	}

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
				'name'     => 'Convoca Common',
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

		// 2. Pages
		$required_pages = array(
			'convoca_calendario'        => array(
				'title'     => __( 'Página: Calendario de Actividades', 'convoca-enroll' ),
				'shortcode' => __( '[convoca_calendario]', 'convoca-enroll' ),
				'fix'       => __( 'Crea una página con el shortcode [convoca_calendario] para mostrar el listado de actividades.', 'convoca-enroll' ),
			),
			'convoca_mis_inscripciones' => array(
				'title'     => __( 'Página: Mis Inscripciones', 'convoca-enroll' ),
				'shortcode' => __( '[convoca_mis_inscripciones]', 'convoca-enroll' ),
				'fix'       => __( 'Crea una página con el shortcode [convoca_mis_inscripciones] para que los usuarios vean sus reservas.', 'convoca-enroll' ),
			),
			'convoca_checkin'           => array(
				'title'     => __( 'Página: Control de Asistencia (Check-in)', 'convoca-enroll' ),
				'shortcode' => '[convoca_checkin]',
				'fix'       => __( 'Crea una página con el shortcode [convoca_checkin] para que los monitores registren la asistencia.', 'convoca-enroll' ),
			),
			'convoca_pago_actividad'    => array(
				'title'     => __( 'Página: Pago de Actividad', 'convoca-enroll' ),
				'shortcode' => __( '[convoca_pago_actividad]', 'convoca-enroll' ),
				'fix'       => __( 'Crea una página con el shortcode [convoca_pago_actividad] para procesar los pagos de inscripción.', 'convoca-enroll' ),
			),
			'formulario_evaluacion'     => array(
				'title'     => __( 'Página: Formulario de Evaluación', 'convoca-enroll' ),
				'shortcode' => __( '[formulario_evaluacion]', 'convoca-enroll' ),
				'fix'       => __( 'Crea una página con el shortcode [formulario_evaluacion] para que los monitores evalúen las actividades.', 'convoca-enroll' ),
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

		update_option(
			self::CACHE_KEY,
			array(
				'results' => $checks,
				'expires' => time() + HOUR_IN_SECONDS,
			)
		);

		return $checks;
	}

	private static function find_page_by_shortcode( string $shortcode ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s AND post_status = 'publish' AND post_type = 'page' LIMIT 1", '%' . $wpdb->esc_like( $shortcode ) . '%' ) );
	}
}
