<?php
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
		add_action( 'wp_ajax_conv_inscribir', array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_conv_inscribir', array( $this, 'handle_ajax' ) );
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
			return '';
		}

		$actividad = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' ) {
			return '<p class="convoca-alert convoca-alert--danger">Actividad no encontrada.</p>';
		}

		$meta = CPT_Actividad::get_meta( $actividad_id );

		wp_enqueue_style( 'bde-public', CONV_ENROLL_URL . 'assets/css/convoca-enroll-public.css', array(), CONV_ENROLL_VERSION );
		wp_enqueue_script( 'bde-public', CONV_ENROLL_URL . 'assets/js/convoca-enroll-public.js', array( 'convoca-common-js' ), CONV_ENROLL_VERSION, true );
		wp_localize_script(
			'bde-public',
			'bdeEnroll',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'apiRoot' => esc_url_raw( rest_url() ),
				'nonce'   => wp_create_nonce( 'conv_enroll_inscribir_nonce' ),
			)
		);

		ob_start();
		include CONV_ENROLL_DIR . 'templates/form-inscripcion.php';
		return ob_get_clean();
	}

	/**
	 * AJAX handler.
	 */
	public function handle_ajax(): void {
		check_ajax_referer( 'conv_enroll_inscribir_nonce', 'nonce' );

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
			$lock_key = 'conv_enroll_payment_creating_' . $result;
			if ( get_transient( $lock_key ) ) {
				wp_send_json_error( array( 'errors' => array( 'Ya hay un proceso de pago en curso para esta inscripción. Por favor, espera un momento.' ) ), 429 );
				return;
			}
			set_transient( $lock_key, true, 30 );

			// Set inscription the mount paid to the requested one.
			update_post_meta( $result, '_conv_importe_pagado', $amount_cents );

			// Check if gateway plugin is available.
			if ( \Convoca\Core\Features::is_gateway_active() ) {
				// 2. Verificar si ya existe un pago asociado que no sea fallido
				$pago_id_existente = (int) get_post_meta( $result, '_conv_pago_id', true );
				$payment           = null;

				if ( $pago_id_existente ) {
					$pago_status = get_post_meta( $pago_id_existente, '_bdg_status', true );
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
							'product_desc' => mb_substr( 'Aportación para ' . ( $actividad->post_title ?? '' ), 0, 125 ),
						)
					);
				}

				delete_transient( $lock_key );

				if ( ! is_wp_error( $payment ) ) {
					update_post_meta( $result, '_conv_pago_id', $payment['pago_id'] );

					$success_data = array(
						'estado'         => 'pendiente_pago',
						'estado_label'   => 'Pendiente de aportación',
						'plazas'         => (int) $act_meta['plazas_disponibles'],
						'redirect'       => $payment['payment_url'],
						'codigo_reserva' => get_post_meta( $result, '_conv_codigo_reserva', true ),
					);

					if ( ! empty( Motor_Inscripcion::$last_pdf_error ) ) {
						$success_data['warning'] = sprintf(
							__( 'La inscripción se ha iniciado, pero hubo un problema al generar el documento de compromiso: %s. Podrás completarlo más tarde.', 'convoca-enroll' ),
							Motor_Inscripcion::$last_pdf_error
						);
					}

					wp_send_json_success( $success_data );
				} else {
					// Error en pasarela: marcar para revisión manual.
					\Convoca\Core\Logger::error( 'Error al crear el pago en la pasarela (Inscripción): ' . $payment->get_error_message(), 'Enroll/Form', $result );
					update_post_meta( $result, '_conv_needs_manual_review', '1' );
					update_post_meta( $result, '_conv_review_note', 'Error en pasarela de pago: ' . $payment->get_error_message() );

					// Devolver éxito con flag de error de pasarela para que el usuario vea que se registró.
					wp_send_json_success(
						array(
							'gateway_error'  => true,
							'error_message'  => 'La inscripción está registrada pero el pago no se pudo procesar automáticamente. Por favor, contacta con nosotros para completar la aportación.',
							'estado'         => 'pendiente_pago',
							'codigo_reserva' => get_post_meta( $result, '_conv_codigo_reserva', true ),
							'plazas'         => (int) $act_meta['plazas_disponibles'],
						)
					);
				}
			}
		}

		$estado = get_post_meta( $result, '_conv_estado', true );

		$success_data = array(
			'estado'         => $estado,
			'estado_label'   => CPT_Inscripcion::LABELS[ $estado ] ?? $estado,
			'plazas'         => (int) $act_meta['plazas_disponibles'],
			'codigo_reserva' => get_post_meta( $result, '_conv_codigo_reserva', true ),
			'redirect'       => add_query_arg( 'enroll_success', '1', home_url() ),
		);

		if ( ! empty( Motor_Inscripcion::$last_pdf_error ) ) {
			$success_data['warning'] = sprintf(
				__( 'Inscripción realizada, pero hubo un problema al generar el documento de compromiso: %s. Por favor, contacta con nosotros si lo necesitas.', 'convoca-enroll' ),
				Motor_Inscripcion::$last_pdf_error
			);
		}

		wp_send_json_success( $success_data );
	}
}
