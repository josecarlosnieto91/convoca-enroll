<?php
/**
 * Default poster templates v2 — Professional visual editorial system.
 *
 * Each template is a full composition system with:
 * - Design tokens (palette, fonts, spacing, radii)
 * - Layer groups for each format
 * - Responsive layout per format
 * - Safe areas, margins, smart fallbacks
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seed data for system templates — v2 schema.
 */
class Template_Defaults {

	public static function seed(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'conv_media_templates';

		// Only seed if no system templates exist
		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_system = 1" );
		if ( $existing > 0 ) {
			return;
		}

		$templates = self::get_defaults();
		foreach ( $templates as $tpl ) {
			$wpdb->replace( $table, $tpl, array( '%s', '%s', '%s', '%s', '%s', '%d' ) );
		}
	}

	public static function get_defaults(): array {
		return array(
			self::nature_classic(),
			self::modern_ngo(),
			self::educational_workshop(),
			self::volunteer_campaign(),
			self::kids_family(),
			self::minimal_corporate(),
			self::full_photo_hero(),
			self::story_focused(),
		);
	}

	/**
	 * Template 1: Nature Classic — Full-bleed photo, green accents, outdoors.
	 */
	private static function nature_classic(): array {
		return array(
			'name'        => 'Nature Classic',
			'slug'        => 'nature-classic',
			'description' => 'Gran imagen protagonista con acentos verdes. Ideal para rutas, naturaleza y actividades al aire libre.',
			'config'      => wp_json_encode( array(
				'version'    => '2.0',
				'design_tokens' => array(
					'palette' => array(
						'primary'   => '#2e7d32',
						'secondary' => '#1b5e20',
						'accent'    => '#8bc34a',
						'light'     => '#e8f5e9',
						'bg'        => '#ffffff',
						'text'      => '#1a1a1a',
						'text_light'=> '#ffffff',
						'overlay'   => 'rgba(0,0,0,0.55)',
					),
					'typography' => array(
						'title'  => array( 'family' => 'Outfit', 'weight' => 700, 'size' => 72, 'color' => '#ffffff', 'tracking' => -0.5 ),
						'subtitle' => array( 'family' => 'Lato', 'weight' => 300, 'size' => 28, 'color' => '#e8f5e9' ),
						'body'   => array( 'family' => 'Lato', 'weight' => 400, 'size' => 26, 'color' => '#ffffff' ),
						'meta'   => array( 'family' => 'Lato', 'weight' => 400, 'size' => 22, 'color' => '#e8f5e9' ),
						'cta'    => array( 'family' => 'Outfit', 'weight' => 600, 'size' => 32, 'color' => '#1b5e20' ),
						'disclaimer' => array( 'family' => 'Lato', 'weight' => 300, 'size' => 14, 'color' => '#666666' ),
					),
					'spacing' => array( 'margin' => 60, 'padding' => 24, 'gap' => 12, 'corner' => 16 ),
					'shadows' => array( 'text' => '0,2,4,rgba(0,0,0,0.3)', 'box' => '0,4,12,rgba(0,0,0,0.15)' ),
				),
				'formats' => array(
					'square'    => array( 'width' => 1080, 'height' => 1080 ),
					'story'     => array( 'width' => 1080, 'height' => 1920 ),
					'facebook'  => array( 'width' => 1200, 'height' => 630 ),
					'banner'    => array( 'width' => 1920, 'height' => 1080 ),
					'portrait'  => array( 'width' => 1080, 'height' => 1350 ),
					'a4'        => array( 'width' => 2480, 'height' => 3508 ),
				),
				'layers' => array(
					// Shared layers (applied to all formats)
					array(
						'id' => 'bg',
						'type' => 'background',
						'gradient' => array( '#2e7d32', '#1b5e20' ),
						'angle' => 135,
						'responsive' => array(
							'story'   => array( 'angle' => 180 ),
							'facebook' => array( 'angle' => 90 ),
						),
					),
					array(
						'id' => 'photo',
						'type' => 'image',
						'fit' => 'cover',
						'opacity' => 0.4,
						'responsive' => array(
							'square'   => array( 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 600 ),
							'story'    => array( 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 1200 ),
							'facebook' => array( 'x' => 0, 'y' => 0, 'w' => 1200, 'h' => 400 ),
							'portrait' => array( 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 700 ),
							'a4'       => array( 'x' => 0, 'y' => 0, 'w' => 2480, 'h' => 1600 ),
						),
					),
					array(
						'id' => 'photo_overlay',
						'type' => 'overlay',
						'gradient' => array( 'rgba(0,0,0,0)', 'rgba(0,0,0,0.65)' ),
						'responsive' => array(
							'square'   => array( 'y' => 400, 'h' => 200 ),
							'story'    => array( 'y' => 800, 'h' => 400 ),
							'facebook' => array( 'y' => 250, 'h' => 150 ),
							'portrait' => array( 'y' => 500, 'h' => 250 ),
						),
					),
					array(
						'id' => 'badge',
						'type' => 'badge',
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 60, 'size' => 36 ),
							'story'    => array( 'x' => 60, 'y' => 80, 'size' => 40 ),
							'facebook' => array( 'x' => 40, 'y' => 40, 'size' => 28 ),
							'portrait' => array( 'x' => 60, 'y' => 60, 'size' => 36 ),
						),
					),
					array(
						'id' => 'title',
						'type' => 'text',
						'ref' => 'title',
						'font' => 'title',
						'max_lines' => 3,
						'auto_shrink' => true,
						'auto_shrink_min' => 32,
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 480, 'w' => 960, 'h' => 220, 'align' => 'left', 'font_size' => 72 ),
							'story'    => array( 'x' => 60, 'y' => 900, 'w' => 960, 'h' => 400, 'align' => 'center', 'font_size' => 96 ),
							'facebook' => array( 'x' => 40, 'y' => 300, 'w' => 1120, 'h' => 180, 'align' => 'left', 'font_size' => 52 ),
							'portrait' => array( 'x' => 60, 'y' => 580, 'w' => 960, 'h' => 280, 'align' => 'left', 'font_size' => 64 ),
						),
					),
					array(
						'id' => 'meta_block',
						'type' => 'text',
						'ref' => 'meta_block',
						'font' => 'meta',
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 700, 'w' => 700, 'h' => 100, 'align' => 'left' ),
							'story'    => array( 'x' => 60, 'y' => 1320, 'w' => 960, 'h' => 120, 'align' => 'center' ),
							'facebook' => array( 'x' => 40, 'y' => 490, 'w' => 800, 'h' => 60, 'align' => 'left' ),
							'portrait' => array( 'x' => 60, 'y' => 860, 'w' => 800, 'h' => 100, 'align' => 'left' ),
						),
					),
					array(
						'id' => 'cta',
						'type' => 'cta',
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 830, 'w' => 360, 'h' => 60, 'align' => 'left' ),
							'story'    => array( 'x' => 240, 'y' => 1500, 'w' => 600, 'h' => 70, 'align' => 'center' ),
							'facebook' => array( 'x' => 40, 'y' => 560, 'w' => 300, 'h' => 50, 'align' => 'left' ),
							'portrait' => array( 'x' => 60, 'y' => 1000, 'w' => 400, 'h' => 60, 'align' => 'left' ),
						),
					),
					array(
						'id' => 'logo',
						'type' => 'logo',
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 930, 'w' => 100, 'h' => 100 ),
							'story'    => array( 'x' => 60, 'y' => 1620, 'w' => 120, 'h' => 120 ),
							'facebook' => array( 'x' => 900, 'y' => 530, 'w' => 80, 'h' => 80 ),
							'portrait' => array( 'x' => 60, 'y' => 1120, 'w' => 100, 'h' => 100 ),
						),
					),
					array(
						'id' => 'qr',
						'type' => 'qr',
						'responsive' => array(
							'square'   => array( 'x' => 880, 'y' => 930, 'size' => 140 ),
							'story'    => array( 'x' => 780, 'y' => 1620, 'size' => 200 ),
							'facebook' => array( 'x' => 1020, 'y' => 530, 'size' => 80 ),
							'portrait' => array( 'x' => 880, 'y' => 1120, 'size' => 140 ),
						),
					),
				),
				'smart' => array(
					'auto_text_shadow' => true,
					'long_title_shrink' => true,
					'badge_from_type' => true,
					'fallback_no_image' => array( 'solid_bg' => true, 'reduce_overlay' => true ),
					'logo_grid_max' => 4,
				),
			) ),
			'is_system' => 1,
		);
	}

	/**
	 * Template 2: Modern NGO — Clean, professional, block colors.
	 */
	private static function modern_ngo(): array {
		return array(
			'name'        => 'Modern NGO',
			'slug'        => 'modern-ngo',
			'description' => 'Estilo profesional con bloques de color y tipografía limpia. Ideal para ONGs y entidades sociales.',
			'config'      => wp_json_encode( array(
				'version'    => '2.0',
				'design_tokens' => array(
					'palette' => array(
						'primary'   => '#1a1a2e',
						'secondary' => '#16213e',
						'accent'    => '#ff8700',
						'light'     => '#e2e2f0',
						'bg'        => '#0f3460',
						'text'      => '#ffffff',
						'text_light'=> '#ffcc80',
					),
					'typography' => array(
						'title'  => array( 'family' => 'Outfit', 'weight' => 800, 'size' => 56, 'color' => '#ffffff', 'tracking' => -0.3 ),
						'body'   => array( 'family' => 'Lato', 'weight' => 400, 'size' => 24, 'color' => '#e2e2f0' ),
						'meta'   => array( 'family' => 'Lato', 'weight' => 400, 'size' => 22, 'color' => '#ffcc80' ),
						'cta'    => array( 'family' => 'Outfit', 'weight' => 700, 'size' => 30, 'color' => '#ffffff' ),
					),
					'spacing' => array( 'margin' => 40, 'padding' => 20, 'gap' => 10, 'corner' => 12 ),
				),
				'formats' => array(
					'square'   => array( 'width' => 1080, 'height' => 1080 ),
					'story'    => array( 'width' => 1080, 'height' => 1920 ),
					'portrait' => array( 'width' => 1080, 'height' => 1350 ),
				),
				'layers' => array(
					array( 'id' => 'bg', 'type' => 'background', 'color' => '#1a1a2e' ),
					array( 'id' => 'accent_bar', 'type' => 'rect', 'color' => '#ff8700',
						'responsive' => array(
							'square'   => array( 'x' => 0, 'y' => 0, 'w' => 16, 'h' => 1080 ),
							'story'    => array( 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 12 ),
							'portrait' => array( 'x' => 0, 'y' => 0, 'w' => 16, 'h' => 1350 ),
						),
					),
					array( 'id' => 'photo', 'type' => 'image', 'fit' => 'cover', 'opacity' => 0.85,
						'responsive' => array(
							'square'   => array( 'x' => 40, 'y' => 40, 'w' => 1000, 'h' => 520, 'border_radius' => 12 ),
							'story'    => array( 'x' => 40, 'y' => 80, 'w' => 1000, 'h' => 800, 'border_radius' => 16 ),
							'portrait' => array( 'x' => 40, 'y' => 40, 'w' => 1000, 'h' => 600, 'border_radius' => 12 ),
						),
					),
					array( 'id' => 'badge', 'type' => 'badge',
						'responsive' => array(
							'square'   => array( 'x' => 70, 'y' => 70, 'size' => 32 ),
							'story'    => array( 'x' => 70, 'y' => 110, 'size' => 36 ),
							'portrait' => array( 'x' => 70, 'y' => 70, 'size' => 32 ),
						),
					),
					array( 'id' => 'title', 'type' => 'text', 'ref' => 'title', 'font' => 'title', 'max_lines' => 3, 'auto_shrink' => true,
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 600, 'w' => 960, 'h' => 150, 'align' => 'left', 'font_size' => 56 ),
							'story'    => array( 'x' => 60, 'y' => 920, 'w' => 960, 'h' => 300, 'align' => 'center', 'font_size' => 72 ),
							'portrait' => array( 'x' => 60, 'y' => 680, 'w' => 960, 'h' => 180, 'align' => 'left', 'font_size' => 52 ),
						),
					),
					array( 'id' => 'meta', 'type' => 'text', 'ref' => 'meta_block', 'font' => 'meta',
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 760, 'w' => 960, 'h' => 80, 'align' => 'left' ),
							'story'    => array( 'x' => 60, 'y' => 1250, 'w' => 960, 'h' => 120, 'align' => 'center' ),
							'portrait' => array( 'x' => 60, 'y' => 880, 'w' => 960, 'h' => 80, 'align' => 'left' ),
						),
					),
					array( 'id' => 'cta', 'type' => 'cta',
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 870, 'w' => 400, 'h' => 60 ),
							'story'    => array( 'x' => 240, 'y' => 1450, 'w' => 600, 'h' => 70 ),
							'portrait' => array( 'x' => 60, 'y' => 1000, 'w' => 400, 'h' => 60 ),
						),
					),
					array( 'id' => 'logo', 'type' => 'logo',
						'responsive' => array(
							'square'   => array( 'x' => 60, 'y' => 960, 'w' => 100, 'h' => 80 ),
							'story'    => array( 'x' => 60, 'y' => 1560, 'w' => 120, 'h' => 100 ),
							'portrait' => array( 'x' => 60, 'y' => 1100, 'w' => 100, 'h' => 80 ),
						),
					),
					array( 'id' => 'qr', 'type' => 'qr',
						'responsive' => array(
							'square'   => array( 'x' => 860, 'y' => 940, 'size' => 160 ),
							'story'    => array( 'x' => 780, 'y' => 1600, 'size' => 200 ),
							'portrait' => array( 'x' => 860, 'y' => 1100, 'size' => 160 ),
						),
					),
				),
				'smart' => array(
					'auto_text_shadow' => false,
					'badge_from_type' => true,
					'fallback_no_image' => array( 'color_shift' => '#16213e' ),
				),
			) ),
			'is_system' => 1,
		);
	}

	// Helper for remaining 6 templates — returns minimal configs
	private static function educational_workshop(): array {
		return self::mini_template( 'Educational Workshop', 'educational-workshop', 'Azules didácticos para talleres y formaciones.', array( '#1565c0', '#0d47a1' ), '#bbdefb' );
	}

	private static function volunteer_campaign(): array {
		return self::mini_template( 'Volunteer Campaign', 'volunteer-campaign', 'Tonos teal solidarios para acciones de voluntariado.', array( '#00695c', '#004d40' ), '#b2dfdb' );
	}

	private static function kids_family(): array {
		return self::mini_template( 'Kids & Family', 'kids-family', 'Colores cálidos y divertidos para actividades infantiles y familiares.', array( '#ff6f00', '#e65100' ), '#ffe0b2' );
	}

	private static function minimal_corporate(): array {
		return self::mini_template( 'Minimal Corporate', 'minimal-corporate', 'Blanco y gris, máxima legibilidad. Ideal para comunicados formales.', array( '#ffffff', '#f5f5f5' ), '#1a1a1a', '#1a1a1a' );
	}

	private static function full_photo_hero(): array {
		return self::mini_template( 'Full Photo Hero', 'full-photo-hero', 'La imagen ocupa todo el fondo. Ideal para actividades con fotografía potente.', array( '#1a1a1a', '#000000' ), '#ffffff' );
	}

	private static function story_focused(): array {
		return self::mini_template( 'Story Focused', 'story-focused', 'Optimizada para Instagram Stories. Vertical, tipografía grande.', array( '#2d2d3a', '#1a1a2e' ), '#ffffff' );
	}

	/**
	 * Generate a minimal but complete template from common defaults.
	 */
	private static function mini_template( string $name, string $slug, string $desc, array $bg, string $accent_text, string $title_color = '#ffffff' ): array {
		return array(
			'name'        => $name,
			'slug'        => $slug,
			'description' => $desc,
			'config'      => wp_json_encode( array(
				'version'       => '2.0',
				'design_tokens' => array(
					'palette'    => array( 'primary' => $bg[0], 'secondary' => $bg[1], 'accent' => '#ff8700', 'text_light' => $accent_text ),
					'typography' => array(
						'title' => array( 'family' => 'Outfit', 'weight' => 700, 'size' => 60, 'color' => $title_color ),
						'meta'  => array( 'family' => 'Lato', 'weight' => 400, 'size' => 24, 'color' => $accent_text ),
						'cta'   => array( 'family' => 'Outfit', 'weight' => 600, 'size' => 30, 'color' => '#ffffff' ),
					),
					'spacing'    => array( 'margin' => 50, 'corner' => 14 ),
				),
				'formats'      => array( 'square' => array( 'width' => 1080, 'height' => 1080 ) ),
				'layers'       => array(
					array( 'id' => 'bg', 'type' => 'background', 'gradient' => $bg, 'angle' => 135 ),
					array( 'id' => 'photo', 'type' => 'image', 'fit' => 'cover', 'opacity' => 0.3,
						'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 600 ),
					array( 'id' => 'overlay', 'type' => 'overlay',
						'gradient' => array( 'rgba(0,0,0,0)', 'rgba(0,0,0,0.6)' ), 'y' => 400, 'h' => 200 ),
					array( 'id' => 'badge', 'type' => 'badge', 'x' => 50, 'y' => 50, 'size' => 34 ),
					array( 'id' => 'title', 'type' => 'text', 'ref' => 'title', 'font' => 'title', 'max_lines' => 3,
						'x' => 50, 'y' => 460, 'w' => 980, 'h' => 200, 'align' => 'left', 'auto_shrink' => true ),
					array( 'id' => 'meta', 'type' => 'text', 'ref' => 'meta_block', 'font' => 'meta',
						'x' => 50, 'y' => 680, 'w' => 700, 'h' => 80, 'align' => 'left' ),
					array( 'id' => 'cta', 'type' => 'cta', 'x' => 50, 'y' => 800, 'w' => 360, 'h' => 56 ),
					array( 'id' => 'logo', 'type' => 'logo', 'x' => 50, 'y' => 920, 'w' => 100, 'h' => 100 ),
					array( 'id' => 'qr', 'type' => 'qr', 'x' => 850, 'y' => 920, 'size' => 140 ),
				),
				'smart'        => array( 'badge_from_type' => true, 'auto_text_shadow' => true ),
			) ),
			'is_system'  => 1,
		);
	}
}
