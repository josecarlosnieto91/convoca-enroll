<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Social
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
 * Meta Provider — Facebook Pages + Instagram Business publishing.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Meta_Provider implements Social_Provider_Interface {

	const API_VERSION = 'v19.0';
	const BASE_URL    = 'https://graph.facebook.com/';

	private array $account;
	private string $access_token;
	private string $page_id;
	private string $ig_user_id;
	private int $account_id;

	public function __construct( int $account_id ) {
		$this->account_id   = $account_id;
		$data = Social_OAuth::get_token( $account_id );
		$this->account      = $data ?: array();
		$this->access_token = $this->account['access_token'] ?? '';
		$this->page_id      = $this->account['account_id'] ?? '';
	}

	public function authenticate( string $auth_code ): array {
		return array( 'success' => false, 'message' => 'Usa los endpoints REST /social/auth/meta' );
	}

	/**
	 * Publish to Facebook and optionally Instagram.
	 *
	 * @param string $poster_url Image URL to publish.
	 * @param string $message    Text caption.
	 * @param string $link_url   Optional link.
	 */
	public function publish( string $message, string $poster_url = '', string $link_url = '' ): array {
		// Idempotency lock: prevent concurrent duplicate publishing.
		$lock_key = 'convoca_lock_' . md5( $message . $poster_url . 'meta' );
		if ( get_transient( $lock_key ) ) {
			return array( 'success' => false, 'message' => 'Ya hay una publicación en curso para este contenido (lock activo).' );
		}
		set_transient( $lock_key, time(), 300 ); // 5 min lock

		// Dry-run check.
		if ( $this->is_dry_run() ) {
			$payload = array(
				'network' => 'meta',
				'page_id' => $this->page_id,
				'poster'  => $poster_url,
				'message' => $message,
				'link'    => $link_url,
			);
			error_log( '[CONVOCA DRY-RUN] Meta payload: ' . wp_json_encode( $payload ) );
			return array( 'success' => true, 'message' => '[DRY-RUN] Simulated', 'id' => 'dry_' . time() );
		}

		if ( empty( $this->access_token ) ) {
			return array( 'success' => false, 'message' => 'Token no disponible. Reconecta la cuenta.' );
		}

		$results = array();
		$all_ok  = true;

		// 1. Publish to Facebook Page.
		// Truncate message for network limits.
		$fb_message   = \Convoca\Enroll\Social\Social_Payload::truncate_for_network( $message, 'facebook' );
		$ig_message   = \Convoca\Enroll\Social\Social_Payload::truncate_for_network( $message, 'instagram' );

		if ( $this->page_id ) {
			$fb_result = $this->publish_facebook( $fb_message, $poster_url, $link_url );
			$results[] = $fb_result;
			if ( ! $fb_result['success'] ) {
				$all_ok = false;
			}
		}

		// 2. Publish to Instagram (requires image).
		if ( $this->ig_user_id && $poster_url ) {
			$ig_result = $this->publish_instagram( $ig_message, $poster_url );
			$results[] = $ig_result;
			if ( ! $ig_result['success'] ) {
				$all_ok = false;
			}
		}

		// Log results.
		\Convoca\Enroll\Media\Media_Logger::log( 'social_post', 0, 'meta_publish', $all_ok ? 'ok' : 'error', array(
			'results' => $results,
			'page'    => $this->page_id,
			'ig'      => $this->ig_user_id,
		) );

		delete_transient( $lock_key );
		return array(
			'success' => $all_ok,
			'message' => $all_ok ? 'Publicado en Meta' : 'Error parcial en Meta',
			'results' => $results,
		);
	}

	/**
	 * Publish to Facebook Page feed/photo.
	 */
	private function publish_facebook( string $message, string $image_url, string $link_url ): array {
		$endpoint = self::BASE_URL . self::API_VERSION . "/{$this->page_id}/" . ( $image_url ? 'photos' : 'feed' );
		$body     = array( 'access_token' => $this->access_token );

		if ( $image_url ) {
			$body['url']     = $image_url;
			$body['message'] = $message;
		} else {
			$body['message'] = $message;
			if ( $link_url ) {
				$body['link'] = $link_url;
			}
		}

		$response = wp_remote_post( $endpoint, array( 'body' => $body, 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			\Convoca\Enroll\Social\Social_Payload::log_api_error( 'meta_facebook', $response, $endpoint );
			return array( 'success' => false, 'message' => 'Facebook: ' . $response->get_error_message() );
		}

		$code   = wp_remote_retrieve_response_code( $response );
		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! empty( $result['error'] ) ) {
			$err = $result['error']['message'] ?? "HTTP $code";

			// Fallback: if photo upload failed, retry as text-only post with link.
			if ( $image_url && strpos( $err, 'url' ) !== false || strpos( $err, 'media' ) !== false || strpos( $err, 'photo' ) !== false ) {
				\Convoca\Enroll\Media\Media_Logger::log( 'social_post', 0, 'meta_fallback_text', 'warning', array(
					'error' => $err,
					'original_endpoint' => $endpoint,
				) );
				return self::publish_facebook( $message, '', $link_url );
			}

			\Convoca\Enroll\Social\Social_Payload::log_api_error( 'meta_facebook', $response, $endpoint );
			return array( 'success' => false, 'message' => "Facebook: $err" );
		}

		return array( 'success' => true, 'message' => 'Facebook OK', 'id' => $result['id'] ?? '' );
	}

	/**
	 * Publish to Instagram Business (2-step).
	 */
	private function publish_instagram( string $message, string $image_url ): array {
		if ( ! $this->ig_user_id ) {
			$this->ig_user_id = $this->discover_instagram();
		}
		if ( ! $this->ig_user_id ) {
			return array( 'success' => false, 'message' => 'Instagram: no hay cuenta de Instagram Business vinculada a esta página.' );
		}

		// Step 1: Create media container.
		$create = wp_remote_post( self::BASE_URL . self::API_VERSION . "/{$this->ig_user_id}/media", array(
			'body' => array(
				'image_url'    => $image_url,
				'caption'      => mb_substr( $message, 0, 2200 ),
				'access_token' => $this->access_token,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $create ) ) {
			return array( 'success' => false, 'message' => 'Instagram: ' . $create->get_error_message() );
		}

		$create_body = json_decode( wp_remote_retrieve_body( $create ), true );
		if ( ! empty( $create_body['error'] ) ) {
			return array( 'success' => false, 'message' => "Instagram: {$create_body['error']['message']}" );
		}

		$media_id = $create_body['id'] ?? '';
		if ( ! $media_id ) {
			return array( 'success' => false, 'message' => 'Instagram: no se obtuvo ID del media container.' );
		}

		// Step 2: Publish the container.
		sleep( 2 );

		$publish = wp_remote_post( self::BASE_URL . self::API_VERSION . "/{$this->ig_user_id}/media_publish", array(
			'body' => array(
				'creation_id'  => $media_id,
				'access_token' => $this->access_token,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $publish ) ) {
			return array( 'success' => false, 'message' => 'Instagram: ' . $publish->get_error_message() );
		}

		$pub_body = json_decode( wp_remote_retrieve_body( $publish ), true );
		if ( ! empty( $pub_body['error'] ) ) {
			return array( 'success' => false, 'message' => "Instagram: {$pub_body['error']['message']}" );
		}

		return array( 'success' => true, 'message' => 'Instagram OK', 'id' => $pub_body['id'] ?? '' );
	}

	/**
	 * Discover Instagram Business account linked to this page.
	 */
	private function discover_instagram(): string {
		$url = self::BASE_URL . self::API_VERSION . "/{$this->page_id}?fields=instagram_business_account&access_token={$this->access_token}";
		$resp = wp_remote_get( $url );
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return $body['instagram_business_account']['id'] ?? '';
	}

	public function is_authenticated(): bool {
		return ! empty( $this->access_token );
	}


	/**
	 * Test connection to Meta API.
	 */
	public function test_connection(): array {
		$token = Social_OAuth::get_token( $this->account_id );
		if ( ! $token || empty( $token["access_token"] ) ) {
			return [ "success" => false, "message" => "Token no encontrado." ];
		}

		$url = self::BASE_URL . self::API_VERSION . "/me?fields=id,name&access_token=" . $token["access_token"];
		$resp = wp_remote_get( $url, [ "timeout" => 10 ] );

		if ( is_wp_error( $resp ) ) {
			return [ "success" => false, "message" => "Error de conexion: " . $resp->get_error_message() ];
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code !== 200 || isset( $body["error"] ) ) {
			Social_OAuth::update_token_status( $this->account_id, "token_expired", $body["error"]["message"] ?? "Error desconocido" );
			return [ "success" => false, "message" => $body["error"]["message"] ?? "HTTP $code" ];
		}

		Social_OAuth::update_token_status( $this->account_id, "active" );
		return [ "success" => true, "message" => "Conexion OK: " . ($body["name"] ?? "Cuenta Meta") ];
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
