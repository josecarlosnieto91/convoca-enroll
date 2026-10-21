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

	/** Prefijo del formato de cifrado NUEVO: lo guardado sin prefijo es del formato viejo (issue #21). */
	private const FORMATO = 'convoca_social_enc:v2:';

	/** Cifrado del formato nuevo: autenticado. */
	private const METODO = 'aes-256-gcm';

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
				'token_expires_at' => $expires_in ? gmdate( 'Y-m-d H:i:s', time() + $expires_in ) : null,
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
				"SELECT * FROM {$wpdb->prefix}convoca_social_accounts WHERE id = %d AND is_active = 1",
				$account_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['access_token'] = self::leer_y_migrar( (int) $row['id'], 'access_token', (string) $row['access_token'] );
		if ( $row['refresh_token'] ) {
			$row['refresh_token'] = self::leer_y_migrar( (int) $row['id'], 'refresh_token', (string) $row['refresh_token'] );
		}

		return $row;
	}

	/**
	 * Lee un token guardado y, si venía del formato viejo, lo vuelve a guardar con el nuevo (issue #21).
	 *
	 * Migrar al leer y no de golpe: los tokens que existen hoy se descifran con el formato viejo y se
	 * re-cifran con el nuevo la primera vez que alguien los usa. Así no hay script de migración a ciegas y
	 * no se pierde ninguna conexión de red social.
	 *
	 * Si el valor guardado no se puede descifrar, se devuelve cadena vacía y **no se pisa** lo guardado:
	 * perder un token es peor que tenerlo en un formato viejo.
	 *
	 * @param int    $account_id Cuenta.
	 * @param string $campo      Columna ('access_token' o 'refresh_token').
	 * @param string $guardado   Valor tal cual está en la base de datos.
	 * @return string Token en claro, o cadena vacía si no se pudo descifrar.
	 */
	private static function leer_y_migrar( int $account_id, string $campo, string $guardado ): string {
		if ( '' === $guardado ) {
			return '';
		}

		$claro = self::decrypt( $guardado );

		if ( '' === $claro || ! self::necesita_migrar( $guardado ) ) {
			return $claro;
		}

		global $wpdb;
		$nuevo = self::encrypt( $claro );
		if ( '' !== $nuevo ) {
			$wpdb->update(
				$wpdb->prefix . 'convoca_social_accounts',
				array( $campo => $nuevo ),
				array( 'id' => $account_id )
			);
		}

		return $claro;
	}

	/**
	 * ¿Este valor guardado viene del formato viejo?
	 *
	 * @param string $guardado Valor tal cual está en la base de datos.
	 * @return bool
	 */
	public static function necesita_migrar( string $guardado ): bool {
		return '' !== $guardado && ! str_starts_with( $guardado, self::FORMATO );
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
		return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}convoca_social_accounts {$where} ORDER BY network, account_name", ARRAY_A );
	}

	/**
	 * Cifra un token: AES-256-GCM, IV ALEATORIO y etiqueta de autenticación (issue #21).
	 *
	 * Antes era AES-256-CBC con el IV derivado de `wp_salt( 'nonce' )`, o sea **el mismo siempre**: el
	 * cifrado era determinista —mirar los valores guardados ya decía qué tokens eran iguales— y sin
	 * autenticación, así que una manipulación no se detectaba. Este es el esquema que ya usa el publisher.
	 *
	 * Si openssl no está o falla, devuelve cadena vacía: **nunca** se guarda un token en claro.
	 *
	 * @param string $data Token en claro.
	 * @return string Valor para guardar, o cadena vacía si no se pudo cifrar.
	 */
	private static function encrypt( string $data ): string {
		$iv         = random_bytes( 12 );
		$tag        = '';
		$ciphertext = openssl_encrypt( $data, self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $tag, '', 16 );

		if ( false === $ciphertext ) {
			return '';
		}

		return self::FORMATO . base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Descifra un token, esté en el formato nuevo o en el viejo.
	 *
	 * @param string $data Valor guardado.
	 * @return string Token en claro, o cadena vacía si el valor no es auténtico o no se puede descifrar.
	 */
	private static function decrypt( string $data ): string {
		if ( '' === $data ) {
			return '';
		}

		if ( ! str_starts_with( $data, self::FORMATO ) ) {
			return self::descifrar_formato_viejo( $data );
		}

		$crudo = base64_decode( substr( $data, strlen( self::FORMATO ) ), true );
		if ( false === $crudo || strlen( $crudo ) < 28 ) {
			return '';
		}

		$iv        = substr( $crudo, 0, 12 );
		$tag       = substr( $crudo, 12, 16 );
		$encrypted = substr( $crudo, 28 );

		// GCM comprueba la etiqueta: un valor manipulado NO se descifra, devuelve false.
		$claro = openssl_decrypt( $encrypted, self::METODO, self::clave(), OPENSSL_RAW_DATA, $iv, $tag );

		return false === $claro ? '' : $claro;
	}

	/**
	 * Descifra un valor del formato viejo (CBC con IV fijo). Existe solo para poder migrarlo al leerlo.
	 *
	 * @param string $data Valor guardado con el formato viejo.
	 * @return string Token en claro, o cadena vacía si no se pudo descifrar.
	 */
	private static function descifrar_formato_viejo( string $data ): string {
		$crudo = base64_decode( $data, true );
		if ( false === $crudo ) {
			return '';
		}

		$claro = openssl_decrypt( $crudo, 'aes-256-cbc', self::clave_vieja(), 0, substr( wp_salt( 'nonce' ), 0, 16 ) );

		return false === $claro ? '' : $claro;
	}

	/**
	 * Clave del formato nuevo: derivada (SHA-256) de la MISMA fuente que usaba el viejo.
	 *
	 * Se deriva en vez de guardarse: no hay ningún valor nuevo que configurar y sigue valiendo la constante
	 * de siempre (o la sal de WordPress).
	 */
	private static function clave(): string {
		return hash( 'sha256', self::clave_vieja() . '|convoca-social-v2', true );
	}

	/**
	 * Fuente de la clave: la de siempre, para que los tokens ya guardados sigan descifrándose.
	 */
	private static function clave_vieja(): string {
		return defined( 'CONVOCA_SOCIAL_KEY' ) ? CONVOCA_SOCIAL_KEY : wp_salt( 'auth' );
	}
}
