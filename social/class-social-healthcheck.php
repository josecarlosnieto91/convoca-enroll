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

namespace Convoca\Enroll\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Social_Healthcheck {
	public static function init(): void {
		add_action( 'convoca_social_token_healthcheck', array( __CLASS__, 'run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_warning' ) );
	}

	public static function run(): void {
		$accounts = Social_OAuth::get_accounts();
		if ( empty( $accounts ) ) {
			return;
		}
		foreach ( $accounts as $acct ) {
			if ( empty( $acct['access_token'] ) ) {
				continue;
			}
			$provider = self::get_provider( $acct );
			if ( ! $provider ) {
				continue;
			}
			$result = $provider->test_connection();
			\Convoca\Enroll\Media\Media_Logger::log(
				'social_healthcheck',
				(int) $acct['id'],
				$result['success'] ? 'ok' : 'fail',
				$result['message'] ?? '',
				array( 'network' => $acct['network'] )
			);
		}
	}

	public static function admin_warning(): void {
		if ( ! current_user_can( 'convoca_manage_social' ) ) {
			return;
		}
		$accounts = Social_OAuth::get_accounts();
		$expired  = array();
		foreach ( $accounts as $acct ) {
			if ( empty( $acct['is_active'] ) ) {
				$expired[] = $acct['network'] . ': ' . ( $acct['account_name'] ?? $acct['label'] );
			}
		}
		if ( empty( $expired ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && $screen->id === 'admin_page_convoca-media-accounts' ) {
			return;
		}

		$list = implode( ', ', $expired );
		$url  = admin_url( 'admin.php?page=convoca-media-accounts' );
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo '⚠️ Convoca Media Suite: La conexión con <strong>' . esc_html( $list ) . '</strong> ha expirado. ';
		echo 'Por favor, ve a <a href="' . esc_url( $url ) . '">Media → Redes Sociales</a> y vuelve a conectar la cuenta.';
		echo '</p></div>';
	}

	private static function get_provider( array $acct ) {
		return match ( $acct['network'] ) {
			'facebook', 'instagram' => new Meta_Provider( (int) $acct['id'] ),
			'google_my_business' => new GBP_Provider( (int) $acct['id'] ),
			default => null,
		};
	}
}
