<?php
/**
 * Google Sheets integration — optional sync per activity.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Google_Sheets {


	public function __construct() {
		$settings = get_option( 'convoca_enroll_settings', array() );
		if ( empty( $settings['sheets_enabled'] ) ) {
			return;
		}

		add_action( 'convoca_enroll_inscripcion_nueva', array( $this, 'on_inscription' ), 20, 2 );
		add_action( 'convoca_inscripcion_confirmada', array( $this, 'on_state_change' ), 20, 2 );
		add_action( 'convoca_inscripcion_cancelada', array( $this, 'on_state_change' ), 20, 2 );
		add_action( 'convoca_inscripcion_promovida', array( $this, 'on_state_change' ), 20, 2 );
	}

	/**
	 * Append row on new inscription.
	 */
	public function on_inscription( int $inscripcion_id, int $actividad_id ): void {
		$sheet_id = get_post_meta( $actividad_id, '_convoca_sheets_id', true );
		if ( empty( $sheet_id ) ) {
			return;
		}

		$m   = fn( $k ) => get_post_meta( $inscripcion_id, '_convoca_' . $k, true );
		$row = array(
			$m( 'nombre' ),
			$m( 'email' ),
			$m( 'telefono' ),
			$m( 'es_socio' ) === '1' ? 'Sí' : 'No',
			CPT_Inscripcion::LABELS[ $m( 'estado' ) ] ?? $m( 'estado' ),
			current_time( 'd/m/Y H:i' ),
		);

		$this->append_row( $sheet_id, $row );
	}

	/**
	 * Update sheet on state change (simplified: log new row).
	 */
	public function on_state_change( int $inscripcion_id, int $actividad_id ): void {
		$sheet_id = get_post_meta( $actividad_id, '_convoca_sheets_id', true );
		if ( empty( $sheet_id ) ) {
			return;
		}

		$m   = fn( $k ) => get_post_meta( $inscripcion_id, '_convoca_' . $k, true );
		$row = array(
			$m( 'nombre' ),
			$m( 'email' ),
			'',
			'',
			'CAMBIO → ' . ( CPT_Inscripcion::LABELS[ $m( 'estado' ) ] ?? $m( 'estado' ) ),
			current_time( 'd/m/Y H:i' ),
		);

		$this->append_row( $sheet_id, $row );
	}

	/**
	 * Append a row to Google Sheets via API v4.
	 */
	private function append_row( string $sheet_id, array $row ): void {
		$settings = get_option( 'convoca_enroll_settings', array() );
		$api_key  = defined( 'CONV_ENROLL_GOOGLE_SHEETS_API_KEY' ) ? CONV_ENROLL_GOOGLE_SHEETS_API_KEY : ( $settings['sheets_api_key'] ?? '' );

		if ( empty( $api_key ) ) {
			return;
		}

		$url = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/A:F:append?valueInputOption=USER_ENTERED',
			urlencode( $sheet_id )
		);

		wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type'   => 'application/json',
					'X-Goog-Api-Key' => $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'values' => array( $row ),
					)
				),
				'timeout' => 10,
			)
		);
	}
}
