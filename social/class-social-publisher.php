<?php
/**
 * Social Publisher — post content to social networks.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publish content to connected social accounts.
 */
class Social_Publisher {

	/**
	 * Publish to a single account.
	 *
	 * @param int    $account_id  Social account ID.
	 * @param string $message     Text content.
	 * @param string $image_url   Optional image URL.
	 * @param string $link_url    Optional link URL.
	 * @return array{success: bool, message: string}
	 */
	public static function publish( int $account_id, string $message, string $image_url = '', string $link_url = '' ): array {
		$account = Social_OAuth::get_token( $account_id );
		if ( ! $account ) {
			return array( 'success' => false, 'message' => 'Cuenta no encontrada o token expirado.' );
		}

		$network = $account['network'];

		try {
			switch ( $network ) {
				case 'facebook':
					return self::publish_facebook( $account, $message, $image_url, $link_url );
				case 'instagram':
					return self::publish_instagram( $account, $message, $image_url );
				case 'google':
					return self::publish_google( $account, $message, $image_url, $link_url );
				default:
					return array( 'success' => false, 'message' => "Red no soportada: $network" );
			}
		} catch ( \Throwable $e ) {
			return array( 'success' => false, 'message' => $e->getMessage() );
		}
	}

	/**
	 * Publish to Facebook Page via Graph API.
	 */
	private static function publish_facebook( array $account, string $message, string $image_url, string $link_url ): array {
		$token   = $account['access_token'];
		$page_id = $account['account_id'];

		if ( $image_url ) {
			// Photo post
			$url = "https://graph.facebook.com/v22.0/{$page_id}/photos";
			$data = array(
				'url'         => $image_url,
				'caption'     => $message,
				'access_token' => $token,
			);
		} else {
			// Text post
			$url = "https://graph.facebook.com/v22.0/{$page_id}/feed";
			$data = array(
				'message'      => $message,
				'link'         => $link_url ?: '',
				'access_token' => $token,
			);
		}

		$response = wp_remote_post( $url, array(
			'body'    => $data,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return array( 'success' => false, 'message' => $body['error']['message'] ?? 'Error desconocido' );
		}

		return array( 'success' => true, 'message' => 'Publicado en Facebook', 'id' => $body['id'] ?? '' );
	}

	/**
	 * Publish to Instagram Business via Graph API.
	 */
	private static function publish_instagram( array $account, string $message, string $image_url ): array {
		$token     = $account['access_token'];
		$ig_user_id = $account['account_id'];

		if ( ! $image_url ) {
			return array( 'success' => false, 'message' => 'Instagram requiere una imagen.' );
		}

		// Step 1: Create media container
		$create = wp_remote_post( "https://graph.facebook.com/v22.0/{$ig_user_id}/media", array(
			'body' => array(
				'image_url'    => $image_url,
				'caption'      => $message,
				'access_token' => $token,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $create ) ) {
			return array( 'success' => false, 'message' => $create->get_error_message() );
		}

		$create_body = json_decode( wp_remote_retrieve_body( $create ), true );

		if ( isset( $create_body['error'] ) ) {
			return array( 'success' => false, 'message' => $create_body['error']['message'] ?? 'Error' );
		}

		$media_id = $create_body['id'] ?? '';

		if ( ! $media_id ) {
			return array( 'success' => false, 'message' => 'No se obtuvo ID del media container.' );
		}

		// Step 2: Publish the container
		sleep( 2 ); // Brief delay for processing

		$publish = wp_remote_post( "https://graph.facebook.com/v22.0/{$ig_user_id}/media_publish", array(
			'body' => array(
				'creation_id'  => $media_id,
				'access_token' => $token,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $publish ) ) {
			return array( 'success' => false, 'message' => $publish->get_error_message() );
		}

		$pub_body = json_decode( wp_remote_retrieve_body( $publish ), true );

		if ( isset( $pub_body['error'] ) ) {
			return array( 'success' => false, 'message' => $pub_body['error']['message'] ?? 'Error' );
		}

		return array( 'success' => true, 'message' => 'Publicado en Instagram', 'id' => $pub_body['id'] ?? '' );
	}

	/**
	 * Publish to Google Business Profile.
	 */
	private static function publish_google( array $account, string $message, string $image_url, string $link_url ): array {
		$token     = $account['access_token'];
		$location_id = $account['account_id'];

		$post_body = array(
			'languageCode' => 'es',
			'summary'      => $message,
			'callToAction' => $link_url ? array(
				'actionType' => 'LEARN_MORE',
				'url'        => $link_url,
			) : null,
		);

		if ( $image_url ) {
			$post_body['media'] = array(
				array(
					'mediaFormat' => 'PHOTO',
					'sourceUrl'   => $image_url,
				),
			);
		}

		$response = wp_remote_post(
			"https://businessprofileperformance.googleapis.com/v1/{$location_id}/localPosts",
			array(
				'headers' => array(
					'Authorization' => "Bearer $token",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $post_body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return array( 'success' => false, 'message' => $body['error']['message'] ?? 'Error' );
		}

		return array( 'success' => true, 'message' => 'Publicado en Google', 'id' => $body['name'] ?? '' );
	}
}
