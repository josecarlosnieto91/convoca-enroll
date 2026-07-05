<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Public
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Formulario_Evaluacion {

	public static function init() {
		add_shortcode( 'convoca_evaluacion', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_convoca_submit_evaluacion', array( __CLASS__, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_convoca_submit_evaluacion', array( __CLASS__, 'handle_submission' ) );

		add_action( 'wp_ajax_convoca_eval_get_nonce', array( __CLASS__, 'get_nonce' ) );
		add_action( 'wp_ajax_nopriv_convoca_eval_get_nonce', array( __CLASS__, 'get_nonce' ) );
	}

	public static function get_nonce() {
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'convoca_eval_get_nonce', 30, 3600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Demasiadas peticiones.', 'convoca-enroll' ) ), 429 );
		}
		wp_send_json_success( wp_create_nonce( 'convoca_evaluacion_nonce' ) );
	}

	public static function enqueue_assets() {
		// Enqueued only when shortcode is present, but doing it globally is safer for blocks.
		wp_register_style( 'conv-evaluacion-css', plugins_url( 'assets/css/evaluacion.css', __DIR__ ), array(), '1.0.0' );
		wp_register_script( 'conv-evaluacion-js', plugins_url( 'assets/js/star-rating.js', __DIR__ ), array( 'jquery' ), '1.0.0', true );

		wp_localize_script(
			'conv-evaluacion-js',
			'convoca_eval_ajax',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'convoca_evaluacion_nonce' ),
			)
		);
	}

	public static function render_shortcode( $atts ) {
		wp_enqueue_style( 'conv-evaluacion-css' );
		wp_enqueue_script( 'conv-evaluacion-js' );

		$atts = shortcode_atts(
			array(
				'actividad_id' => 0,
			),
			$atts
		);

		$actividad_id = intval( $atts['actividad_id'] );
		$actividad    = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' || $actividad->post_status !== 'publish' ) {
			return '<p>' . esc_html__( 'ID de actividad no válido o inexistente.', 'convoca-enroll' ) . '</p>';
		}

		$fecha_fin = get_post_meta( $actividad_id, '_convoca_fecha_fin', true );
		if ( ! $fecha_fin || strtotime( $fecha_fin ) > current_time( 'timestamp' ) ) {
			return '<p>' . esc_html__( 'Esta actividad aún no ha finalizado y no puede ser evaluada.', 'convoca-enroll' ) . '</p>';
		}

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Debes iniciar sesión para evaluar esta actividad.', 'convoca-enroll' ) . '</p>';
		}

		$user = wp_get_current_user();

		// 1. Check if user already evaluated
		$existing = get_posts(
			array(
				'post_type'      => 'convoca_evaluacion',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_eval_actividad_id',
						'value' => $actividad_id,
					),
					array(
						'key'   => '_convoca_eval_usuario_id',
						'value' => $user->ID,
					),
				),
				'posts_per_page' => 1,
			)
		);

		if ( ! empty( $existing ) ) {
			return '<div class="conv-eval-notice">' . esc_html__( 'Ya has enviado una evaluación para esta actividad. ¡Gracias!', 'convoca-enroll' ) . '</div>';
		}

		// 2. Check permissions
		$can_evaluate = false;

		if ( current_user_can( 'manage_options' ) || current_user_can( 'convoca_manage_evaluations' ) ) {
			$can_evaluate = true;
		} else {
			// Is monitor?
			$is_monitor       = in_array( 'monitor_actividad', (array) $user->roles );
			$responsables_raw = get_post_meta( $actividad_id, '_convoca_responsables', true );
			$responsables     = is_array( $responsables_raw ) ? $responsables_raw : explode( ',', (string) $responsables_raw );
			if ( in_array( $user->ID, array_map( 'intval', $responsables ) ) ) {
				$is_monitor = true;
			}

			if ( $is_monitor ) {
				$can_evaluate = true;
			} else {
				// Is volunteer?
				$is_volunteer = in_array( 'voluntario_aprobado', (array) $user->roles ) || current_user_can( 'gestionar_mis_turnos' );
				if ( $is_volunteer ) {
					// Did attend?
					$inscriptions = get_posts(
						array(
							'post_type'      => 'inscripcion',
							'posts_per_page' => 1,
							'meta_query'     => array(
								array(
									'key'   => '_convoca_actividad_id',
									'value' => $actividad_id,
								),
								array(
									'key'   => '_convoca_user_id',
									'value' => $user->ID,
								),
							),
						)
					);
					if ( ! empty( $inscriptions ) ) {
						$asistencia = get_post_meta( $inscriptions[0]->ID, '_convoca_asistencia', true );
						if ( $asistencia === 'si' ) {
							$can_evaluate = true;
						}
					}
				}
			}
		}

		if ( ! $can_evaluate ) {
			return '<p>' . esc_html__( 'No tienes permisos para evaluar esta actividad o no consta tu asistencia.', 'convoca-enroll' ) . '</p>';
		}

		ob_start();
		?>
		<div class="conv-evaluacion-container">
			<h3><?php printf( esc_html__( 'Evaluar Actividad: %s', 'convoca-enroll' ), esc_html( get_the_title( $actividad_id ) ) ); ?></h3>
			<form id="conv-evaluacion-form" method="post">
				<input type="hidden" name="actividad_id" value="<?php echo esc_attr( $actividad_id ); ?>">
				<input type="hidden" name="action" value="convoca_submit_evaluacion">
				<?php wp_nonce_field( 'convoca_evaluacion_nonce', 'security' ); ?>

				<div class="conv-eval-section">
					<h4><?php esc_html_e( '1. Valoraciones numéricas', 'convoca-enroll' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Valora del 1 al 5 (1 = Muy insatisfecho, 5 = Muy satisfecho)', 'convoca-enroll' ); ?></p>
					
					<?php self::render_star_input( 'gestion', __( 'Gestión y coordinación', 'convoca-enroll' ) ); ?>
					<?php self::render_star_input( 'instalaciones', __( 'Instalaciones / Espacio', 'convoca-enroll' ) ); ?>
					<?php self::render_star_input( 'participantes', __( 'Grupo participante', 'convoca-enroll' ) ); ?>
					<?php self::render_star_input( 'comunicacion', __( 'Comunicación con el equipo', 'convoca-enroll' ) ); ?>
				</div>

				<div class="conv-eval-section">
					<h4><?php esc_html_e( '2. Comentarios y sugerencias', 'convoca-enroll' ); ?></h4>
					
					<div class="conv-form-group">
						<label for="comentarios_gestion"><?php esc_html_e( 'Cuéntanos brevemente tu experiencia', 'convoca-enroll' ); ?></label>
						<textarea id="comentarios_gestion" name="comentarios_gestion" rows="3"></textarea>
					</div>

					<div class="conv-form-group">
						<label for="necesidades_no_cubiertas"><?php _e( '¿Has necesitado algo que no te hayan facilitado?', 'convoca-enroll' ); ?></label>
						<textarea id="necesidades_no_cubiertas" name="necesidades_no_cubiertas" rows="2"></textarea>
					</div>

					<div class="conv-form-group">
						<label for="mejoras_gestion"><?php _e( '¿Qué mejorarías en la gestión?', 'convoca-enroll' ); ?></label>
						<textarea id="mejoras_gestion" name="mejoras_gestion" rows="2"></textarea>
					</div>

					<div class="conv-form-group">
						<label for="mejoras_instalaciones"><?php _e( '¿Qué mejorarías del espacio/instalaciones?', 'convoca-enroll' ); ?></label>
						<textarea id="mejoras_instalaciones" name="mejoras_instalaciones" rows="2"></textarea>
					</div>

					<div class="conv-form-group">
						<label for="comentarios_participantes"><?php _e( 'Comentarios sobre el público o dinámica', 'convoca-enroll' ); ?></label>
						<textarea id="comentarios_participantes" name="comentarios_participantes" rows="2"></textarea>
					</div>

					<div class="conv-form-group">
						<label for="aspectos_positivos"><?php _e( 'Aspectos positivos (lo mejor de la actividad)', 'convoca-enroll' ); ?></label>
						<textarea id="aspectos_positivos" name="aspectos_positivos" rows="2"></textarea>
					</div>

					<div class="conv-form-group">
						<label for="aspectos_mejorar"><?php _e( 'Aspectos a mejorar', 'convoca-enroll' ); ?></label>
						<textarea id="aspectos_mejorar" name="aspectos_mejorar" rows="2"></textarea>
					</div>

					<div class="conv-form-group">
						<label for="otros_comentarios"><?php _e( 'Otros comentarios o sugerencias', 'convoca-enroll' ); ?></label>
						<textarea id="otros_comentarios" name="otros_comentarios" rows="2"></textarea>
					</div>
				</div>

				<div class="conv-eval-section conv-privacy-section">
					<label>
						<input type="checkbox" name="privacy_consent" required>
						<?php printf( esc_html__( 'Acepto que mi evaluación sea tratada según la política de privacidad de %s. Los datos se utilizarán internamente para mejorar las actividades.', 'convoca-enroll' ), esc_html( get_bloginfo( 'name' ) ) ); ?>
					</label>
				</div>

				<div class="conv-form-submit">
					<button type="submit" class="button button-primary wp-element-button"><?php esc_html_e( 'Enviar Evaluación', 'convoca-enroll' ); ?></button>
				</div>
				<div id="conv-evaluacion-response"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_star_input( $field_id, $label ) {
		?>
		<div class="conv-star-rating-group">
			<label><?php echo esc_html( $label ); ?></label>
			<div class="star-rating" data-field="<?php echo esc_attr( $field_id ); ?>">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<span class="star" data-val="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( $i ); ?> estrellas" role="button" tabindex="0">☆</span>
				<?php endfor; ?>
				<input type="hidden" name="<?php echo esc_attr( $field_id ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="0" required>
			</div>
		</div>
		<?php
	}

	public static function handle_submission() {
		check_ajax_referer( 'convoca_evaluacion_nonce', 'security' );

		if ( ! \Convoca\Core\Utils::check_rate_limit( 'convoca_submit_evaluacion', 10, 3600 ) ) {
			wp_send_json_error( __( 'Demasiados intentos. Inténtalo de nuevo más tarde.', 'convoca-enroll' ), 429 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'Debes iniciar sesión.', 'convoca-enroll' ) );
		}

		$user         = wp_get_current_user();
		$actividad_id = isset( $_POST['actividad_id'] ) ? intval( $_POST['actividad_id'] ) : 0;

		if ( ! $actividad_id ) {
			wp_send_json_error( __( 'Actividad no válida.', 'convoca-enroll' ) );
		}

		$actividad = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' || $actividad->post_status !== 'publish' ) {
			wp_send_json_error( __( 'ID de actividad no válido o inexistente.', 'convoca-enroll' ) );
		}

		$fecha_fin = get_post_meta( $actividad_id, '_convoca_fecha_fin', true );
		if ( ! $fecha_fin || strtotime( $fecha_fin ) > current_time( 'timestamp' ) ) {
			wp_send_json_error( __( 'Esta actividad aún no ha finalizado y no puede ser evaluada.', 'convoca-enroll' ) );
		}

		// Check if already evaluated.
		$existing = get_posts(
			array(
				'post_type'      => 'convoca_evaluacion',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_eval_actividad_id',
						'value' => $actividad_id,
					),
					array(
						'key'   => '_convoca_eval_usuario_id',
						'value' => $user->ID,
					),
				),
				'posts_per_page' => 1,
			)
		);

		if ( ! empty( $existing ) ) {
			wp_send_json_error( __( 'Ya has enviado una evaluación para esta actividad.', 'convoca-enroll' ) );
		}

		// Lock to prevent race conditions.
		$lock_key = 'convoca_eval_lock_' . $user->ID . '_' . $actividad_id;
		if ( get_transient( $lock_key ) ) {
			wp_send_json_error( __( 'Estamos procesando tu solicitud, por favor espera.', 'convoca-enroll' ) );
		}
		set_transient( $lock_key, true, 30 );

		$numeric_fields = array( 'gestion', 'instalaciones', 'participantes', 'comunicacion' );
		$ratings        = array();
		foreach ( $numeric_fields as $field ) {
			$val = isset( $_POST[ $field ] ) ? intval( $_POST[ $field ] ) : 0;
			if ( $val < 1 || $val > 5 ) {
				delete_transient( $lock_key );
				wp_send_json_error( __( 'Por favor, completa todas las valoraciones numéricas (de 1 a 5 estrellas).', 'convoca-enroll' ) );
			}
			$ratings[ $field ] = $val;
		}

		$title = sprintf( 'Evaluación de %s por %s', get_the_title( $actividad_id ), $user->display_name );

		$post_data = array(
			'post_title'  => wp_strip_all_tags( $title ),
			'post_status' => 'publish',
			'post_type'   => 'convoca_evaluacion',
		);

		$eval_id = wp_insert_post( $post_data );

		if ( is_wp_error( $eval_id ) ) {
			delete_transient( $lock_key );
			wp_send_json_error( __( 'Error al guardar la evaluación.', 'convoca-enroll' ) );
		}

		// Save metadata.
		update_post_meta( $eval_id, '_convoca_eval_actividad_id', $actividad_id );
		update_post_meta( $eval_id, '_convoca_eval_usuario_id', $user->ID );
		update_post_meta( $eval_id, '_convoca_eval_fecha', current_time( 'mysql' ) );

		foreach ( $ratings as $field => $val ) {
			update_post_meta( $eval_id, '_convoca_eval_' . $field, $val );
		}

		$text_fields = array(
			'comentarios_gestion',
			'necesidades_no_cubiertas',
			'mejoras_gestion',
			'mejoras_instalaciones',
			'comentarios_participantes',
			'aspectos_positivos',
			'aspectos_mejorar',
			'otros_comentarios',
		);
		foreach ( $text_fields as $field ) {
			$val = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_post_meta( $eval_id, '_convoca_eval_' . $field, $val );
		}

		// Hook for integrations.
		do_action( 'convoca_evaluacion_completada', $eval_id, $actividad_id, $user->ID );

		wp_send_json_success( __( 'Evaluación enviada con éxito. ¡Gracias por tu colaboración!', 'convoca-enroll' ) );
	}
}
