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

trait Admin_Settings_Content {

	/**
	 * Pinta la pestaña.
	 *
	 * @param array<string, mixed> $s Datos de la pestaña.
	 */
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

	/**
	 * Pinta la pestaña.
	 *
	 * @param array<string, mixed> $s Datos de la pestaña.
	 */
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

	/**
	 * Pinta la pestaña.
	 *
	 * @param array<string, mixed> $templates Datos de la pestaña.
	 */
	private static function render_tab_emails( array $templates ): void {
		/**
		 * Etiquetas legibles por slug.
		 *
		 * Cubre las 11 plantillas; `?? $slug` deja pasar sin romper cualquier
		 * plantilla que se añada en el futuro sin etiqueta.
		 *
		 * @var array<string, string> $labels
		 */
		$labels = array(
			'recepcion'                      => __( 'Inscripción recibida', 'convoca-enroll' ),
			'lista_espera'                   => __( 'En lista de espera', 'convoca-enroll' ),
			'promocion_lista_espera'         => __( 'Promoción de lista de espera', 'convoca-enroll' ),
			'confirmacion_plaza'             => __( 'Confirmación de plaza', 'convoca-enroll' ),
			'cancelacion_reserva'            => __( 'Cancelación de reserva', 'convoca-enroll' ),
			'recordatorio_7dias'             => __( 'Recordatorio 7 días', 'convoca-enroll' ),
			'recordatorio_24h'               => __( 'Recordatorio 24h', 'convoca-enroll' ),
			'recordatorio_1hora'             => __( 'Recordatorio 1 hora', 'convoca-enroll' ),
			'feedback_post'                  => __( 'Post-evento (feedback)', 'convoca-enroll' ),
			'google_photos_album_creado'     => __( 'Álbum de Google Photos creado', 'convoca-enroll' ),
			'google_photos_album_compartido' => __( 'Álbum de Google Photos compartido', 'convoca-enroll' ),
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

	/**
	 * Pinta la pestaña.
	 *
	 * @param array<string, mixed> $s Datos de la pestaña.
	 */
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
					<p class="description">Próxima ejecución del cron: <strong><?php echo esc_html( (string) $next_run_text ); ?></strong></p>
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
					<p class="description">Página donde has colocado el shortcode <code>[convoca_evaluacion]</code>. Si lo dejas en blanco, se usará el enlace de la propia actividad más el parámetro <code>?evaluar=1</code>.</p>
				</td>
			</tr>
		</table>
		<?php
	}
}
