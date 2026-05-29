<?php
/**
 * Google Business Profile Provider.
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
	private string $location_id;

	public function __construct( int $account_id ) {
		$data = Social_OAuth::get_token( $account_id );
		$this->account      = $data ?: array();
		$this->access_token = $this->account['access_token'] ?? '';
		$this->location_id  = $this->account['account_id'] ?? '';
	}

	public function authenticate( string $auth_code ): array {
		return array( 'success' => false, 'message' => 'OAuth flow requires Google Cloud credentials.' );
	}

	public function publish( string $message, string $image_url = '', string $link_url = '' ): array {
		if ( $this->is_dry_run() ) {
			return array( 'success' => true, 'message' => '[DRY-RUN] Simulated GBP publish', 'id' => 'dry_run_gbp_' . time() );
		}

		if ( empty( $this->access_token ) ) {
			return array( 'success' => false, 'message' => 'GBP: Token no disponible.' );
		}

		$post_body = array(
			'languageCode' => 'es',
			'summary'      => $message,
			'callToAction' => $link_url ? array( 'actionType' => 'LEARN_MORE', 'url' => $link_url ) : null,
		);

		$response = wp_remote_post(
			"https://businessprofileperformance.googleapis.com/v1/{$this->location_id}/localPosts",
			array(
				'headers' => array( 'Authorization' => "Bearer {$this->access_token}", 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $post_body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => 'GBP: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['error'] ) ) {
			return array( 'success' => false, 'message' => 'GBP: ' . ( $body['error']['message'] ?? 'Error' ) );
		}

		return array( 'success' => true, 'message' => 'Publicado en Google', 'id' => $body['name'] ?? '' );
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
