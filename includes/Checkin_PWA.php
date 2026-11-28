<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
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
 * PWA support for the QR Check-in page.
 * Handles manifest.json and service worker registration.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Checkin_PWA {

	/**
	 * Initialize PWA hooks.
	 */
	public static function init(): void {
		// El check-in instalable es PRO: sin licencia no se engancha nada (enroll#123).
		if ( ! \Convoca\Core\License_Manager::has_pro( 'pwa_checkin' ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'register_rewrites' ) );
		// Priority 1: run before the Checkin_Handler (which fires at 10).
		add_action( 'template_redirect', array( __CLASS__, 'serve_manifest' ), 1 );
		add_action( 'init', array( __CLASS__, 'serve_sw' ), 0 ); // High priority, before everything.
		add_action( 'wp_head', array( __CLASS__, 'add_meta_tags' ) );

		// Register rest route for online check-in queue flush.
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'convoca-enroll/v1',
					'/checkin/flush',
					array(
						'methods'             => 'POST',
						'callback'            => array( __CLASS__, 'rest_flush_queue' ),
						'permission_callback' => function () {
							return current_user_can( 'manage_inscripciones' ) || in_array( 'voluntario_aprobado', (array) wp_get_current_user()->roles, true );
						},
					)
				);
			}
		);
	}

	/**
	 * Register rewrite rules for PWA assets (not needed — served via template_redirect + URI check).
	 * Kept as no-op for backward compatibility.
	 */
	public static function register_rewrites(): void {
		// Assets are served via template_redirect by matching $_SERVER["REQUEST_URI"].
		// No rewrite rules needed.
	}

	/**
	 * Serve manifest.json.
	 */
	public static function serve_manifest(): void {
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $uri, '/checkin/manifest.json' ) === false ) {
			return;
		}

		$logo_url = \Convoca\Core\Utils::get_logo_url( 'checkin' );

		header( 'Content-Type: application/json' );
		echo wp_json_encode(
			array(
				'name'             => __( 'Check-in Convoca', 'convoca-enroll' ),
				'short_name'       => __( 'Check-in', 'convoca-enroll' ),
				'description'      => __( 'Escáner QR de asistencia para monitores de Convoca', 'convoca-enroll' ),
				'start_url'        => home_url( '/checkin/' ),
				'scope'            => '/checkin/',
				'display'          => 'standalone',
				'orientation'      => 'portrait',
				'background_color' => '#0f172a',
				'theme_color'      => '#0f172a',
				'categories'       => array( 'utilities', 'productivity' ),
				'icons'            => self::get_icons( $logo_url ),
				'shortcuts'        => array(
					array(
						'name'       => __( 'Escáner QR', 'convoca-enroll' ),
						'short_name' => __( 'Escanear', 'convoca-enroll' ),
						'url'        => '/checkin/',
					),
				),
			),
			JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	/**
	 * Serve the service worker script.
	 */
	public static function serve_sw(): void {
		$uri   = $_SERVER['REQUEST_URI'] ?? '';
		$query = wp_parse_url( $uri, PHP_URL_QUERY ) ?? '';
		if ( strpos( $query, 'sw=1' ) === false ) {
			return;
		}

		$sw_path = CONVOCA_ENROLL_DIR . 'public/assets/pwa/sw.js';
		if ( file_exists( $sw_path ) ) {
			header( 'Content-Type: application/javascript' );
			header( 'Cache-Control: no-cache' );
			header( 'Service-Worker-Allowed: /checkin/' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- PWA service worker delivery
			readfile( $sw_path );
		} else {
			header( 'HTTP/1.1 404 Not Found' );
			echo '// Service worker not found';
		}
		exit;
	}

	/**
	 * Add PWA meta tags to the scanner page.
	 */
	public static function add_meta_tags(): void {
		if ( ! get_query_var( 'convoca_enroll_checkin_page' ) ) {
			return;
		}

		$logo_url = \Convoca\Core\Utils::get_logo_url( 'checkin' );
		?>
		<link rel="manifest" href="<?php echo esc_url( home_url( '/checkin/manifest.json' ) ); ?>">
		<?php /* SW served via PHP (no .js suffix to avoid nginx static file handling) */ ?>
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="apple-mobile-web-app-title" content="Check-in Convoca">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="theme-color" content="#0f172a">
		<?php if ( $logo_url ) : ?>
		<link rel="apple-touch-icon" href="<?php echo esc_url( $logo_url ); ?>">
		<?php endif; ?>
		<!-- Service worker registration -->
		<script>
		if ('serviceWorker' in navigator) {
			window.addEventListener('load', () => {
				navigator.serviceWorker.register('/checkin/?sw=1', { scope: '/checkin/' })
					.then(reg => console.log('SW registered:', reg.scope))
					.catch(err => console.warn('SW registration failed:', err));
			});
		}
		</script>
		<?php
	}

	/**
	 * Get icon list for the manifest.
	 *
	 * @return array<int, array<string, string>> mixed>
	 */
	private static function get_icons( string $logo_url = '' ): array {
		$icons = array();

		if ( $logo_url ) {
			$icons[] = array(
				'src'     => $logo_url,
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			);
			$icons[] = array(
				'src'     => $logo_url,
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any maskable',
			);
		}

		// Fallback: generate from site icon.
		$site_icon = get_site_icon_url( 192 );
		if ( $site_icon ) {
			$icons[] = array(
				'src'   => $site_icon,
				'sizes' => '192x192',
				'type'  => 'image/png',
			);
			$icons[] = array(
				'src'   => get_site_icon_url( 512 ),
				'sizes' => '512x512',
				'type'  => 'image/png',
			);
		}

		return $icons;
	}

	/**
	 * Valida token y nonce de un elemento.
	 *
	 * @param string $token Token.
	 * @param string $nonce Nonce.
	 * @return string|null Estado de error o null si vale.
	 */
	private static function validar_item( string $token, string $nonce ): ?string {
		if ( ! $token || ! $nonce ) {
			return 'invalid';
		}

		return wp_verify_nonce( $nonce, 'convoca_enroll_qr_checkin' ) ? null : 'expired_nonce';
	}

	/**
	 * ¿La persona puede marcar esa inscripción?
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	private static function puede_marcar( string $token ): bool {
		$inscripcion_id = Checkin_Link::id_for_token( $token );
		$actividad_id   = $inscripcion_id ? (int) CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' ) : 0;

		return $actividad_id > 0 && CPT_Actividad::is_user_responsible( get_current_user_id(), $actividad_id );
	}

	/**
	 * Procesa un elemento de la cola offline.
	 *
	 * @param array<string, mixed> $item Elemento con `body`.
	 * @return array<string, mixed> Resultado para el cliente.
	 */
	private static function procesar_item_cola( array $item ): array {
		parse_str( (string) ( $item['body'] ?? '' ), $data );
		$raw_id    = $data['id'] ?? '';
		$raw_nonce = $data['nonce'] ?? '';
		$token     = is_string( $raw_id ) ? sanitize_text_field( $raw_id ) : '';
		$nonce     = is_string( $raw_nonce ) ? sanitize_text_field( $raw_nonce ) : '';

		$error = self::validar_item( $token, $nonce );
		if ( null !== $error ) {
			return array(
				'token'  => $token,
				'status' => $error,
			);
		}

		if ( ! self::puede_marcar( $token ) ) {
			return array(
				'token'  => $token,
				'status' => 'forbidden',
			);
		}

		$result = ( new Checkin_Handler() )->mark_as_attended_by_token( $token, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return array(
				'token'   => $token,
				'status'  => 'error',
				'message' => $result->get_error_message(),
			);
		}

		return array(
			'token'           => $token,
			'status'          => 'success',
			'already_checked' => (bool) $result['ya_marcada'],
		);
	}

	/**
	 * REST endpoint: flush pending offline check-in queue.
	 */
	public static function rest_flush_queue( \WP_REST_Request $request ): \WP_REST_Response {
		$items   = $request->get_json_params()['items'] ?? array();
		$results = array();

		foreach ( $items as $item ) {
			$results[] = self::procesar_item_cola( (array) $item );
		}

		return new \WP_REST_Response(
			array(
				'processed' => count( $results ),
				'results'   => $results,
			)
		);
	}
}
