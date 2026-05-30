<?php
/**
 * Social Provider Interface — abstract contract for all social networks.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each social network must implement this interface.
 */
interface Social_Provider_Interface {

	/**
	 * Authenticate and store tokens.
	 *
	 * @param string $auth_code Authorization code from OAuth redirect.
	 * @return array{success: bool, message: string, account_id?: int}
	 */
	public function authenticate( string $auth_code ): array;

	/**
	 * Publish content to the network.
	 *
	 * @param string $message   Text content.
	 * @param string $image_url Optional image URL.
	 * @param string $link_url  Optional link URL.
	 * @return array{success: bool, message: string, id?: string}
	 */
	public function publish( string $message, string $image_url = '', string $link_url = '' ): array;

	/**
	 * Check if the current token is valid.
	 *
	 * @return bool
	 */
	public function is_authenticated(): bool;

	/**
	 * Get the current account status.
	 *
	 * @return array{connected: bool, account_name: string, expires_at: ?string}
	 */
	public function get_status(): array;

	/**
	 * Test the connection and return status.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function test_connection(): array;
}
