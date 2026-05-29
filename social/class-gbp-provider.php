<?php
/**
 * Google Business Profile Provider — Local Posts publishing.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GBP_Provider implements Social_Provider_Interface {

	private array $account;
	private string $access_token;
	private string $refresh_token;
	private string $location_id;

	public function __construct( int $account_id ) {
		$data = Social_OAuth::get_token( $account_id );
		$this->account       = $data ?: array();
		$this->access_token  = $this->account['access_token'] ?? '';
		$this->refresh_token = $this->account['refresh_token'] ?? '';
		$this->location_id   = $this->account['account_id'] ?? '';
	}

	public function authenticate( string $auth_code ): array {
		return array( 'success' => false, 'message' => 'Usa los endpoints REST /social/auth/google' );
	}

	/**
	 * Publish a Local Post to Google Business Profile.
	 */
	public function publish( string $message, string $image_url = '', string $link_url = '' ): array {
		if ( $this->is_dry_run() ) {
			$payload = array(
				'network'     => 'google',
				'location_id' => $this->location_id,
				'message'     => $message,
				'image'       => $image_url,
				'link'        => $link_url,
			);
			error_log( '[CONVOCA DRY-RUN] GBP payload: ' . wp_json_encode( $payload ) );
			return array( 'success' => true, 'message' => '[DRY-RUN] Simulated GBP', 'id' => 'dry_gbp_' . time() );
		}

		if ( empty( $this->access_token ) ) {
			return array( 'success' => false, 'message' => 'Token no disponible. Reconecta la cuenta.' );
		}

		// Truncate and sanitize for GBP.
		$message = \Convoca\Enroll\Social\Social_Payload::sanitize_for_gbp( $message );
		$message = \Convoca\Enroll\Social\Social_Payload::truncate_for_network( $message, 'google' );

		// Refresh token if expired.
		$this->maybe_refresh_token();

		if ( empty( $this->location_id ) ) {
			return array( 'success' => false, 'message' => 'GBP: No hay location_id. Reconecta la cuenta.' );
		}

		$post_body = array(
			'languageCode' => 'es',
			'summary'      => mb_substr( $message, 0, 1500 ),
		);

		if ( $link_url ) {
			$post_body['callToAction'] = array(
				'actionType' => 'LEARN_MORE',
				'url'        => $link_url,
			);
		}

		if ( $image_url ) {
			$post_body['media'] = array(
				array(
					'mediaFormat' => 'PHOTO',
					'sourceUrl'   => $image_url,
				),
			);
		}

		$response = wp_remote_post(
			"https://businessprofileperformance.googleapis.com/v1/{$this->location_id}/localPosts",
			array(
				'headers' => array(
					'Authorization' => "Bearer {$this->access_token}",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $post_body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => 'GBP: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! empty( $body['error'] ) ) {
			$err = $body['error']['message'] ?? "HTTP $code";
			\Convoca\Enroll\Media\Media_Logger::log( 'social_post', 0, 'gbp_error', 'error', array(
				'response' => $body,
				'code'     => $code,
			) );
			\Convoca\Enroll\Social\Social_Payload::log_api_error( 'gbp', $response, 'https://businessprofileperformance.googleapis.com/v1/{location_id}/localPosts' );
			return array( 'success' => false, 'message' => "GBP: $err" );
		}

		return array( 'success' => true, 'message' => 'Publicado en Google Business Profile', 'id' => $body['name'] ?? '' );
	}

	/**
	 * Refresh the access_token using the refresh_token if expired.
	 */
	private function maybe_refresh_token(): void {
		if ( empty( $this->refresh_token ) ) {
			return;
		}

		$expires = $this->account['token_expires_at'] ?? null;
		if ( $expires && strtotime( $expires ) > time() + 300 ) {
			return; // Still valid (5 min buffer).
		}

		$client_id     = defined( 'CONVOCA_GOOGLE_CLIENT_ID' ) ? CONVOCA_GOOGLE_CLIENT_ID : '';
		$client_secret = defined( 'CONVOCA_GOOGLE_CLIENT_SECRET' ) ? CONVOCA_GOOGLE_CLIENT_SECRET : '';

		if ( ! $client_id || ! $client_secret ) {
			return;
		}

		$resp = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body' => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $this->refresh_token,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
			),
		) );

		if ( is_wp_error( $resp ) ) {
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! empty( $body['access_token'] ) ) {
			$this->access_token = $body['access_token'];
			// Update stored token.
			global $wpdb;
			$encrypted = \Convoca\Enroll\Social\Social_OAuth::store_token(
				$this->account['network'],
				$this->account['account_id'],
				$this->account['account_name'],
				$body['access_token'],
				$this->refresh_token,
				$body['expires_in'] ?? 3600
			);
		}
	}

	public function is_authenticated(): bool {
		return ! empty( $this->access_token );
	}

	public function get_status(): array {
		return array(
			'connected'    => $this->is_authenticated(),
			'account_name' => $this->account['account_name'] ?? '',
			'expires_at'   => $this->account['token_expires_at'] ?? null,
		);
	}

	private function is_dry_run(): bool {
		return defined( 'CONVOCA_SOCIAL_DRY_RUN' ) && CONVOCA_SOCIAL_DRY_RUN === true;
	}
}
