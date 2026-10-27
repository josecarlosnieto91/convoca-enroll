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
 * Pestañas de ajustes extraídas de Admin_Settings.
 *
 * Vive en un trait para partir la clase sin cambiar ni una línea del HTML ni de la lógica: el
 * código se ha movido tal cual, y `self::` sigue apuntando a Admin_Settings.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Admin_Settings_General {

	/**
	 * Pinta la pestaña.
	 *
	 * @param array<string, mixed> $s Datos de la pestaña.
	 */
	private static function render_tab_general( array $s ): void {
		?>
		<h2><?php esc_html_e( 'Configuración General', 'convoca-enroll' ); ?></h2>

		<div class="convoca-field">
			<label for="admin_email"><?php esc_html_e( 'Email del administrador', 'convoca-enroll' ); ?></label>
			<input type="email" id="admin_email" name="conv[admin_email]"
				value="<?php echo esc_attr( $s['admin_email'] ?? '' ); ?>">
		</div>

		<div class="convoca-field">
			<label for="admin_email_domains"><?php esc_html_e( 'Dominios de correo del equipo', 'convoca-enroll' ); ?></label>
			<input type="text" id="admin_email_domains" name="conv[admin_email_domains]"
				value="<?php echo esc_attr( $s['admin_email_domains'] ?? '' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Separados por «|». Las plantillas que nombran un correo de estos dominios lo sustituyen por el email configurado arriba. Vacío = el dominio de ese email.', 'convoca-enroll' ); ?></small>
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
			<label for="cancel_window_hours"><?php esc_html_e( 'Ventana de cancelación (horas)', 'convoca-enroll' ); ?></label>
			<input type="number" id="cancel_window_hours" name="conv[cancel_window_hours]" min="0"
				value="<?php echo esc_attr( $s['cancel_window_hours'] ?? '24' ); ?>">
			<small class="convoca-small"><?php esc_html_e( 'Horas mínimas antes del inicio para que un participante pueda cancelar por autoservicio. A menos horas solo podrá cancelar la organización.', 'convoca-enroll' ); ?></small>
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
}
