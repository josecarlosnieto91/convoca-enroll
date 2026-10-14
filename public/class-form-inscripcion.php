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

/**
 * Public form: shortcode [convoca_inscripcion] + render_callback for block.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Form_Inscripcion {


	public function __construct() {
		add_shortcode( 'convoca_form_inscripcion', array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_convoca_inscribir', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_convoca_inscribir', array( $this, 'handle_ajax' ) );
	}

	/**
	 * Shortcode handler.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'              => 0,
				'mostrar_plazas'  => true,
				'mostrar_precios' => true,
			),
			$atts,
			'convoca_inscripcion'
		);

		return $this->render_form( (int) $atts['id'], $atts );
	}

	/**
	 * Render the inscription form.
	 */
	public function render_form( int $actividad_id, array $attrs = array() ): string {
		if ( ! $actividad_id ) {
			return $this->aviso_sin_actividad();
		}

		$actividad = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' ) {
			return $this->aviso_sin_actividad();
		}

		$meta = CPT_Actividad::get_meta( $actividad_id );

		wp_enqueue_style( 'conv-public', CONVOCA_ENROLL_URL . 'assets/css/convoca-enroll-public.css', array(), CONVOCA_ENROLL_VERSION );
		wp_enqueue_script( 'conv-public', CONVOCA_ENROLL_URL . 'assets/js/convoca-enroll-public.js', array( 'convoca-common-js' ), CONVOCA_ENROLL_VERSION, true );
		wp_localize_script(
			'conv-public',
			'bdeEnroll',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'apiRoot' => esc_url_raw( rest_url() ),
				'nonce'   => wp_create_nonce( 'convoca_enroll_inscribir_nonce' ),
			)
		);

		ob_start();
		include CONVOCA_ENROLL_DIR . 'templates/form-inscripcion.php';
		return ob_get_clean();
	}

	/**
	 * Aviso cuando el formulario no apunta a ninguna actividad: dice QUÉ HACER.
	 *
	 * Antes devolvía «Actividad no encontrada.» y, si faltaba el `id`, **nada en absoluto**:
	 * el visitante veía un error que no puede seguir (o una página vacía) y quien lo
	 * administra no sabía que el shortcode estaba mal puesto. Medido en `/inscribete/`
	 * (en la demo y en un sitio de cliente): la página existía y no había forma de inscribirse
 * ni de saber por qué.
	 *
	 * @return string HTML del aviso.
	 */
	protected function aviso_sin_actividad(): string {
		$archivo = get_post_type_archive_link( 'actividad' );
		$salida  = '<div class="convoca-alert convoca-alert--warning" role="status">';
		$salida .= '<p>' . esc_html__( 'Aquí no hay ninguna actividad abierta a inscripción.', 'convoca-enroll' ) . '</p>';

		if ( $archivo ) {
			$salida .= '<p><a class="convoca-btn convoca-btn-primary" href="' . esc_url( $archivo ) . '">' . esc_html__( 'Ver las actividades disponibles', 'convoca-enroll' ) . '</a></p>';
		}

		$salida .= '<p>' . esc_html__( 'Si has llegado por un enlace de la asociación, avísanos: ese enlace está mal.', 'convoca-enroll' ) . '</p>';

		// Quien puede editar ve la causa y el arreglo; el visitante, no.
		if ( current_user_can( 'edit_posts' ) ) {
			$salida .= '<p class="description">' . esc_html__( 'Para quien administra: esta página no tiene actividad asignada. Abre la ficha de la actividad que quieras y pega en ella el bloque o el shortcode con su id, por ejemplo [convoca_form_inscripcion id="123"]. Para listar todas las inscripciones abiertas, usa [convoca_inscripcion_page].', 'convoca-enroll' ) . '</p>';
		}

		return $salida . '</div>';
	}

	/**
	 * AJAX handler.
	 */
	public function handle_ajax(): void {
		check_ajax_referer( 'convoca_enroll_inscribir_nonce', 'nonce' );

		if ( ! \Convoca\Core\Utils::check_rate_limit( 'inscribir', 5, 3600 ) ) {
			wp_send_json_error( array( 'errors' => array( 'Demasiados intentos de inscripción. Inténtalo de nuevo en una hora.' ) ), 429 );
		}

		$post_data = wp_unslash( $_POST );

		$actividad_id        = (int) ( $post_data['actividad_id'] ?? 0 );
		$nombre              = sanitize_text_field( $post_data['nombre'] ?? '' );
		$email               = sanitize_email( $post_data['email'] ?? '' );
		$telefono            = sanitize_text_field( $post_data['telefono'] ?? '' );
		$dni                 = sanitize_text_field( $post_data['dni'] ?? '' );
		$whatsapp            = sanitize_text_field( $post_data['whatsapp'] ?? 'si' );
		$tipo_inscripcion    = sanitize_text_field( $post_data['tipo_inscripcion'] ?? 'general' );
		$consentimiento      = ! empty( $post_data['consentimiento'] );
		$es_menor            = ! empty( $post_data['es_menor'] );
		$nombre_participante = sanitize_text_field( $post_data['nombre_participante'] ?? '' );
		$edad_participante   = absint( $post_data['edad_participante'] ?? 0 );

		// Determine es_socio (legacy).
		$es_socio = ( $tipo_inscripcion === 'socio' );

		// Validate.
		$errors = array();
		if ( ! $actividad_id ) {
			$errors[] = 'Actividad no especificada.';
		}
		if ( empty( $nombre ) ) {
			$errors[] = 'El nombre es obligatorio.';
		}
		if ( empty( $dni ) ) {
			$errors[] = 'El documento de identificación es obligatorio.';
		}
		if ( ! is_email( $email ) ) {
			$errors[] = 'El email no es válido.';
		}
		if ( ! $consentimiento ) {
			$errors[] = 'Debes aceptar la política de privacidad.';
		}
		if ( $es_menor && empty( $nombre_participante ) ) {
			$errors[] = 'El nombre del participante (menor) es obligatorio.';
		}
		if ( $es_menor && $edad_participante <= 0 ) {
			$errors[] = 'La edad del participante es obligatoria.';
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ) );
		}

		// El tipo de inscripción lo decide el SERVIDOR, no el formulario. El motor ya lo corrige
		// —sin sesión de socio, toda inscripción es de «socio de día» (Trasgu)—, pero el importe se
		// calculaba antes con el tipo que venía en la petición: una petición anónima podía pedir
		// «socio» y pagar la tarifa de socio (medido en la pasada 11: 2,50 € en vez de 5,00 €). Se
		// corrige aquí, antes de calcular el importe, con la misma condición que usa el motor.
		$es_socio_real = class_exists( '\\Convoca\\Members\\Member_Auth' ) && \Convoca\Members\Member_Auth::is_authenticated();
		if ( ! $es_socio_real ) {
			$tipo_inscripcion = 'socio_dia';
			$es_socio         = false;
		}

		// Determine price based on tipo_inscripcion.
		$act_meta      = CPT_Actividad::get_meta( $actividad_id );
		$requiere_pago = ! empty( $act_meta['requiere_pago'] );
		$amount_eur    = 0;

		if ( $requiere_pago ) {
			$amount_eur = match ( $tipo_inscripcion ) {
				'socio' => (float) ( $act_meta['precio_socio'] ?? 0 ),
				'socio_dia' => (float) ( $act_meta['precio_socio_dia'] ?? 0 ),
				default => 0,
			};
		}

		$amount_cents   = 0;
		$estado_forzado = null;
		if ( $requiere_pago && $amount_eur > 0 ) {
			$amount_cents   = (int) round( $amount_eur * 100 );
			$estado_forzado = 'pendiente_pago';
		}

		$result = Motor_Inscripcion::inscribir(
			$actividad_id,
			array(
				'nombre'                => $nombre,
				'email'                 => $email,
				'telefono'              => $telefono,
				'dni'                   => $dni,
				'whatsapp'              => $whatsapp,
				'es_socio'              => $es_socio,
				'tipo_inscripcion'      => $tipo_inscripcion,
				'es_menor'              => $es_menor,
				'nombre_participante'   => $nombre_participante,
				'edad_participante'     => $edad_participante,
				'estado_forzado'        => $estado_forzado,
				'rgpd'                  => $consentimiento,
				'compromiso_voluntario' => ! empty( $post_data['compromiso_voluntario'] ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'errors' => array( $result->get_error_message() ) ) );
		}

		// If payment is needed and amount > 0, create payment and redirect.
		if ( $requiere_pago && $amount_eur > 0 ) {
			$actividad = get_post( $actividad_id );

			// 1. Prevenir doble creación de pago por race condition (doble clic)
			$lock_key = 'convoca_enroll_payment_creating_' . $result;
			if ( ! \Convoca\Core\Utils::acquire_lock( $lock_key, 30 ) ) {
				wp_send_json_error( array( 'errors' => array( 'Ya hay un proceso de pago en curso para esta inscripción. Por favor, espera un momento.' ) ), 429 );
			}

			// Set inscription the mount paid to the requested one.
			update_post_meta( $result, '_convoca_importe_pagado', $amount_cents );

			// Check if gateway plugin is available.
			if ( \Convoca\Core\Features::is_gateway_active() ) {
				// 2. Verificar si ya existe un pago asociado que no sea fallido
				$pago_id_existente = (int) get_post_meta( $result, '_convoca_pago_id', true );
				$payment           = null;

				if ( $pago_id_existente ) {
					$pago_status = get_post_meta( $pago_id_existente, '_convoca_status', true );
					if ( $pago_status !== 'failed' ) {
						$payment = array(
							'pago_id'     => $pago_id_existente,
							'payment_url' => \Convoca\Gateway\Payment_Handler::get_payment_link( $pago_id_existente ),
						);
						\Convoca\Core\Logger::info( "Reutilizando pago existente #$pago_id_existente para inscripción #$result.", 'Enroll/Form', $result );
					}
				}

				if ( ! $payment ) {
					$payment = \Convoca\Gateway\Payment_Handler::create_payment(
						array(
							'amount_cents' => $amount_cents,
							'origin'       => 'enroll',
							'origin_id'    => $result,
							// El correo del formulario: con él se envía el recibo y se avisa de la caducidad.
							'payer_email'  => $email,
							'method'       => 'any', // El inscrito elige metodo en la pagina de pago.
							'product_desc' => mb_substr( 'Aportación para ' . ( $actividad->post_title ?? '' ), 0, 125 ),
						)
					);
				}

				delete_transient( $lock_key ); // Compat: limpieza si existiera de versiones previas.
				\Convoca\Core\Utils::release_lock( $lock_key );

				if ( ! is_wp_error( $payment ) ) {
					update_post_meta( $result, '_convoca_pago_id', $payment['pago_id'] );

					$success_data = array(
						'estado'         => 'pendiente_pago',
						'estado_label'   => __( 'Pendiente de aportación', 'convoca-enroll' ),
						'plazas'         => (int) $act_meta['plazas_disponibles'],
						'redirect'       => $payment['payment_url'],
						'codigo_reserva' => get_post_meta( $result, '_convoca_codigo_reserva', true ),
					);

					if ( ! empty( Motor_Inscripcion::$last_pdf_error ) ) {
						$success_data['warning'] = sprintf(
							/* translators: %s: PDF generation error message */
							__( 'La inscripción se ha iniciado, pero hubo un problema al generar el documento de compromiso: %s. Podrás completarlo más tarde.', 'convoca-enroll' ),
							Motor_Inscripcion::$last_pdf_error
						);
					}

					wp_send_json_success( $success_data );
				} else {
					// Error en pasarela: marcar para revisión manual.
					\Convoca\Core\Logger::error( 'Error al crear el pago en la pasarela (Inscripción): ' . $payment->get_error_message(), 'Enroll/Form', $result );
					update_post_meta( $result, '_convoca_needs_manual_review', '1' );
					update_post_meta( $result, '_convoca_review_note', 'Error en pasarela de pago: ' . $payment->get_error_message() );

					// Devolver éxito con flag de error de pasarela para que el usuario vea que se registró.
					wp_send_json_success(
						array(
							'gateway_error'  => true,
							'error_message'  => __( 'La inscripción está registrada pero el pago no se pudo procesar automáticamente. Por favor, contacta con nosotros para completar la aportación.', 'convoca-enroll' ),
							'estado'         => 'pendiente_pago',
							'codigo_reserva' => get_post_meta( $result, '_convoca_codigo_reserva', true ),
							'plazas'         => (int) $act_meta['plazas_disponibles'],
						)
					);
				}
			}
		}

		$estado = get_post_meta( $result, '_convoca_estado', true );

		$success_data = array(
			'estado'         => $estado,
			'estado_label'   => CPT_Inscripcion::labels()[ $estado ] ?? $estado,
			'plazas'         => (int) $act_meta['plazas_disponibles'],
			'codigo_reserva' => get_post_meta( $result, '_convoca_codigo_reserva', true ),
			'redirect'       => add_query_arg( 'enroll_success', '1', home_url() ),
		);

		if ( ! empty( Motor_Inscripcion::$last_pdf_error ) ) {
			$success_data['warning'] = sprintf(
				/* translators: %s: PDF generation error message */
				__( 'Inscripción realizada, pero hubo un problema al generar el documento de compromiso: %s. Por favor, contacta con nosotros si lo necesitas.', 'convoca-enroll' ),
				Motor_Inscripcion::$last_pdf_error
			);
		}

		wp_send_json_success( $success_data );
	}
}
