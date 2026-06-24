<?php
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

		try {
			// Scale: modules are about 33 wide for a URL, scale=10 gives ~330px
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
			$result = $qrcode->render( $url, $filepath );

			if ( ! file_exists( $filepath ) ) {
				return null;
			}
		} catch ( \Throwable $e ) {
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
	 * Resolve the best URL for the QR code.
	 */
	private static function resolve_url( int $actividad_id ): ?string {
		$blog_post_id = get_post_meta( $actividad_id, '_conv_media_blog_post_id', true );
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
			@unlink( $qr_file );
		}
	}
}
