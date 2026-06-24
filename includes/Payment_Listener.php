<?php
/**
 * Listens for convoca_payment_completed to confirm enroll inscriptions.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payment_Listener {


	private const TRANSIENT_KEY = 'convoca_enroll_payment_processed_';

	public function __construct() {
		// Only listen to the new unified hook to avoid duplication.
		add_action( 'convoca_gateway_payment_completed', array( $this, 'on_payment_completed' ), 10, 5 );
	}

	/**
	 * Handle successful payment from the gateway.
	 * Uses transient to prevent duplicate processing.
	 *
	 * @param int    $pago_id    Payment post ID.
	 * @param string $origin     Origin plugin key.
	 * @param int    $origin_id  Post ID of the inscription.
	 * @param array  $meta       Payment metadata.
	 */
	public function on_payment_completed( $pago_id, $origin, $origin_id, $meta ): void {
		// Deduplication: check if this payment was already processed.
		$transient_key = self::TRANSIENT_KEY . $pago_id;
		if ( ! \Convoca\Core\Utils::acquire_lock( $transient_key, 60 ) ) {
			\Convoca\Core\Logger::info( "Payment $pago_id already processed, skipping (deduplicated)", 'Enroll/Payment' );
			return;
		}

		// Only handle enroll payments.
		if ( $origin !== 'enroll' ) {
			return;
		}

		$inscripcion = get_post( $origin_id );
		if ( ! $inscripcion || $inscripcion->post_type !== 'inscripcion' ) {
			\Convoca\Core\Logger::warning( "Pago $pago_id completado pero inscripción #$origin_id no existe o fue eliminada.", 'Enroll/Payment', $origin_id );
			return;
		}

		$current_estado = get_post_meta( $origin_id, '_convoca_estado', true );
		$last_pago_id   = (int) get_post_meta( $origin_id, '_convoca_pago_id', true );

		if ( $current_estado !== 'pendiente_pago' || $last_pago_id === (int) $pago_id ) {
			\Convoca\Core\Logger::info( "Payment $pago_id skipped - already processed or state is $current_estado", 'Enroll/Payment', $origin_id );
			return;
		}

		// Set transient to prevent duplicate processing.
		set_transient( $transient_key, 1, HOUR_IN_SECONDS );

		// Update payment info before confirming.
		update_post_meta( $origin_id, '_convoca_metodo_pago', $meta['method'] ?? '' );
		update_post_meta( $origin_id, '_convoca_pago_id', $pago_id );

		// Confirm the inscription (this handles capacity decrement).
		$result = Motor_Inscripcion::confirmar( $origin_id );

		if ( is_wp_error( $result ) ) {
			// This could happen if capacity was exhausted while the user was paying.
			// Log for admin review but don't leave in inconsistent state.
			\Convoca\Core\Logger::error( "Enroll confirmation failed after payment (Pago: $pago_id, Inscripción: $origin_id): " . $result->get_error_message(), 'Enroll/Payment', $origin_id );

			// Mark as needs manual review.
			update_post_meta( $origin_id, '_convoca_needs_manual_review', '1' );
			update_post_meta( $origin_id, '_convoca_review_note', 'Error en confirmación automática tras pago: ' . $result->get_error_message() );
		}
	}
}
