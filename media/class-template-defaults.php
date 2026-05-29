<?php
/**
 * Default poster templates shipped with the plugin.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seed data for system templates.
 */
class Template_Defaults {

	/**
	 * Insert default templates into the DB.
	 */
	public static function seed(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'conv_media_templates';

		$templates = self::get_defaults();
		foreach ( $templates as $tpl ) {
			$wpdb->replace( $table, $tpl, array( '%s', '%s', '%s', '%s', '%s', '%d' ) );
		}
	}

	/**
	 * Return default template definitions as arrays.
	 */
	public static function get_defaults(): array {
		return array(
			// ── Template 1: Nature / outdoor ──
			array(
				'name'        => 'Naturaleza',
				'slug'        => 'naturaleza',
				'description' => 'Plantilla con degradado verde y espacio para imagen grande. Ideal para rutas, senderismo y actividades al aire libre.',
				'config'      => wp_json_encode( self::template_naturaleza() ),
				'is_system'   => 1,
			),
			// ── Template 2: Urbane / social ──
			array(
				'name'        => 'Urbano',
				'slug'        => 'urbano',
				'description' => 'Plantilla moderna con bloques de color. Ideal para talleres, formaciones y eventos en centro social.',
				'config'      => wp_json_encode( self::template_urbano() ),
				'is_system'   => 1,
			),
			// ── Template 3: Minimal ──
			array(
				'name'        => 'Mínima',
				'slug'        => 'minima',
				'description' => 'Plantilla limpia con mucho espacio negativo. Ideal para actividades que requieren máxima legibilidad.',
				'config'      => wp_json_encode( self::template_minima() ),
				'is_system'   => 1,
			),
		);
	}

	/**
	 * Naturaleza template config.
	 */
	private static function template_naturaleza(): array {
		return array(
			'version'     => '1.0',
			'width'       => 1080,
			'height'      => 1080,
			'formats'     => array( 'square' => array( 1080, 1080 ) ),
			'fonts'       => array(
				'title' => array( 'family' => 'Outfit', 'weight' => 700, 'size' => 64, 'color' => '#ffffff' ),
				'meta'  => array( 'family' => 'Lato', 'weight' => 400, 'size' => 28, 'color' => '#e8f5e9' ),
				'cta'   => array( 'family' => 'Outfit', 'weight' => 600, 'size' => 36, 'color' => '#1b5e20' ),
			),
			'layers'      => array(
				array( 'type' => 'background', 'gradient' => array( '#2e7d32', '#1b5e20' ), 'angle' => 135 ),
				array( 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 600, 'opacity' => 0.35, 'fit' => 'cover' ),
				array( 'type' => 'overlay', 'gradient' => array( 'rgba(0,0,0,0)', 'rgba(0,0,0,0.6)' ), 'y' => 400, 'h' => 200 ),
				array( 'type' => 'badge', 'x' => 60, 'y' => 60, 'size' => 40 ),
				array( 'type' => 'text', 'ref' => 'title', 'x' => 60, 'y' => 480, 'w' => 960, 'h' => 160, 'align' => 'left' ),
				array( 'type' => 'text', 'ref' => 'date', 'x' => 60, 'y' => 640, 'w' => 600, 'h' => 40, 'align' => 'left' ),
				array( 'type' => 'text', 'ref' => 'location', 'x' => 60, 'y' => 680, 'w' => 600, 'h' => 40, 'align' => 'left' ),
				array( 'type' => 'logo', 'x' => 60, 'y' => 920, 'w' => 120, 'h' => 120, 'opacity' => 1.0 ),
				array( 'type' => 'qr', 'x' => 880, 'y' => 920, 'size' => 140 ),
				array( 'type' => 'text', 'ref' => 'cta', 'x' => 60, 'y' => 800, 'w' => 400, 'h' => 60, 'align' => 'left' ),
			),
		);
	}

	/**
	 * Urbano template config.
	 */
	private static function template_urbano(): array {
		return array(
			'version'     => '1.0',
			'width'       => 1080,
			'height'      => 1080,
			'formats'     => array( 'square' => array( 1080, 1080 ) ),
			'fonts'       => array(
				'title' => array( 'family' => 'Outfit', 'weight' => 800, 'size' => 56, 'color' => '#ffffff' ),
				'meta'  => array( 'family' => 'Lato', 'weight' => 400, 'size' => 26, 'color' => '#ffcc80' ),
				'cta'   => array( 'family' => 'Outfit', 'weight' => 700, 'size' => 32, 'color' => '#ffffff' ),
			),
			'colors'      => array( 'accent' => '#ff8700', 'secondary' => '#320028', 'bg' => '#1a1a2e' ),
			'layers'      => array(
				array( 'type' => 'background', 'color' => '#1a1a2e' ),
				array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'w' => 20, 'h' => 1080, 'color' => '#ff8700' ),
				array( 'type' => 'image', 'x' => 40, 'y' => 40, 'w' => 1000, 'h' => 500, 'opacity' => 0.9, 'fit' => 'cover', 'border_radius' => 16 ),
				array( 'type' => 'badge', 'x' => 70, 'y' => 70, 'size' => 36 ),
				array( 'type' => 'text', 'ref' => 'title', 'x' => 60, 'y' => 580, 'w' => 960, 'h' => 130, 'align' => 'left' ),
				array( 'type' => 'text', 'ref' => 'date', 'x' => 60, 'y' => 720, 'w' => 960, 'h' => 36, 'align' => 'left' ),
				array( 'type' => 'text', 'ref' => 'location', 'x' => 60, 'y' => 760, 'w' => 960, 'h' => 36, 'align' => 'left' ),
				array( 'type' => 'text', 'ref' => 'cta', 'x' => 60, 'y' => 840, 'w' => 400, 'h' => 60, 'align' => 'left' ),
				array( 'type' => 'logo', 'x' => 60, 'y' => 930, 'w' => 100, 'h' => 100 ),
				array( 'type' => 'qr', 'x' => 860, 'y' => 920, 'size' => 160 ),
			),
		);
	}

	/**
	 * Minima template config.
	 */
	private static function template_minima(): array {
		return array(
			'version'     => '1.0',
			'width'       => 1080,
			'height'      => 1080,
			'formats'     => array( 'square' => array( 1080, 1080 ) ),
			'fonts'       => array(
				'title' => array( 'family' => 'Outfit', 'weight' => 600, 'size' => 72, 'color' => '#1a1a1a' ),
				'meta'  => array( 'family' => 'Lato', 'weight' => 300, 'size' => 28, 'color' => '#666666' ),
				'cta'   => array( 'family' => 'Outfit', 'weight' => 500, 'size' => 34, 'color' => '#ffffff' ),
			),
			'layers'      => array(
				array( 'type' => 'background', 'color' => '#ffffff' ),
				array( 'type' => 'image', 'x' => 60, 'y' => 60, 'w' => 960, 'h' => 540, 'fit' => 'cover', 'border_radius' => 24 ),
				array( 'type' => 'overlay', 'y' => 480, 'h' => 120, 'gradient' => array( 'rgba(255,255,255,0)', 'rgba(255,255,255,1)' ) ),
				array( 'type' => 'badge', 'x' => 90, 'y' => 90, 'size' => 34 ),
				array( 'type' => 'text', 'ref' => 'title', 'x' => 60, 'y' => 600, 'w' => 960, 'h' => 170, 'align' => 'center' ),
				array( 'type' => 'text', 'ref' => 'date', 'x' => 60, 'y' => 780, 'w' => 960, 'h' => 36, 'align' => 'center' ),
				array( 'type' => 'text', 'ref' => 'location', 'x' => 60, 'y' => 816, 'w' => 960, 'h' => 36, 'align' => 'center' ),
				array( 'type' => 'rect', 'x' => 340, 'y' => 890, 'w' => 400, 'h' => 64, 'color' => '#ff8700', 'border_radius' => 32 ),
				array( 'type' => 'text', 'ref' => 'cta', 'x' => 340, 'y' => 890, 'w' => 400, 'h' => 64, 'align' => 'center' ),
				array( 'type' => 'logo', 'x' => 440, 'y' => 960, 'w' => 80, 'h' => 80, 'align' => 'center' ),
			),
		);
	}
}
