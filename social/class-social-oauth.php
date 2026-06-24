<?php
/**
 * OAuth2 manager for social network authentication.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages OAuth2 tokens for social accounts.
 */
class Social_OAuth {

	const TOKEN_CRYPT_KEY = 'convoca_social_encrypt_v1';

	/**
	 * Encrypt and store a token.
	 */
	public static function store_token( string $network, string $account_id, string $account_name, string $access_token, ?string $refresh_token = null, ?int $expires_in = null ): int {
		global $wpdb;

		$encrypted = self::encrypt( $access_token );
		$refresh   = $refresh_token ? self::encrypt( $refresh_token ) : null;

		$wpdb->replace(
			$wpdb->prefix . 'convoca_social_accounts',
			array(
				'network'          => $network,
				'account_id'       => $account_id,
				'account_name'     => $account_name,
				'access_token'     => $encrypted,
				'refresh_token'    => $refresh,
				'token_expires_at' => $expires_in ? date( 'Y-m-d H:i:s', time() + $expires_in ) : null,
				'is_active'        => 1,
				'last_sync_at'     => current_time( 'mysql' ),
			) 
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get a decrypted token for an account.
	 */
	public static function get_token( int $account_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}conv_social_accounts WHERE id = %d AND is_active = 1",
				$account_id
			),
			ARRAY_A 
		);

		if ( ! $row ) {
			return null;
		}

		$row['access_token'] = self::decrypt( $row['access_token'] );
		if ( $row['refresh_token'] ) {
			$row['refresh_token'] = self::decrypt( $row['refresh_token'] );
		}

		return $row;
	}

	/**
	 * Get all active accounts for a network.
	 */
		/**
	 * Update token status for an account.
	 */
	public static function update_token_status( int $account_id, string $status, string $error = '' ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'convoca_social_accounts',
			array(
				'is_active' => ( $status === 'active' ? 1 : 0 ), 'last_error' => $error
			),
			array( 'id' => $account_id )
		);
	}

	public static function get_accounts( string $network = '' ): array {
		global $wpdb;
		$where = $network ? $wpdb->prepare( 'WHERE network = %s AND is_active = 1', $network ) : 'WHERE is_active = 1';
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}conv_social_accounts {$where} ORDER BY network, account_name", ARRAY_A );
	}

	/**
	 * Simple encryption for token storage.
	 */
	private static function encrypt( string $data ): string {
		$key = defined( 'CONVOCA_SOCIAL_KEY' ) ? CONVOCA_SOCIAL_KEY : wp_salt( 'auth' );
		$iv  = substr( wp_salt( 'nonce' ), 0, 16 );
		return base64_encode( openssl_encrypt( $data, 'aes-256-cbc', $key, 0, $iv ) );
	}

	/**
	 * Simple decryption.
	 */
	private static function decrypt( string $data ): string {
		$key = defined( 'CONVOCA_SOCIAL_KEY' ) ? CONVOCA_SOCIAL_KEY : wp_salt( 'auth' );
		$iv  = substr( wp_salt( 'nonce' ), 0, 16 );
		return openssl_decrypt( base64_decode( $data ), 'aes-256-cbc', $key, 0, $iv );
	}
}
