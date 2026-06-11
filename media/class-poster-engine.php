<?php
/**
 * Poster Engine v5 — Direct GD image renderer for activity posters.
 *
 * Renders deterministic canvas posters without HTML/CSS/PDF pipelines.
 * Features: 6 social formats, 8 templates, text centering, auto-shrink,
 * text shadow for readability, no-image placeholders, accurate layout.
 *
 * @package Convoca\Enroll\Media
 * @version 5.0.0
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Poster_Engine {

	const CACHE_DIR       = 'convoca-posters';
	const FONT_MONTSERRAT = 'Montserrat';
	const FONT_PLAYFAIR   = 'PlayfairDisplay';

	/**
	 * Render posters for one or more formats.
	 *
	 * @param int    $actividad_id Activity post ID.
	 * @param string $template_slug Template slug.
	 * @param array  $overrides Supports format, formats, image_id, force, export.
	 * @return array|\WP_Error
	 */
	public static function render( int $actividad_id, string $template_slug = 'nature-classic', array $overrides = array() ): array|\WP_Error {
		if ( ! extension_loaded( 'gd' ) ) {
			return new \WP_Error( 'gd_missing', __( 'GD no está disponible. Activa la extensión PHP GD para generar carteles.', 'convoca-enroll' ) );
		}

		$formats = self::normalize_formats( $overrides );
		$export  = self::normalize_export( $overrides['export'] ?? 'png' );
		$force   = ! empty( $overrides['force'] );
		$upload  = wp_upload_dir();
		$dir     = trailingslashit( $upload['basedir'] ) . self::CACHE_DIR;
		$urlbase = trailingslashit( $upload['baseurl'] ) . self::CACHE_DIR;
		wp_mkdir_p( $dir );

		$files  = array();
		$cached = true;

		foreach ( $formats as $format ) {
			$data = self::activity_data( $actividad_id, $template_slug, $format, $overrides );
			if ( is_wp_error( $data ) ) {
				return $data;
			}

			$hash = substr(
				md5(
					wp_json_encode(
						array(
							get_post_modified_time( 'U', true, $actividad_id ),
							$data['image_id'],
							$data['title'],
							$data['date'],
							$data['time'],
							$data['location'],
							$data['price'],
						)
					)
				),
				0,
				10
			);
			$file = sprintf( 'poster-%d-%s-%s-%s.%s', $actividad_id, sanitize_title( $template_slug ), $format, $hash, $export );
			$path = trailingslashit( $dir ) . $file;

			if ( ! $force && file_exists( $path ) ) {
				$files[ $format ] = $path;
				continue;
			}

			$result = self::draw( $data, $path, $export );
			if ( is_wp_error( $result ) ) {
				Media_Logger::log( 'poster', $actividad_id, 'render', 'error', array( 'format' => $format, 'error' => $result->get_error_message() ) );
				return $result;
			}

			$cached           = false;
			$files[ $format ] = $path;
		}

		$first = reset( $files );
		Media_Logger::log(
			'poster',
			$actividad_id,
			'render',
			'success',
			array(
				'template' => $template_slug,
				'formats'  => array_keys( $files ),
				'cached'   => $cached,
			)
		);

		return array(
			'files'  => $files,
			'url'    => $first ? str_replace( $dir, $urlbase, $first ) : '',
			'cached' => $cached,
		);
	}

	/* ─────────────────────────────────────────────
	 *  FORMAT HELPERS
	 * ───────────────────────────────────────────── */

	private static function normalize_formats( array $overrides ): array {
		$formats = ! empty( $overrides['formats'] ) && is_array( $overrides['formats'] )
			? $overrides['formats']
			: array( $overrides['format'] ?? 'square' );
		$allowed = array_keys( self::all_format_dimensions() );
		$formats = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $formats ),
					static fn( $f ) => in_array( $f, $allowed, true )
				)
			)
		);
		return $formats ?: array( 'square' );
	}

	private static function normalize_export( string $export ): string {
		$export = strtolower( sanitize_key( $export ) );
		return in_array( $export, array( 'png', 'jpg', 'jpeg', 'webp' ), true ) ? $export : 'png';
	}

	public static function format_dimensions( string $format ): array {
		$map = self::all_format_dimensions();
		return $map[ $format ] ?? $map['square'];
	}

	public static function all_format_dimensions(): array {
		return array(
			'square'   => array( 'width' => 1080, 'height' => 1080, 'label' => 'Instagram cuadrado 1:1' ),
			'portrait' => array( 'width' => 1080, 'height' => 1350, 'label' => 'Instagram vertical 4:5' ),
			'story'    => array( 'width' => 1080, 'height' => 1920, 'label' => 'Stories/Reels 9:16' ),
			'facebook' => array( 'width' => 1200, 'height' => 630, 'label' => 'Facebook/link 1.91:1' ),
			'banner'   => array( 'width' => 1920, 'height' => 1080, 'label' => 'Banner 16:9' ),
			'a4'       => array( 'width' => 2480, 'height' => 3508, 'label' => 'A4 impresión 300dpi' ),
		);
	}

	/* ─────────────────────────────────────────────
	 *  ACTIVITY DATA EXTRACTION
	 * ───────────────────────────────────────────── */

	private static function activity_data( int $id, string $template_slug, string $format, array $overrides ): array|\WP_Error {
		$post = get_post( $id );
		if ( ! $post || 'actividad' !== $post->post_type ) {
			return new \WP_Error( 'invalid_activity', __( 'Actividad no encontrada o tipo incorrecto.', 'convoca-enroll' ) );
		}

		$start_raw = self::meta_first( $id, array( '_conv_fecha_inicio', 'fecha_inicio', 'fecha' ) );
		$end_raw   = self::meta_first( $id, array( '_conv_fecha_fin', 'fecha_fin' ) );
		$time_raw  = self::meta_first( $id, array( 'hora', '_conv_hora' ) );

		list( $start_date, $start_time ) = self::split_datetime( $start_raw );
		list( $end_date, $end_time )     = self::split_datetime( $end_raw );
		$explicit_time                   = $time_raw ?: $start_time;

		$type_slug = self::meta_first( $id, array( 'tipo_actividad', '_conv_tipo_actividad', 'tipo', '_conv_tipo' ) );
		$style     = self::event_style( $type_slug );
		$image_id  = absint( $overrides['image_id'] ?? 0 );

		if ( $image_id ) {
			update_post_meta( $id, '_conv_poster_image_id', $image_id );
		}
		$image_id = $image_id ?: absint( get_post_meta( $id, '_conv_poster_image_id', true ) );
		$image_id = $image_id ?: (int) get_post_thumbnail_id( $id );
		$image_id = $image_id ?: self::first_image_id( $id );

		$dimensions = self::format_dimensions( $format );
		$qr_path    = QR_Generator::generate( $id, array( 'size' => 540 ) );
		$logo_id    = absint( get_theme_mod( 'custom_logo' ) );
		$logo_path  = $logo_id ? get_attached_file( $logo_id ) : '';

		return array(
			'id'         => $id,
			'template'   => sanitize_key( $template_slug ),
			'format'     => $format,
			'w'          => $dimensions['width'],
			'h'          => $dimensions['height'],
			'title'      => wp_strip_all_tags( get_the_title( $post ) ),
			'subtitle'   => wp_strip_all_tags( $post->post_excerpt ?: self::plain_trim( $post->post_content, 150 ) ),
			'date'       => self::date_label( $start_date, $end_date ),
			'time'       => self::time_label( $start_date, $end_date, $explicit_time ),
			'location'   => wp_strip_all_tags( self::meta_first( $id, array( '_conv_lugar', 'lugar', 'ubicacion', '_conv_ubicacion' ) ) ),
			'places'     => absint( self::meta_first( $id, array( '_conv_plazas_totales', 'plazas_totales' ) ) ),
			'price'      => self::price_label( $id ),
			'type_label' => $style['label'],
			'type_color' => $style['color'],
			'org_name'   => get_bloginfo( 'name' ),
			'image_id'   => $image_id,
			'image_path' => $image_id ? get_attached_file( $image_id ) : '',
			'qr_path'    => $qr_path && file_exists( $qr_path ) ? $qr_path : '',
			'logo_path'  => $logo_path && file_exists( $logo_path ) ? $logo_path : '',
			'palette'    => self::palette( $template_slug, $style['color'] ),
		);
	}

	private static function split_datetime( string $raw ): array {
		$raw = trim( $raw );
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}(?::\d{2})?)/', $raw, $m ) ) {
			return array( $m[1], $m[2] );
		}
		return array( $raw, '' );
	}

	/* ─────────────────────────────────────────────
	 *  LABEL HELPERS
	 * ───────────────────────────────────────────── */

	private static function date_label( string $start, string $end ): string {
		$st = $start ? strtotime( $start ) : false;
		$et = $end ? strtotime( $end ) : false;
		if ( ! $st ) {
			return '';
		}
		$out = date_i18n( 'j F Y', $st );
		return $et && date_i18n( 'Y-m-d', $st ) !== date_i18n( 'Y-m-d', $et )
			? $out . ' – ' . date_i18n( 'j F Y', $et )
			: $out;
	}

	private static function time_label( string $start, string $end, string $time ): string {
		if ( $time ) {
			return str_replace( '-', '–', $time );
		}
		$st = $start ? strtotime( $start ) : false;
		$et = $end ? strtotime( $end ) : false;
		if ( ! $st ) {
			return '';
		}
		$out = date_i18n( 'H:i', $st );
		return $et && $et !== $st ? $out . ' – ' . date_i18n( 'H:i', $et ) : $out;
	}

	private static function price_label( int $id ): string {
		$raw = self::meta_first( $id, array( '_conv_precio_socio', '_conv_precio_general', 'precio_socio', 'precio' ) );
		$val = (float) str_replace( ',', '.', $raw );
		return $val > 0 ? number_format_i18n( $val, 2 ) . ' €' : __( 'Gratuito', 'convoca-enroll' );
	}

	/* ─────────────────────────────────────────────
	 *  META & STYLE
	 * ───────────────────────────────────────────── */

	private static function meta_first( int $id, array $keys ): string {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $id, $key, true );
			if ( '' !== $value && null !== $value && array() !== $value ) {
				return is_array( $value )
					? implode( ', ', array_map( 'wp_strip_all_tags', $value ) )
					: (string) $value;
			}
		}
		return '';
	}

	private static function first_image_id( int $id ): int {
		$gallery = get_post_meta( $id, 'galeria_fotos', true );
		if ( is_array( $gallery ) && $gallery ) {
			$first = reset( $gallery );
			return is_numeric( $first ) ? (int) $first : (int) attachment_url_to_postid( $first );
		}
		$post = get_post( $id );
		if ( $post && preg_match( '/wp-image-(\d+)/', $post->post_content, $m ) ) {
			return (int) $m[1];
		}
		return 0;
	}

	private static function plain_trim( string $html, int $limit ): string {
		$text = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) );
		return mb_strlen( $text ) > $limit
			? mb_substr( $text, 0, $limit - 1 ) . '…'
			: $text;
	}

	private static function event_style( string $type ): array {
		if ( $type && class_exists( '\Convoca\Core\Event_Style_Registry' ) ) {
			$style = \Convoca\Core\Event_Style_Registry::get( $type );
			if ( is_array( $style ) ) {
				return array(
					'label' => $style['label'] ?? ucfirst( $type ),
					'color' => $style['color'] ?? '#5fa65a',
				);
			}
		}
		return array(
			'label' => $type
				? ucfirst( str_replace( array( '-', '_' ), ' ', $type ) )
				: __( 'Actividad', 'convoca-enroll' ),
			'color' => '#5fa65a',
		);
	}

	/* ─────────────────────────────────────────────
	 *  PALETTE
	 * ───────────────────────────────────────────── */

	private static function palette( string $template, string $accent ): array {
		$map = array(
			'nature-classic'       => array( '#173b25', '#07150e', '#132018', '#4a5c50', '#5fa65a' ),
			'modern-ngo'           => array( '#10213a', '#07111f', '#111827', '#4b5563', '#ff8700' ),
			'educational-workshop' => array( '#243b73', '#111827', '#111827', '#475569', '#3b82f6' ),
			'volunteer-campaign'   => array( '#4a1d31', '#1a0b12', '#201018', '#614455', '#e84a5f' ),
			'kids-family'          => array( '#0f766e', '#042f2e', '#10231f', '#4b635d', '#f59e0b' ),
			'full-photo-hero'      => array( '#1f2937', '#030712', '#111827', '#4b5563', '#22c55e' ),
			'story-focused'        => array( '#312e81', '#111827', '#111827', '#475569', '#a78bfa' ),
			'minimal-corporate'    => array( '#334155', '#0f172a', '#111827', '#475569', '#0ea5e9' ),
		);
		$v      = $map[ sanitize_key( $template ) ] ?? $map['nature-classic'];
		$accent_color = $accent ?: $v[4];
		$accent_text  = self::is_light_color( $accent_color ) ? '#1a1a1a' : '#ffffff';
		return array(
			'bg'          => $v[0],
			'deep'        => $v[1],
			'text'        => $v[2],
			'muted'       => $v[3],
			'accent'      => $accent_color,
			'accent_text' => $accent_text,
			'panel'       => 'rgba(255,255,255,0.94)',
		);
	}

	private static function is_light_color( string $hex ): bool {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
		return $luminance > 180;
	}

	/* ─────────────────────────────────────────────
	 *  MAIN DRAWING PIPELINE
	 * ───────────────────────────────────────────── */

	private static function draw( array $d, string $path, string $export ): bool|\WP_Error {
		$img = imagecreatetruecolor( $d['w'], $d['h'] );
		if ( ! $img ) {
			return new \WP_Error( 'canvas_error', __( 'No se pudo crear el lienzo del cartel.', 'convoca-enroll' ) );
		}
		imagealphablending( $img, true );
		imagesavealpha( $img, true );

		$p = $d['palette'];
		$l = self::layout( $d['format'], $d['w'], $d['h'] );

		// 1. Background gradient
		self::gradient( $img, $d['w'], $d['h'], $p['bg'], $p['deep'] );

		// 2. Photo zone
		$has_photo = self::has_image( $d['image_path'] );
		if ( $has_photo ) {
			self::photo( $img, $d['image_path'], $l['photo'] );
			self::photo_overlay( $img, $l['photo'], $p['bg'] );
		} else {
			self::placeholder( $img, $l['photo'], $p );
		}

		// 3. Semi-transparent panel for text
		self::round_rect( $img, $l['panel'], self::color( $img, $p['panel'] ), $l['radius'] );

		// 4. Badge
		self::badge( $img, $d, $l['badge'] );

		// 5. Title (with auto-shrink)
		$title_size = self::auto_shrink_size(
			$d['title'],
			self::font( self::FONT_PLAYFAIR ),
			$l['title']['w'],
			$l['title']['h'],
			$l['title_size'],
			1.05
		);
		self::text_box( $img, $d['title'], $l['title'], self::font( self::FONT_PLAYFAIR ), $p['text'], $title_size, 1.05, 'left', true );

		// 6. Subtitle
		if ( $d['subtitle'] ) {
			self::text_box( $img, $d['subtitle'], $l['subtitle'], self::font( self::FONT_MONTSERRAT ), $p['muted'], $l['subtitle_size'], 1.18, 'left' );
		}

		// 7. Meta block
		$meta_lines = array_filter(
			array(
				$d['date'] ? $d['date'] : '',
				$d['time'] ? $d['time'] : '',
			)
		);
		if ( $d['location'] ) {
			$meta_lines[] = '📍 ' . $d['location'];
		}
		if ( $d['places'] ) {
			$meta_lines[] = sprintf( _n( '%d plaza', '%d plazas', $d['places'], 'convoca-enroll' ), $d['places'] );
		}
		if ( $d['price'] ) {
			$meta_lines[] = '💰 ' . $d['price'];
		}
		self::text_box( $img, implode( "\n", $meta_lines ), $l['meta'], self::font( self::FONT_MONTSERRAT ), $p['text'], $l['meta_size'], 1.28, 'left' );

		// 8. CTA button
		self::cta( $img, $d, $l['cta'] );

		// 9. QR code
		self::qr( $img, $d['qr_path'], $l['qr'] );

		// 10. Logo
		self::logo( $img, $d, $l['logo'] );

		$ok = match ( $export ) {
			'jpg', 'jpeg' => imagejpeg( $img, $path, 92 ),
			'webp'        => function_exists( 'imagewebp' ) ? imagewebp( $img, $path, 92 ) : false,
			default       => imagepng( $img, $path, 6 ),
		};
		imagedestroy( $img );

		if ( ! $ok || ! file_exists( $path ) || filesize( $path ) < 100 ) {
			return new \WP_Error( 'save_error', __( 'No se pudo guardar el cartel generado.', 'convoca-enroll' ) );
		}
		return true;
	}

	/* ─────────────────────────────────────────────
	 *  LAYOUT CALCULATION
	 * ───────────────────────────────────────────── */

	private static function layout( string $format, int $w, int $h ): array {
		$landscape = $w > $h;
		$min_dim   = min( $w, $h );
		$m         = (int) round( $min_dim * 0.055 );
		$gap       = (int) round( $min_dim * 0.028 );
		$radius    = (int) round( $min_dim * 0.028 );

		if ( $landscape ) {
			return self::layout_landscape( $w, $h, $m, $gap, $radius, $format );
		}

		return self::layout_portrait( $w, $h, $m, $gap, $radius, $format );
	}

	private static function layout_landscape( int $w, int $h, int $m, int $gap, int $radius, string $format ): array {
		$photo_w  = (int) round( $w * 0.46 );
		$photo    = array( 'x' => $w - $m - $photo_w, 'y' => $m, 'w' => $photo_w, 'h' => $h - 2 * $m );
		$panel_w  = $w - $photo_w - 3 * $m;
		$panel    = array( 'x' => $m, 'y' => $m, 'w' => $panel_w, 'h' => $h - 2 * $m );

		$badge_h      = 56;
		$available_h  = $panel['h'] - 2 * $gap;
		$title_h      = (int) round( $available_h * 0.35 );
		$subtitle_h   = (int) round( $available_h * 0.15 );
		$cta_h        = 60;
		$meta_h       = $available_h - $badge_h - $title_h - $subtitle_h - $cta_h - 3 * $gap;
		$meta_h       = max( 40, $meta_h );

		$badge_y    = $panel['y'] + $gap;
		$title_y    = $badge_y + $badge_h + $gap;
		$subtitle_y = $title_y + $title_h + $gap;
		$meta_y     = $subtitle_y + $subtitle_h + $gap;
		$cta_y      = $panel['y'] + $panel['h'] - $gap - $cta_h;

		$qr_size  = (int) round( min( $w, $h ) * 0.12 );
		$logo_size = (int) round( min( $w, $h ) * 0.06 );

		return array(
			'photo'    => $photo,
			'panel'    => $panel,
			'radius'   => $radius,
			'badge'    => array( 'x' => $panel['x'] + $gap, 'y' => $badge_y, 'w' => min( 460, $panel['w'] - 2 * $gap ), 'h' => $badge_h ),
			'title'    => array( 'x' => $panel['x'] + $gap, 'y' => $title_y, 'w' => $panel['w'] - 2 * $gap, 'h' => $title_h ),
			'subtitle' => array( 'x' => $panel['x'] + $gap, 'y' => $subtitle_y, 'w' => $panel['w'] - 2 * $gap, 'h' => $subtitle_h ),
			'meta'     => array( 'x' => $panel['x'] + $gap, 'y' => $meta_y, 'w' => $panel['w'] - 2 * $gap, 'h' => $meta_h ),
			'cta'      => array( 'x' => $panel['x'] + $gap, 'y' => $cta_y, 'w' => min( 390, (int) ( $panel['w'] * 0.55 ) ), 'h' => $cta_h ),
			'qr'       => array( 'x' => $photo['x'] + $photo['w'] - $m - $qr_size, 'y' => $photo['y'] + $photo['h'] - $m - $qr_size, 'size' => $qr_size ),
			'logo'     => array( 'x' => $panel['x'] + $panel['w'] - $gap - $logo_size, 'y' => $panel['y'] + $gap, 'size' => $logo_size ),
			'title_size'    => (int) round( min( $w, $h ) * 0.078 ),
			'subtitle_size' => max( 18, (int) round( min( $w, $h ) * 0.028 ) ),
			'meta_size'     => max( 20, (int) round( min( $w, $h ) * 0.030 ) ),
		);
	}

	private static function layout_portrait( int $w, int $h, int $m, int $gap, int $radius, string $format ): array {
		$photo_ratio = match ( $format ) {
			'story'   => 0.48,
			'a4'      => 0.45,
			'portrait' => 0.44,
			default   => 0.45,
		};
		$photo_h  = (int) round( $h * $photo_ratio );
		$photo    = array( 'x' => 0, 'y' => 0, 'w' => $w, 'h' => $photo_h );

		$overlap  = (int) round( min( $w, $h ) * 0.055 );
		$panel_y  = $photo_h - $overlap;
		$panel    = array( 'x' => $m, 'y' => $panel_y, 'w' => $w - 2 * $m, 'h' => $h - $panel_y - $m );

		$qr_size   = (int) round( min( $w, $h ) * ( 'story' === $format ? 0.16 : 0.12 ) );
		$logo_size = (int) round( min( $w, $h ) * ( 'story' === $format ? 0.075 : 0.065 ) );

		$font_base = max( 42, (int) round( min( $w, $h ) * ( 'story' === $format ? 0.065 : 0.060 ) ) );

		$available_h      = $panel['h'] - 2 * $gap;
		$bottom_zone_h    = max( $qr_size, $logo_size ) + $gap;
		$text_available_h = $available_h - $bottom_zone_h;

		$badge_h    = (int) round( min( $w, $h ) * 0.065 );
		$title_h    = (int) round( $text_available_h * 0.38 );
		$subtitle_h = (int) round( $text_available_h * 0.12 );
		$cta_h      = (int) round( min( $w, $h ) * 0.075 );
		$meta_h     = max( 40, $text_available_h - $badge_h - $title_h - $subtitle_h - $cta_h - 3 * $gap );

		$badge_y    = $panel['y'] + $gap;
		$title_y    = $badge_y + $badge_h + $gap;
		$subtitle_y = $title_y + $title_h + $gap;
		$meta_y     = $subtitle_y + $subtitle_h + $gap;
		$cta_y      = $panel['y'] + $panel['h'] - $gap - $bottom_zone_h - $gap - $cta_h;

		$qr_logo_y  = $panel['y'] + $panel['h'] - $gap - max( $qr_size, $logo_size );

		return array(
			'photo'     => $photo,
			'panel'     => $panel,
			'radius'    => $radius,
			'badge'     => array( 'x' => $panel['x'] + $gap, 'y' => $badge_y, 'w' => min( $panel['w'] - 2 * $gap, 500 ), 'h' => $badge_h ),
			'title'     => array( 'x' => $panel['x'] + $gap, 'y' => $title_y, 'w' => $panel['w'] - 2 * $gap, 'h' => $title_h ),
			'subtitle'  => array( 'x' => $panel['x'] + $gap, 'y' => $subtitle_y, 'w' => $panel['w'] - 2 * $gap, 'h' => $subtitle_h ),
			'meta'      => array( 'x' => $panel['x'] + $gap, 'y' => $meta_y, 'w' => $panel['w'] - $qr_size - 3 * $gap, 'h' => $meta_h ),
			'cta'       => array( 'x' => $panel['x'] + $gap, 'y' => $cta_y, 'w' => min( 480, (int) round( $panel['w'] * 0.48 ) ), 'h' => $cta_h ),
			'qr'        => array( 'x' => $panel['x'] + $panel['w'] - $gap - $qr_size, 'y' => $qr_logo_y, 'size' => $qr_size ),
			'logo'      => array( 'x' => $panel['x'] + $panel['w'] - $gap - $logo_size, 'y' => $panel['y'] + $gap, 'size' => $logo_size ),
			'title_size'    => $font_base,
			'subtitle_size' => max( 20, (int) round( min( $w, $h ) * 0.028 ) ),
			'meta_size'     => max( 22, (int) round( min( $w, $h ) * 0.030 ) ),
		);
	}

	/* ─────────────────────────────────────────────
	 *  RENDERING PRIMITIVES
	 * ───────────────────────────────────────────── */

	private static function has_image( string $path ): bool {
		return $path && file_exists( $path ) && filesize( $path ) > 100;
	}

	private static function gradient( $img, int $w, int $h, string $from, string $to ): void {
		$f = sscanf( ltrim( $from, '#' ), '%02x%02x%02x' );
		$t = sscanf( ltrim( $to, '#' ), '%02x%02x%02x' );
		for ( $y = 0; $y < $h; $y++ ) {
			$ratio = $y / max( 1, $h - 1 );
			$r = (int) ( $f[0] + ( $t[0] - $f[0] ) * $ratio );
			$g = (int) ( $f[1] + ( $t[1] - $f[1] ) * $ratio );
			$b = (int) ( $f[2] + ( $t[2] - $f[2] ) * $ratio );
			imageline( $img, 0, $y, $w, $y, imagecolorallocate( $img, $r, $g, $b ) );
		}
	}

	private static function load_image( string $path ) {
		$info = $path ? @getimagesize( $path ) : false;
		if ( ! $info ) {
			return null;
		}
		return match ( $info[2] ) {
			IMAGETYPE_JPEG => imagecreatefromjpeg( $path ),
			IMAGETYPE_PNG  => imagecreatefrompng( $path ),
			IMAGETYPE_WEBP => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : null,
			default        => null,
		};
	}

	private static function photo( $canvas, string $path, array $r ): void {
		$src = self::load_image( $path );
		if ( ! $src ) {
			return;
		}
		$sw = imagesx( $src );
		$sh = imagesy( $src );
		$sc = max( $r['w'] / $sw, $r['h'] / $sh );
		$cw = (int) round( $r['w'] / $sc );
		$ch = (int) round( $r['h'] / $sc );
		$sx = (int) max( 0, ( $sw - $cw ) / 2 );
		$sy = (int) max( 0, ( $sh - $ch ) / 2 );
		imagecopyresampled( $canvas, $src, $r['x'], $r['y'], $sx, $sy, $r['w'], $r['h'], $cw, $ch );
		imagedestroy( $src );
	}

	private static function photo_overlay( $img, array $r, string $color_hex ): void {
		$overlay_h = (int) round( $r['h'] * 0.40 );
		$c         = sscanf( ltrim( $color_hex, '#' ), '%02x%02x%02x' );
		for ( $y = $r['h'] - $overlay_h; $y < $r['h']; $y++ ) {
			$ratio = ( $y - ( $r['h'] - $overlay_h ) ) / max( 1, $overlay_h );
			$alpha = (int) round( $ratio * 80 );
			$color = imagecolorallocatealpha( $img, $c[0], $c[1], $c[2], $alpha );
			imageline( $img, $r['x'], $r['y'] + $y, $r['x'] + $r['w'], $r['y'] + $y, $color );
		}
	}

	private static function placeholder( $img, array $r, array $p ): void {
		$bg_color = self::color( $img, $p['deep'] );
		imagefilledrectangle( $img, $r['x'], $r['y'], $r['x'] + $r['w'], $r['y'] + $r['h'], $bg_color );

		$cx     = $r['x'] + (int) round( $r['w'] / 2 );
		$cy     = $r['y'] + (int) round( $r['h'] / 2 );
		$cr     = (int) round( min( $r['w'], $r['h'] ) * 0.18 );
		$accent = self::color( $img, $p['accent'] );
		imagefilledellipse( $img, $cx, $cy, $cr * 2, $cr * 2, $accent );

		$inner_r = (int) round( $cr * 0.65 );
		$light   = self::color( $img, $p['bg'] );
		imagefilledellipse( $img, $cx, $cy, $inner_r * 2, $inner_r * 2, $light );

		$font_path = self::font( self::FONT_MONTSERRAT );
		if ( $font_path ) {
			$icon_size = (int) round( $cr * 0.65 );
			$icon_text = '📅';
			$bbox      = @imagettfbbox( $icon_size, 0, $font_path, $icon_text );
			$tw        = $bbox ? abs( $bbox[2] - $bbox[0] ) : $icon_size;
			$tx        = $cx - (int) round( $tw / 2 );
			$ty        = $cy + (int) round( $icon_size * 0.35 );
			imagettftext( $img, $icon_size, 0, $tx, $ty, self::color( $img, $p['accent'] ), $font_path, $icon_text );
		}

		$line_color = self::color( $img, $p['muted'] );
		$line_x     = $r['x'] + $r['w'] - (int) round( $r['w'] * 0.15 );
		$line_y     = $r['y'] + $r['h'] - (int) round( $r['h'] * 0.08 );
		$line_w     = (int) round( $r['w'] * 0.08 );
		imageline( $img, $line_x, $line_y, $line_x + $line_w, $line_y, $line_color );
		imageline( $img, $line_x, $line_y + 4, $line_x + (int) round( $line_w * 0.7 ), $line_y + 4, $line_color );
	}

	private static function round_rect( $img, array $r, $color, int $radius ): void {
		$x = $r['x']; $y = $r['y']; $w = $r['w']; $h = $r['h'];
		if ( $radius > min( $w, $h ) / 2 ) {
			$radius = (int) round( min( $w, $h ) / 2 );
		}
		imagefilledrectangle( $img, $x + $radius, $y, $x + $w - $radius, $y + $h, $color );
		imagefilledrectangle( $img, $x, $y + $radius, $x + $w, $y + $h - $radius, $color );
		imagefilledellipse( $img, $x + $radius, $y + $radius, 2 * $radius, 2 * $radius, $color );
		imagefilledellipse( $img, $x + $w - $radius, $y + $radius, 2 * $radius, 2 * $radius, $color );
		imagefilledellipse( $img, $x + $radius, $y + $h - $radius, 2 * $radius, 2 * $radius, $color );
		imagefilledellipse( $img, $x + $w - $radius, $y + $h - $radius, 2 * $radius, 2 * $radius, $color );
	}

	/* ─────────────────────────────────────────────
	 *  ELEMENT DRAWING
	 * ───────────────────────────────────────────── */

	private static function badge( $img, array $d, array $r ): void {
		$radius   = (int) round( $r['h'] / 2 );
		$bg_color = self::color( $img, $d['palette']['accent'] );
		self::round_rect( $img, $r, $bg_color, $radius );

		$text  = mb_strtoupper( $d['type_label'] );
		$size  = (int) round( $r['h'] * 0.36 );
		$font  = self::font( self::FONT_MONTSERRAT );

		$bbox = $font ? @imagettfbbox( $size, 0, $font, $text ) : false;
		$tw   = $bbox ? abs( $bbox[2] - $bbox[0] ) : strlen( $text ) * 6;
		$tx   = $r['x'] + (int) round( ( $r['w'] - $tw ) / 2 );
		$ty   = $r['y'] + (int) round( ( $r['h'] + $size * 0.8 ) / 2 );

		if ( $font && function_exists( 'imagettftext' ) ) {
			imagettftext( $img, $size, 0, $tx, $ty, self::color( $img, $d['palette']['accent_text'] ), $font, $text );
		} else {
			imagestring( $img, 5, $tx, $ty - $size, $text, self::color( $img, $d['palette']['accent_text'] ) );
		}
	}

	private static function cta( $img, array $d, array $r ): void {
		$radius   = (int) round( $r['h'] / 2 );
		$bg_color = self::color( $img, $d['palette']['accent'] );
		self::round_rect( $img, $r, $bg_color, $radius );

		$text = __( 'Apúntate', 'convoca-enroll' ) . ' · ' . $d['price'];
		$size = (int) round( $r['h'] * 0.34 );
		$font = self::font( self::FONT_MONTSERRAT );

		$bbox = $font ? @imagettfbbox( $size, 0, $font, $text ) : false;
		$tw   = $bbox ? abs( $bbox[2] - $bbox[0] ) : strlen( $text ) * 6;
		$tx   = $r['x'] + (int) round( ( $r['w'] - $tw ) / 2 );
		$ty   = $r['y'] + (int) round( ( $r['h'] + $size * 0.8 ) / 2 );

		if ( $font && function_exists( 'imagettftext' ) ) {
			imagettftext( $img, $size, 0, $tx, $ty, self::color( $img, $d['palette']['accent_text'] ), $font, $text );
		} else {
			imagestring( $img, 5, $tx, $ty - $size, $text, self::color( $img, $d['palette']['accent_text'] ) );
		}
	}

	private static function qr( $img, string $path, array $r ): void {
		$qr = self::load_image( $path );
		if ( ! $qr ) {
			return;
		}
		$pad = max( 8, (int) round( $r['size'] * 0.08 ) );
		self::round_rect(
			$img,
			array( 'x' => $r['x'] - $pad, 'y' => $r['y'] - $pad, 'w' => $r['size'] + 2 * $pad, 'h' => $r['size'] + 2 * $pad ),
			self::color( $img, '#ffffff' ),
			12
		);
		imagecopyresampled( $img, $qr, $r['x'], $r['y'], 0, 0, $r['size'], $r['size'], imagesx( $qr ), imagesy( $qr ) );
		imagedestroy( $qr );
	}

	private static function logo( $img, array $d, array $r ): void {
		$logo = self::load_image( $d['logo_path'] );
		if ( $logo ) {
			imagecopyresampled( $img, $logo, $r['x'], $r['y'], 0, 0, $r['size'], $r['size'], imagesx( $logo ), imagesy( $logo ) );
			imagedestroy( $logo );
			return;
		}
		self::text_box(
			$img,
			$d['org_name'],
			array( 'x' => $r['x'] - 220, 'y' => $r['y'] + (int) round( $r['size'] * 0.1 ), 'w' => 210, 'h' => $r['size'] ),
			self::font( self::FONT_MONTSERRAT ),
			$d['palette']['text'],
			20,
			1.1,
			'right'
		);
	}

	/* ─────────────────────────────────────────────
	 *  TEXT RENDERING ENGINE
	 * ───────────────────────────────────────────── */

	private static function text_box(
		$img,
		string $text,
		array $box,
		string $font,
		string $color,
		int $size,
		float $line_height = 1.18,
		string $align = 'left',
		bool $shadow = false
	): void {
		$text = trim( $text );
		if ( '' === $text ) {
			return;
		}

		$color_id     = self::color( $img, $color );
		$lines        = self::wrap( $text, $font, $size, $box['w'] );
		$line_spacing = (int) round( $size * $line_height );
		$max_y        = $box['y'] + $box['h'];
		$has_ttf      = $font && function_exists( 'imagettftext' );

		// Calculate total text height
		$total_text_h = count( $lines ) * $line_spacing - (int) round( $size * ( $line_height - 1 ) );

		// Vertical centering if text fits with room
		$start_y = $box['y'] + $size;
		if ( $total_text_h < $box['h'] && count( $lines ) > 0 ) {
			$start_y = $box['y'] + (int) round( ( $box['h'] - $total_text_h ) / 2 ) + $size;
		}

		$y = $start_y;
		foreach ( $lines as $line ) {
			if ( $y > $max_y ) {
				break;
			}

			$line_x = $box['x'];
			if ( 'center' === $align || 'right' === $align ) {
				$bbox = $has_ttf ? @imagettfbbox( $size, 0, $font, $line ) : false;
				$tw   = $bbox ? abs( $bbox[2] - $bbox[0] ) : mb_strlen( $line ) * 8;
				if ( 'center' === $align ) {
					$line_x = $box['x'] + (int) round( ( $box['w'] - $tw ) / 2 );
				} else {
					$line_x = $box['x'] + $box['w'] - $tw;
				}
			}

			if ( $has_ttf ) {
				if ( $shadow ) {
					$shadow_color = imagecolorallocatealpha( $img, 0, 0, 0, 60 );
					imagettftext( $img, $size, 0, $line_x + 2, $y + 2, $shadow_color, $font, $line );
				}
				imagettftext( $img, $size, 0, $line_x, $y, $color_id, $font, $line );
			} else {
				imagestring( $img, 5, $line_x, $y - $size, $line, $color_id );
			}

			$y += $line_spacing;
		}
	}

	private static function wrap( string $text, string $font, int $size, int $max_width ): array {
		if ( $max_width <= 0 ) {
			return array( $text );
		}

		$has_ttf = $font && function_exists( 'imagettfbbox' );
		$out     = array();

		foreach ( preg_split( '/\R/u', $text ) as $paragraph ) {
			$words = preg_split( '/\s+/u', trim( $paragraph ) );
			if ( empty( $words ) || ( 1 === count( $words ) && '' === $words[0] ) ) {
				$out[] = '';
				continue;
			}
			$line = '';
			foreach ( $words as $word ) {
				$test  = '' === $line ? $word : $line . ' ' . $word;
				$bbox  = $has_ttf ? @imagettfbbox( $size, 0, $font, $test ) : false;
				$width = $bbox ? abs( $bbox[2] - $bbox[0] ) : mb_strlen( $test ) * 9;
				if ( $width > $max_width && '' !== $line ) {
					$out[] = $line;
					$line  = $word;
				} else {
					$line = $test;
				}
			}
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}

		return $out;
	}

	private static function auto_shrink_size(
		string $text,
		string $font,
		int $max_width,
		int $max_height,
		int $start_size,
		float $line_height = 1.05
	): int {
		if ( '' === $text || ! $font || ! function_exists( 'imagettfbbox' ) ) {
			return $start_size;
		}

		$min_size = max( 24, (int) round( $start_size * 0.4 ) );
		$size     = $start_size;

		for ( $attempt = 0; $attempt < 6; $attempt++ ) {
			$lines   = self::wrap( $text, $font, $size, $max_width );
			$total_h = count( $lines ) * (int) round( $size * $line_height );

			if ( $total_h <= $max_height ) {
				return $size;
			}

			$size = (int) round( $size * 0.88 );
			if ( $size < $min_size ) {
				return $min_size;
			}
		}

		return $min_size;
	}

	/* ─────────────────────────────────────────────
	 *  FONT & COLOR HELPERS
	 * ───────────────────────────────────────────── */

	private static function font( string $name ): string {
		$files = array(
			self::FONT_MONTSERRAT => 'assets/fonts/Montserrat.ttf',
			self::FONT_PLAYFAIR   => 'assets/fonts/PlayfairDisplay.ttf',
		);
		$path  = CONV_ENROLL_DIR . ( $files[ $name ] ?? '' );
		return file_exists( $path ) ? $path : '';
	}

	private static function color( $img, string $color ) {
		$compact = str_replace( ' ', '', $color );
		if ( preg_match( '/rgba\((\d+),(\d+),(\d+),([0-9.]+)\)/', $compact, $m ) ) {
			return imagecolorallocatealpha( $img, (int) $m[1], (int) $m[2], (int) $m[3], 127 - (int) round( (float) $m[4] * 127 ) );
		}
		$hex = ltrim( $color, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return imagecolorallocate( $img, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}
}
