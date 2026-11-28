<?php
/**
 * Pago de una promoción desde la lista de espera.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea el pago de una promoción cuando la actividad es de pago.
 */
class Promocion_Pago {

	/**
	 * Crea el pago si hay gateway y la inscripción quedó pendiente de pago.
	 *
	 * @param int    $inscripcion_id Inscripción promovida.
	 * @param int    $actividad_id   Actividad.
	 * @param string $estado         Estado tras la promoción.
	 * @return void
	 */
	public static function crear( int $inscripcion_id, int $actividad_id, string $estado ): void {
		if ( 'pendiente_pago' !== $estado ) {
			return;
		}
		if ( ! class_exists( '\\Convoca\\Core\\Features' ) || ! \Convoca\Core\Features::is_gateway_active() || ! class_exists( '\\Convoca\\Gateway\\Payment_Handler' ) ) {
			return;
		}
		if ( (int) CPT_Inscripcion::get_meta( $inscripcion_id, 'pago_id' ) > 0 ) {
			return;
		}

		$meta_actividad = CPT_Actividad::get_meta( $actividad_id );
		$tipo           = (string) CPT_Inscripcion::get_meta( $inscripcion_id, 'tipo_inscripcion' );
		$precio         = match ( $tipo ) {
			'socio'     => (float) ( $meta_actividad['precio_socio'] ?? 0 ),
			'socio_dia' => (float) ( $meta_actividad['precio_socio_dia'] ?? 0 ),
			default     => (float) ( $meta_actividad['precio_general'] ?? 0 ),
		};
		$importe = (int) round( $precio * 100 );
		if ( $importe <= 0 ) {
			return;
		}

		$pago = \Convoca\Gateway\Payment_Handler::create_payment(
			array(
				'amount_cents' => $importe,
				'origin'       => 'enroll',
				'origin_id'    => $inscripcion_id,
				'payer_email'  => (string) CPT_Inscripcion::get_meta( $inscripcion_id, 'email' ),
				'method'       => 'any',
				'product_desc' => mb_substr( 'Aportación para ' . get_the_title( $actividad_id ), 0, 125 ),
			)
		);

		if ( is_wp_error( $pago ) || empty( $pago['pago_id'] ) ) {
			\Convoca\Core\Logger::warning( 'No se pudo crear el pago de la promoción #' . $inscripcion_id, 'Enroll/Motor', $inscripcion_id );
			return;
		}

		CPT_Inscripcion::update_meta( $inscripcion_id, 'pago_id', (int) $pago['pago_id'] );
		CPT_Inscripcion::update_meta( $inscripcion_id, 'importe_pagado', $importe );
	}
}
