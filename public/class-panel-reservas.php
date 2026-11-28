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
 * Public user panel: check and cancel reservations via email + code.
 *
 * Shortcode: [convoca_panel_reservas]
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Panel_Reservas {


	public function __construct() {
		add_shortcode( 'convoca_panel_reservas', array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_convoca_panel_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_convoca_panel_login', array( $this, 'ajax_login' ) );
		add_action( 'wp_ajax_convoca_panel_cancelar', array( $this, 'ajax_cancelar' ) );
		add_action( 'wp_ajax_nopriv_convoca_panel_cancelar', array( $this, 'ajax_cancelar' ) );
	}

	/* ── Shortcode ─────────────────────────────── */

	public function shortcode( mixed $atts ): string {
		wp_enqueue_style( 'conv-panel', CONVOCA_ENROLL_URL . 'assets/css/convoca-enroll-panel.css', array( 'convoca-core' ), CONVOCA_ENROLL_VERSION );
		wp_enqueue_script( 'conv-panel', CONVOCA_ENROLL_URL . 'assets/js/convoca-enroll-panel.js', array( 'convoca-common-js' ), CONVOCA_ENROLL_VERSION, true );
		wp_localize_script(
			'conv-panel',
			'bdePanel',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'convoca_enroll_panel_nonce' ),
			)
		);

		ob_start();
		include CONVOCA_ENROLL_DIR . 'templates/panel-reservas.php';
		return (string) ob_get_clean();
	}

	/* ── AJAX: Login (email + code) ────────────── */

	public function ajax_login(): void {
		check_ajax_referer( 'convoca_enroll_panel_nonce', 'nonce' );

		if ( ! \Convoca\Core\Utils::check_rate_limit( 'panel_login', 10, 600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Demasiados intentos. Inténtalo de nuevo en 10 minutos.', 'convoca-enroll' ) ), 429 );
		}

		$email  = sanitize_email( $_POST['email'] ?? '' );
		$codigo = strtoupper( sanitize_text_field( $_POST['codigo'] ?? '' ) );

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Introduce un email válido.', 'convoca-enroll' ) ) );
		}

		if ( empty( $codigo ) ) {
			wp_send_json_error( array( 'message' => __( 'Introduce tu código de reserva.', 'convoca-enroll' ) ) );
		}

		// Check if email + code match any inscription.
		$match = Motor_Inscripcion::buscar_por_codigo( $email, $codigo );
		if ( ! $match ) {
			wp_send_json_error( array( 'message' => __( 'No se encontró ninguna reserva con ese email y código. Revisa los datos e inténtalo de nuevo.', 'convoca-enroll' ) ) );
		}

		// Get ALL reservations for this email.
		$reservas = $this->get_reservas_formateadas( $email );

		wp_send_json_success(
			array(
				'reservas' => $reservas,
				'email'    => $email,
			)
		);
	}

	/* ── AJAX: Cancel reservation ─────────────── */

	public function ajax_cancelar(): void {
		check_ajax_referer( 'convoca_enroll_panel_nonce', 'nonce' );

		if ( ! \Convoca\Core\Utils::check_rate_limit( 'panel_cancelar', 5, 3600 ) ) {
			wp_send_json_error( array( 'message' => __( 'Demasiados intentos de cancelación. Inténtalo de nuevo en una hora.', 'convoca-enroll' ) ), 429 );
		}

		$email          = sanitize_email( $_POST['email'] ?? '' );
		$codigo         = strtoupper( sanitize_text_field( $_POST['codigo'] ?? '' ) );
		$inscripcion_id = (int) ( $_POST['inscripcion_id'] ?? 0 );

		if ( ! is_email( $email ) || empty( $codigo ) ) {
			wp_send_json_error( array( 'message' => __( 'Datos de sesión no válidos.', 'convoca-enroll' ) ) );
		}

		// Verify that the email owns this inscription AND the code matches.
		$email_inscripcion  = CPT_Inscripcion::get_meta( $inscripcion_id, 'email' );
		$codigo_inscripcion = strtoupper( CPT_Inscripcion::get_meta( $inscripcion_id, 'codigo_reserva' ) );

		if ( strtolower( $email_inscripcion ) !== strtolower( $email ) || $codigo_inscripcion !== $codigo ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permiso para cancelar esta reserva o el código es incorrecto.', 'convoca-enroll' ) ) );
		}

		// Verify inscription exists and is not already cancelled.
		$estado = CPT_Inscripcion::get_meta( $inscripcion_id, 'estado' );
		if ( $estado === 'cancelada' ) {
			wp_send_json_error( array( 'message' => __( 'Esta reserva ya está cancelada.', 'convoca-enroll' ) ) );
		}

		// Cancel.
		$result = Motor_Inscripcion::cancelar( $inscripcion_id, __( 'Cancelada por el usuario desde el panel de reservas.', 'convoca-enroll' ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Return updated list.
		$reservas = $this->get_reservas_formateadas( $email );

		wp_send_json_success(
			array(
				'reservas' => $reservas,
				'message'  => __( 'Tu reserva ha sido cancelada correctamente. Recibirás un email de confirmación.', 'convoca-enroll' ),
			)
		);
	}

	/* ── Helpers ───────────────────────────────── */

	/**
	 * Reservas listas para pintar.
	 *
	 * @return array<int, array<string, mixed>> mixed>
	 */
	private function get_reservas_formateadas( string $email ): array {
		// Get ALL reservations for this email (including cancelled).
		$posts = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'email',
						'value' => sanitize_email( $email ),
					),
				),
			)
		);

		$settings = get_option( 'convoca_enroll_settings', array() );
		$reservas = array();
		foreach ( $posts as $post ) {
			$m          = fn( $k ) => CPT_Inscripcion::get_meta( $post->ID, $k );
			$act_id     = (int) $m( 'actividad_id' );
			$act        = get_post( $act_id );
			$fecha_raw  = $act ? CPT_Actividad::get_meta_value( $act_id, 'fecha_inicio' ) : '';
			$fecha      = $fecha_raw ? \Convoca\Core\Utils::format_date( $fecha_raw, 'd/m/Y' ) : '—';
			$hora       = $fecha_raw ? \Convoca\Core\Utils::format_date( $fecha_raw, 'H:i' ) : '—';
			$estado     = $m( 'estado' );
			$es_menor   = $m( 'es_menor' ) === '1';
			$cancelable = self::puede_cancelar( (string) $estado, (string) $fecha_raw, $settings );

			$reservas[] = array(
				'id'                 => $post->ID,
				'actividad'          => $act ? $act->post_title : __( 'Actividad no disponible', 'convoca-enroll' ),
				'fecha'              => $fecha,
				'hora'               => $hora,
				'ubicacion'          => $act ? ( CPT_Actividad::get_meta_value( $act_id, 'ubicacion' ) ?: '—' ) : '—',
				'estado'             => $estado,
				'estado_label'       => CPT_Inscripcion::labels()[ $estado ] ?? $estado,
				'estado_class'       => CPT_Inscripcion::BADGE_CLASSES[ $estado ] ?? 'convoca-badge',
				'codigo'             => $m( 'codigo_reserva' ),
				'es_menor'           => $es_menor,
				'participante'       => $es_menor ? $m( 'nombre_participante' ) : $m( 'nombre' ),
				'cancelable'         => $cancelable[0],
				'payment_url'        => self::enlace_pago( $post->ID ),
				'motivo_cancelacion' => $cancelable[1],
				'fecha_inscripcion'  => get_the_date( 'd/m/Y H:i', $post ),
			);
		}

		return $reservas;
	}

	/**
	 * URL de pago de la reserva, si tiene un pago pendiente y el gateway está disponible.
	 *
	 * @param int $inscripcion_id Inscripción.
	 * @return string
	 */
	private static function enlace_pago( int $inscripcion_id ): string {
		$pago_id = (int) CPT_Inscripcion::get_meta( $inscripcion_id, 'pago_id' );
		if ( $pago_id <= 0 || ! class_exists( '\\Convoca\\Gateway\\Payment_Handler' ) ) {
			return '';
		}

		return (string) \Convoca\Gateway\Payment_Handler::build_payment_url( $pago_id );
	}

	/**
	 * Misma ventana que aplica `Motor_Inscripcion::cancelar()`: el botón no promete lo que el motor rechaza.
	 *
	 * @param string $estado      Estado de la inscripción.
	 * @param string $fecha_inicio Fecha de la actividad.
	 * @param array<string, mixed> $settings Ajustes de Enroll.
	 * @return array{0: bool, 1: string} Puede cancelar y motivo si no.
	 */
	private static function puede_cancelar( string $estado, string $fecha_inicio, array $settings ): array {
		if ( 'cancelada' === $estado ) {
			return array( false, __( 'Reserva cancelada', 'convoca-enroll' ) );
		}

		$privilegiado = current_user_can( 'manage_options' ) || current_user_can( 'convoca_manage_enroll' );
		if ( $privilegiado ) {
			return array( true, '' );
		}

		$window_hours = absint( $settings['cancel_window_hours'] ?? 24 );
		if ( Motor_Inscripcion::es_cancelacion_tardia( $fecha_inicio, $window_hours, time() ) ) {
			/* translators: %d: horas de antelación exigidas. */
			return array( false, sprintf( __( 'Fuera del plazo de cancelación (%d h antes)', 'convoca-enroll' ), $window_hours ) );
		}

		return array( true, '' );
	}
}
