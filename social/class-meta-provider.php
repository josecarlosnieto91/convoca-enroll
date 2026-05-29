<?php
/**
 * Meta Provider — Facebook Pages + Instagram Business.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles OAuth and publishing to Facebook and Instagram via Graph API.
 */
class Meta_Provider implements Social_Provider_Interface {

	const API_VERSION = 'v22.0';
	const BASE_URL    = 'https://graph.facebook.com/';

	private array $account;
	private string $access_token;
	private string $page_id;
	private string $ig_user_id;

	/**
	 * @param int $account_id Social account DB ID.
	 */
	public function __construct( int $account_id ) {
		$data = Social_OAuth::get_token( $account_id );
		$this->account      = $data ?: array();
		$this->access_token = $this->account['access_token'] ?? '';
		$this->page_id      = $this->account['account_id'] ?? '';
	}

	/**
	 * Exchange auth code for long-lived token.
	 */
	public function authenticate( string $auth_code ): array {
		// In real flow: exchange $auth_code for access_token via:
		// GET https://graph.facebook.com/v22.0/oauth/access_token?client_id=...&redirect_uri=...&client_secret=...&code=$auth_code
		// Then exchange short-lived for long-lived:
		// GET https://graph.facebook.com/v22.0/oauth/access_token?grant_type=fb_exchange_token&client_id=...&client_secret=...&fb_exchange_token=$short_token

		return array( 'success' => false, 'message' => 'OAuth flow requires Meta App credentials. Use the Meta Developer Portal.' );
	}

	/**
	 * Publish to Facebook Page and/or Instagram.
	 */
	public function publish( string $message, string $image_url = '', string $link_url = '' ): array {
		if ( $this->is_dry_run() ) {
			return array( 'success' => true, 'message' => '[DRY-RUN] Simulated Facebook publish', 'id' => 'dry_run_fb_' . time() );
		}

		if ( empty( $this->access_token ) ) {
			return array( 'success' => false, 'message' => 'Meta: Token no disponible. Reconecta la cuenta.' );
		}

		// Post to Facebook Page
		$endpoint = self::BASE_URL . self::API_VERSION . "/{$this->page_id}/" . ( $image_url ? 'photos' : 'feed' );
		$body     = array( 'access_token' => $this->access_token );

		if ( $image_url ) {
			$body['url']     = $image_url;
			$body['caption'] = $message;
		} else {
			$body['message'] = $message;
			if ( $link_url ) {
				$body['link'] = $link_url;
			}
		}

		$response = wp_remote_post( $endpoint, array( 'body' => $body, 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => 'Meta: ' . $response->get_error_message() );
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $result['error'] ) ) {
			return array( 'success' => false, 'message' => 'Meta: ' . ( $result['error']['message'] ?? 'Error desconocido' ) );
		}

		return array( 'success' => true, 'message' => 'Publicado en Facebook', 'id' => $result['id'] ?? '' );
	}

	public function is_authenticated(): bool {
		return ! empty( $this->access_token );
	}

	public function get_status(): array {
		return array(
			'connected'   => $this->is_authenticated(),
			'account_name' => $this->account['account_name'] ?? '',
			'expires_at'   => $this->account['token_expires_at'] ?? null,
		);
	}

	/**
	 * Check if dry-run mode is enabled.
	 */
	private function is_dry_run(): bool {
		return defined( 'CONVOCA_SOCIAL_DRY_RUN' ) && CONVOCA_SOCIAL_DRY_RUN === true;
	}
}
