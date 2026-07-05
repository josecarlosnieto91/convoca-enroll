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
 * REST API endpoints for social OAuth flows.
 *
 * @package Convoca\Enroll\Social
 */

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register OAuth and social management REST routes.
 */
class Social_Rest_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		// ── OAuth Start ──
		register_rest_route(
			'convoca/v1',
			'/social/auth/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'auth_meta_start' ),
				'permission_callback' => array( $this, 'can_manage_social' ),
			) 
		);
		register_rest_route(
			'convoca/v1',
			'/social/auth/google',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'auth_google_start' ),
				'permission_callback' => array( $this, 'can_manage_social' ),
			) 
		);

		// ── OAuth Callbacks ──
		register_rest_route(
			'convoca/v1',
			'/social/callback/meta',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'callback_meta' ),
				'permission_callback' => '__return_true',
			) 
		);
		register_rest_route(
			'convoca/v1',
			'/social/callback/google',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'callback_google' ),
				'permission_callback' => '__return_true',
			) 
		);

		// ── Account Management ──
		register_rest_route(
			'convoca/v1',
			'/social/accounts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_accounts' ),
				'permission_callback' => array( $this, 'can_manage_social' ),
			) 
		);
		register_rest_route(
			'convoca/v1',
			'/social/accounts/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_account' ),
				'permission_callback' => array( $this, 'can_manage_social' ),
			) 
		);
	}

	public function can_manage_social(): bool {
		return current_user_can( 'convoca_manage_social' ) || current_user_can( 'manage_options' );
	}

	// ─── META OAUTH ─────────────────────────────────

	public function auth_meta_start( \WP_REST_Request $request ): void {
		$app_id = defined( 'CONVOCA_META_APP_ID' ) ? CONVOCA_META_APP_ID : '';
		if ( ! $app_id ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=meta_no_app' ) );
			exit;
		}

		$state = wp_generate_password( 32, false );
		set_transient( 'convoca_oauth_state_meta', $state, 600 );

		$redirect_uri = rawurlencode( rest_url( 'convoca/v1/social/callback/meta' ) );
		$url          = "https://www.facebook.com/v22.0/dialog/oauth?client_id={$app_id}&redirect_uri={$redirect_uri}&state={$state}&scope=pages_manage_posts,pages_read_engagement,instagram_basic,instagram_content_publish";
		wp_redirect( $url );
		exit;
	}

	public function callback_meta( \WP_REST_Request $request ): void {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );
		$error = $request->get_param( 'error' );

		if ( $error || ! $code ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=oauth_denied' ) );
			exit;
		}

		// Validate state (CSRF).
		$saved = get_transient( 'convoca_oauth_state_meta' );
		if ( ! $saved || $saved !== $state ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=csrf' ) );
			exit;
		}
		delete_transient( 'convoca_oauth_state_meta' );

		$app_id     = defined( 'CONVOCA_META_APP_ID' ) ? CONVOCA_META_APP_ID : '';
		$app_secret = defined( 'CONVOCA_META_APP_SECRET' ) ? CONVOCA_META_APP_SECRET : '';
		if ( ! $app_id || ! $app_secret ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=meta_no_app' ) );
			exit;
		}

		// Step 1: Exchange code for short-lived token.
		$token_url = "https://graph.facebook.com/v22.0/oauth/access_token?client_id={$app_id}&redirect_uri=" . rawurlencode( rest_url( 'convoca/v1/social/callback/meta' ) ) . "&client_secret={$app_secret}&code={$code}";
		$resp      = wp_remote_get( $token_url );
		if ( is_wp_error( $resp ) ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=token_failed' ) );
			exit;
		}
		$body        = json_decode( wp_remote_retrieve_body( $resp ), true );
		$short_token = $body['access_token'] ?? '';
		if ( ! $short_token ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=token_failed' ) );
			exit;
		}

		// Step 2: Exchange for long-lived token.
		$long_url   = "https://graph.facebook.com/v22.0/oauth/access_token?grant_type=fb_exchange_token&client_id={$app_id}&client_secret={$app_secret}&fb_exchange_token={$short_token}";
		$long_resp  = wp_remote_get( $long_url );
		$long_body  = json_decode( wp_remote_retrieve_body( $long_resp ), true );
		$long_token = $long_body['access_token'] ?? $short_token;
		$expires_in = $long_body['expires_in'] ?? 5184000; // ~60 days

		// Step 3: Get user Pages.
		$pages_url  = "https://graph.facebook.com/v22.0/me/accounts?access_token={$long_token}";
		$pages_resp = wp_remote_get( $pages_url );
		$pages_body = json_decode( wp_remote_retrieve_body( $pages_resp ), true );
		$pages      = $pages_body['data'] ?? array();

		$connected = 0;
		foreach ( $pages as $page ) {
			$page_token = $page['access_token'] ?? '';
			$page_id    = $page['id'] ?? '';
			$page_name  = $page['name'] ?? 'Facebook Page';
			if ( ! $page_id || ! $page_token ) {
				continue;
			}

			Social_OAuth::store_token( 'facebook', $page_id, $page_name, $page_token, $long_token, $expires_in );
			++$connected;

			// Check for linked Instagram account.
			$ig_url  = "https://graph.facebook.com/v22.0/{$page_id}?fields=instagram_business_account&access_token={$page_token}";
			$ig_resp = wp_remote_get( $ig_url );
			$ig_body = json_decode( wp_remote_retrieve_body( $ig_resp ), true );
			if ( ! empty( $ig_body['instagram_business_account']['id'] ) ) {
				$ig_id   = $ig_body['instagram_business_account']['id'];
				$ig_name = $ig_body['instagram_business_account']['username'] ?? 'Instagram';
				Social_OAuth::store_token( 'instagram', $ig_id, $ig_name, $page_token, $long_token, $expires_in );
			}
		}

		$msg = $connected > 0 ? 'connected' : 'no_pages';
		wp_redirect( admin_url( "admin.php?page=convoca-media-accounts&convoca_success={$msg}" ) );
		exit;
	}

	// ─── GOOGLE OAUTH ───────────────────────────────

	public function auth_google_start( \WP_REST_Request $request ): void {
		$client_id = defined( 'CONVOCA_GOOGLE_CLIENT_ID' ) ? CONVOCA_GOOGLE_CLIENT_ID : '';
		if ( ! $client_id ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=google_no_app' ) );
			exit;
		}

		$state = wp_generate_password( 32, false );
		set_transient( 'convoca_oauth_state_google', $state, 600 );

		$redirect_uri = rawurlencode( rest_url( 'convoca/v1/social/callback/google' ) );
		$url          = "https://accounts.google.com/o/oauth2/v2/auth?client_id={$client_id}&redirect_uri={$redirect_uri}&response_type=code&scope=https://www.googleapis.com/auth/business.manage&access_type=offline&prompt=consent&state={$state}";
		wp_redirect( $url );
		exit;
	}

	public function callback_google( \WP_REST_Request $request ): void {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );
		$error = $request->get_param( 'error' );

		if ( $error || ! $code ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=oauth_denied' ) );
			exit;
		}

		$saved = get_transient( 'convoca_oauth_state_google' );
		if ( ! $saved || $saved !== $state ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=csrf' ) );
			exit;
		}
		delete_transient( 'convoca_oauth_state_google' );

		$client_id     = defined( 'CONVOCA_GOOGLE_CLIENT_ID' ) ? CONVOCA_GOOGLE_CLIENT_ID : '';
		$client_secret = defined( 'CONVOCA_GOOGLE_CLIENT_SECRET' ) ? CONVOCA_GOOGLE_CLIENT_SECRET : '';
		if ( ! $client_id || ! $client_secret ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=google_no_app' ) );
			exit;
		}

		// Exchange code for tokens.
		$resp = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => rest_url( 'convoca/v1/social/callback/google' ),
					'grant_type'    => 'authorization_code',
				),
			) 
		);
		if ( is_wp_error( $resp ) ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=token_failed' ) );
			exit;
		}
		$body          = json_decode( wp_remote_retrieve_body( $resp ), true );
		$access_token  = $body['access_token'] ?? '';
		$refresh_token = $body['refresh_token'] ?? '';
		$expires_in    = $body['expires_in'] ?? 3600;

		if ( ! $access_token ) {
			wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_error=token_failed' ) );
			exit;
		}

		// Get account name.
		$info_resp    = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'headers' => array( 'Authorization' => "Bearer {$access_token}" ),
			) 
		);
		$info_body    = json_decode( wp_remote_retrieve_body( $info_resp ), true );
		$account_name = $info_body['email'] ?? 'Google Account';

		Social_OAuth::store_token( 'google', $info_body['id'] ?? 'google_1', $account_name, $access_token, $refresh_token, $expires_in );

		wp_redirect( admin_url( 'admin.php?page=convoca-media-accounts&convoca_success=connected' ) );
		exit;
	}

	// ─── ACCOUNT MANAGEMENT ─────────────────────────

	public function list_accounts(): \WP_REST_Response {
		return new \WP_REST_Response( Social_OAuth::get_accounts(), 200 );
	}

	public function delete_account( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;
		$id = (int) $request->get_param( 'id' );
		$wpdb->delete( $wpdb->prefix . 'convoca_social_accounts', array( 'id' => $id ) );
		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}
}
