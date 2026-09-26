<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Media
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
 * QR Code Generator for activity posters.
 *
 * Uses chillerlan/php-qrcode (v6+) — modern, PHP 8.x compatible.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Common\EccLevel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate QR codes pointing to activity blog post, landing, or inscription URL.
 */
class QR_Generator {

	const CACHE_GROUP = 'convoca_qr';

	/**
	 * Generate QR code image for an activity.
	 *
	 * @param int   $actividad_id Activity post ID.
	 * @param array $options      Overrides: { size, color }.
	 * @return string|null File path to generated QR PNG, or null on failure.
	 */
	public static function generate( int $actividad_id, array $options = array() ): ?string {
		$url = self::resolve_url( $actividad_id );
		if ( ! $url ) {
			return null;
		}

		$cache_key = 'qr_' . $actividad_id . '_' . md5( $url . wp_json_encode( $options ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $cached && file_exists( $cached ) ) {
			return $cached;
		}

		$size       = min( max( $options['size'] ?? 300, 100 ), 1000 );
		$upload_dir = wp_upload_dir();
		$qr_dir     = $upload_dir['basedir'] . '/convoca-qr/';

		if ( ! is_dir( $qr_dir ) ) {
			wp_mkdir_p( $qr_dir );
		}

		$filename = 'qr-actividad-' . $actividad_id . '.png';
		$filepath = $qr_dir . $filename;

		if ( ! self::render( $url, $filepath, $size ) ) {
			return null;
		}

		wp_cache_set( $cache_key, $filepath, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $filepath;
	}

	/**
	 * Get QR URL for frontend display.
	 */
	public static function get_url( int $actividad_id, array $options = array() ): ?string {
		$path = self::generate( $actividad_id, $options );
		if ( ! $path ) {
			return null;
		}
		$upload_dir = wp_upload_dir();
		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
	}

	/**
	 * Genera el QR de una URL cualquiera (no solo de la ficha de una actividad).
	 *
	 * Lo usa el check-in: el QR del correo tiene que apuntar a la URL de check-in de esa
	 * inscripción, no a la actividad. Se pinta en local, con la misma biblioteca que el
	 * resto, en vez de pedirle la imagen a un servicio externo —que se llevaría el token
	 * de check-in del socio a un tercero—.
	 *
	 * @param string $clave   Identificador estable para el nombre del fichero (p. ej. «inscripcion-12»).
	 * @param string $url     URL que debe codificar el QR.
	 * @param array  $options Overrides: { size }.
	 * @return string|null Ruta del PNG, o null si no se pudo generar.
	 */
	public static function generate_for_url( string $clave, string $url, array $options = array() ): ?string {
		if ( '' === $url ) {
			return null;
		}

		$cache_key = 'qr_' . md5( $clave . $url . wp_json_encode( $options ) );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $cached && file_exists( $cached ) ) {
			return $cached;
		}

		$size       = min( max( $options['size'] ?? 300, 100 ), 1000 );
		$upload_dir = wp_upload_dir();
		$qr_dir     = $upload_dir['basedir'] . '/convoca-qr/';

		if ( ! is_dir( $qr_dir ) ) {
			wp_mkdir_p( $qr_dir );
		}

		$filepath = $qr_dir . 'qr-' . sanitize_file_name( $clave ) . '-' . substr( md5( $url ), 0, 8 ) . '.png';

		// Si ya está en disco con el mismo contenido, no se vuelve a pintar.
		if ( file_exists( $filepath ) ) {
			wp_cache_set( $cache_key, $filepath, self::CACHE_GROUP, HOUR_IN_SECONDS );
			return $filepath;
		}

		if ( ! self::render( $url, $filepath, $size ) ) {
			return null;
		}

		wp_cache_set( $cache_key, $filepath, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $filepath;
	}

	/**
	 * URL pública del QR de una URL cualquiera.
	 *
	 * @param string $clave   Identificador estable para el fichero.
	 * @param string $url     URL que debe codificar el QR.
	 * @param array  $options Overrides: { size }.
	 * @return string|null URL pública, o null si no se pudo generar.
	 */
	public static function url_for( string $clave, string $url, array $options = array() ): ?string {
		$path = self::generate_for_url( $clave, $url, $options );
		if ( ! $path ) {
			return null;
		}

		$upload_dir = wp_upload_dir();

		return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $path );
	}

	/**
	 * Pinta el PNG del QR. Un solo sitio dibuja códigos: lo comparten la ficha de la
	 * actividad y el enlace de check-in.
	 *
	 * @param string $url      Contenido del QR.
	 * @param string $filepath Fichero de destino.
	 * @param int    $size     Lado aproximado en píxeles.
	 * @return bool
	 */
	private static function render( string $url, string $filepath, int $size ): bool {
		try {
			// Los módulos ocupan unas 33 columnas en una URL: escala 10 da ~330 px.
			$scale = max( 3, (int) round( $size / 33 ) );

			$qrOptions = new QROptions(
				array(
					'outputInterface'  => QRGdImagePNG::class,
					'eccLevel'         => EccLevel::M,
					'scale'            => $scale,
					'addQuietzone'     => true,
					'quietzoneSize'    => 2,
					'outputBase64'     => false,
					'imageTransparent' => false,
				)
			);

			$qrcode = new QRCode( $qrOptions );
			$qrcode->render( $url, $filepath );

			return file_exists( $filepath );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Resolve the best URL for the QR code.
	 */
	private static function resolve_url( int $actividad_id ): ?string {
		$blog_post_id = get_post_meta( $actividad_id, '_convoca_media_blog_post_id', true );
		if ( $blog_post_id && get_post_status( $blog_post_id ) === 'publish' ) {
			return get_permalink( $blog_post_id );
		}

		$landing = get_permalink( $actividad_id );
		if ( $landing ) {
			return $landing;
		}

		$settings = get_option( 'convoca_enroll_settings', array() );
		if ( ! empty( $settings['inscripcion_url'] ) ) {
			return $settings['inscripcion_url'];
		}

		return null;
	}

	/**
	 * Invalidate QR cache for an activity.
	 */
	public static function invalidate( int $actividad_id ): void {
		$upload_dir = wp_upload_dir();
		$qr_file    = $upload_dir['basedir'] . '/convoca-qr/qr-actividad-' . $actividad_id . '.png';
		if ( file_exists( $qr_file ) ) {
			wp_delete_file( $qr_file );
		}
	}
}
