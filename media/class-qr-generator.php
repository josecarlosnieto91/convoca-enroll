<?php
/**
 * QR Code Generator for activity posters.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

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
	 * Priority URL:
	 * 1. Blog post associated with activity
	 * 2. Public activity landing page
	 * 3. Inscription URL
	 *
	 * @param int   $actividad_id Activity post ID.
	 * @param array $options      Overrides: { size, color, logo_path, margin }.
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

		$size       = $options['size'] ?? 300;
		$color_hex  = $options['color'] ?? '#000000';
		$margin     = $options['margin'] ?? 10;
		$upload_dir = wp_upload_dir();
		$qr_dir     = $upload_dir['basedir'] . '/convoca-qr/';

		if ( ! is_dir( $qr_dir ) ) {
			wp_mkdir_p( $qr_dir );
		}

		// Parse color hex to RGB.
		$color_rgb = self::hex_to_rgb( $color_hex );

		$result = Builder::create()
			->writer( new PngWriter() )
			->data( $url )
			->encoding( new Encoding( 'UTF-8' ) )
			->errorCorrectionLevel( ErrorCorrectionLevel::Medium )
			->size( $size )
			->margin( $margin )
			->roundBlockSizeMode( RoundBlockSizeMode::Margin )
			->foregroundColor( $color_rgb )
			->build();

		$filename = 'qr-actividad-' . $actividad_id . '.png';
		$filepath = $qr_dir . $filename;

		$result->saveToFile( $filepath );

		// Cache.
		wp_cache_set( $cache_key, $filepath, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $filepath;
	}

	/**
	 * Get QR URL (not file path) for frontend display.
	 *
	 * @param int   $actividad_id Activity ID.
	 * @param array $options      Overrides.
	 * @return string|null
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
	 *
	 * @param int $actividad_id Activity ID.
	 * @return string|null
	 */
	private static function resolve_url( int $actividad_id ): ?string {
		// 1. Blog post associated with this activity.
		$blog_post_id = get_post_meta( $actividad_id, '_conv_media_blog_post_id', true );
		if ( $blog_post_id && get_post_status( $blog_post_id ) === 'publish' ) {
			return get_permalink( $blog_post_id );
		}

		// 2. Public activity landing page.
		$landing = get_permalink( $actividad_id );
		if ( $landing ) {
			return $landing;
		}

		// 3. Inscription URL from settings.
		$settings = get_option( 'conv_enroll_settings', array() );
		if ( ! empty( $settings['inscripcion_url'] ) ) {
			return $settings['inscripcion_url'];
		}

		return null;
	}

	/**
	 * Invalidate QR cache for an activity.
	 *
	 * @param int $actividad_id Activity ID.
	 */
	public static function invalidate( int $actividad_id ): void {
		$upload_dir = wp_upload_dir();
		$qr_file    = $upload_dir['basedir'] . '/convoca-qr/qr-actividad-' . $actividad_id . '.png';
		if ( file_exists( $qr_file ) ) {
			@unlink( $qr_file );
		}
	}

	/**
	 * Convert hex color to RGB array for endroid library.
	 *
	 * @param string $hex Hex color (e.g., #ff8700).
	 * @return array { r, g, b }
	 */
	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		);
	}
}
