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
 * Poster Engine v6 — Direct Imagick image renderer for activity posters.
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
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( '\\Imagick' ) ) {
			return new \WP_Error( 'imagick_missing', __( 'Imagick no está disponible. Instala/activa la extensión PHP Imagick para generar carteles.', 'convoca-enroll' ) );
		}

		$formats = self::normalize_formats( $overrides );
		$export  = self::normalize_export( (string) ( $overrides['export'] ?? $overrides['export_type'] ?? 'png' ) );
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
		if ( 'jpeg' === $export ) {
			return 'jpg';
		}
		return in_array( $export, array( 'png', 'jpg', 'webp' ), true ) ? $export : 'png';
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

		$start_raw = self::meta_first( $id, array( '_convoca_fecha_inicio', 'fecha_inicio', 'fecha' ) );
		$end_raw   = self::meta_first( $id, array( '_convoca_fecha_fin', 'fecha_fin' ) );
		$time_raw  = self::meta_first( $id, array( 'hora', '_convoca_hora' ) );

		list( $start_date, $start_time ) = self::split_datetime( $start_raw );
		list( $end_date, $end_time )     = self::split_datetime( $end_raw );
		$explicit_time                   = $time_raw ?: $start_time;

		$type_slug = self::meta_first( $id, array( 'tipo_actividad', '_convoca_tipo_actividad', 'tipo', '_convoca_tipo' ) );
		$style     = self::event_style( $type_slug );
		$image_id  = absint( $overrides['image_id'] ?? 0 );

		if ( $image_id ) {
			update_post_meta( $id, '_convoca_poster_image_id', $image_id );
		}
		$image_id = $image_id ?: absint( get_post_meta( $id, '_convoca_poster_image_id', true ) );
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
			'location'   => wp_strip_all_tags( self::meta_first( $id, array( '_convoca_lugar', 'lugar', 'ubicacion', '_convoca_ubicacion' ) ) ),
			'places'     => absint( self::meta_first( $id, array( '_convoca_plazas_totales', 'plazas_totales' ) ) ),
			'price'      => self::price_label( $id ),
			'organizer'  => wp_strip_all_tags( self::meta_first( $id, array( '_convoca_organizador', 'organizador', 'organiza', '_convoca_organiza' ) ) ),
			'age'        => wp_strip_all_tags( self::meta_first( $id, array( '_convoca_edad', 'edad', 'edad_recomendada', '_convoca_edad_recomendada' ) ) ),
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
		$raw = self::meta_first( $id, array( '_convoca_precio_socio', '_convoca_precio_general', 'precio_socio', 'precio' ) );
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
		try {
			$img = new \Imagick();
			$img->newImage( $d['w'], $d['h'], new \ImagickPixel( $d['palette']['bg'] ) );
			$img->setImageFormat( 'png' );
			$img->setImageAlphaChannel( \Imagick::ALPHACHANNEL_SET );
			$p = $d['palette'];
			$l = self::layout( $d['format'], $d['w'], $d['h'] );

			$rgba = static function( string $hex, float $opacity ): string {
				$hex = trim( $hex );
				if ( str_starts_with( $hex, 'rgb' ) ) { return $hex; }
				$hex = ltrim( $hex, '#' );
				if ( 3 === strlen( $hex ) ) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
				if ( 6 !== strlen( $hex ) ) { return 'rgba(0,0,0,' . max( 0, min( 1, $opacity ) ) . ')'; }
				return sprintf( 'rgba(%d,%d,%d,%.3F)', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), max( 0, min( 1, $opacity ) ) );
			};

			$text_draw = static function( string $font, int $size, string $color ): \ImagickDraw {
				$draw = new \ImagickDraw();
				$draw->setFont( $font ?: 'DejaVu-Sans' );
				$draw->setFontSize( $size );
				$draw->setFillColor( new \ImagickPixel( $color ) );
				$draw->setTextAntialias( true );
				return $draw;
			};

			$wrap = static function( \Imagick $canvas, string $text, string $font, int $size, int $max_width ) use ( $text_draw ): array {
				$draw = $text_draw( $font, $size, '#000000' );
				$out  = array();
				foreach ( preg_split( '/\R/u', trim( $text ) ) as $paragraph ) {
					$paragraph = trim( $paragraph );
					if ( '' === $paragraph ) { continue; }
					$line = '';
					foreach ( preg_split( '/\s+/u', $paragraph ) as $word ) {
						$test = '' === $line ? $word : $line . ' ' . $word;
						$metrics = $canvas->queryFontMetrics( $draw, $test );
						if ( (float) ( $metrics['textWidth'] ?? 0 ) > $max_width && '' !== $line ) { $out[] = $line; $line = $word; }
						else { $line = $test; }
					}
					if ( '' !== $line ) { $out[] = $line; }
				}
				return $out ?: array( $text );
			};

			$ellipsize = static function( \Imagick $canvas, string $text, string $font, int $size, int $max_width ) use ( $text_draw ): string {
				$draw = $text_draw( $font, $size, '#000000' );
				while ( mb_strlen( $text ) > 1 ) { $test = rtrim( $text, " .,-–" ) . '…'; $metrics = $canvas->queryFontMetrics( $draw, $test ); if ( (float) ( $metrics['textWidth'] ?? 0 ) <= $max_width ) { return $test; } $text = mb_substr( $text, 0, -1 ); }
				return '…';
			};

			$text_box = static function( \Imagick $canvas, string $text, array $box, string $font, string $color, int $size, float $line_height = 1.15, string $align = 'left', bool $shadow = false, int $max_lines = 0 ) use ( $text_draw, $wrap, $ellipsize ): void {
				$text = trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text ) );
				if ( '' === $text || $box['w'] <= 0 || $box['h'] <= 0 ) { return; }
				$draw = $text_draw( $font, $size, $color );
				$lines = $wrap( $canvas, $text, $font, $size, $box['w'] );
				if ( $max_lines > 0 && count( $lines ) > $max_lines ) { $lines = array_slice( $lines, 0, $max_lines ); $lines[ $max_lines - 1 ] = $ellipsize( $canvas, $lines[ $max_lines - 1 ], $font, $size, $box['w'] ); }
				$step = (int) round( $size * $line_height );
				$total_h = count( $lines ) * $step;
				$y = $box['y'] + max( $size, (int) round( ( $box['h'] - $total_h ) / 2 ) + $size );
				foreach ( $lines as $line ) {
					if ( $y > $box['y'] + $box['h'] ) { break; }
					$metrics = $canvas->queryFontMetrics( $draw, $line );
					$tw = (float) ( $metrics['textWidth'] ?? 0 );
					$x = $box['x'];
					if ( 'center' === $align ) { $x = $box['x'] + (int) round( ( $box['w'] - $tw ) / 2 ); }
					if ( 'right' === $align ) { $x = $box['x'] + $box['w'] - (int) round( $tw ); }
					if ( $shadow ) { $canvas->annotateImage( $text_draw( $font, $size, 'rgba(0,0,0,0.32)' ), $x + 3, $y + 3, 0, $line ); }
					$canvas->annotateImage( $draw, $x, $y, 0, $line );
					$y += $step;
				}
			};

			$round_rect = static function( \Imagick $canvas, array $r, string $fill, int $radius, string $stroke = 'transparent', int $stroke_width = 0 ): void {
				$draw = new \ImagickDraw();
				$draw->setFillColor( new \ImagickPixel( $fill ) );
				$draw->setStrokeColor( new \ImagickPixel( $stroke ) );
				$draw->setStrokeWidth( $stroke_width );
				$draw->roundRectangle( $r['x'], $r['y'], $r['x'] + $r['w'], $r['y'] + $r['h'], $radius, $radius );
				$canvas->drawImage( $draw );
			};

			$gradient = new \Imagick();
			$gradient->newPseudoImage( $d['w'], $d['h'], 'gradient:' . $p['bg'] . '-' . $p['deep'] );
			$gradient->setImageFormat( 'png' );
			$img->compositeImage( $gradient, \Imagick::COMPOSITE_OVER, 0, 0 );
			$gradient->clear(); $gradient->destroy();

			$decor = new \ImagickDraw();
			$decor->setFillColor( new \ImagickPixel( $rgba( $p['accent'], 0.16 ) ) );
			$decor->circle( (int) ( $d['w'] * 0.92 ), (int) ( $d['h'] * 0.12 ), (int) ( $d['w'] * 1.10 ), (int) ( $d['h'] * 0.12 ) );
			$decor->setFillColor( new \ImagickPixel( $rgba( '#ffffff', 0.08 ) ) );
			$decor->circle( (int) ( $d['w'] * 0.10 ), (int) ( $d['h'] * 0.92 ), (int) ( $d['w'] * 0.26 ), (int) ( $d['h'] * 0.92 ) );
			$img->drawImage( $decor );

			if ( self::has_image( $d['image_path'] ) ) {
				$photo = new \Imagick( $d['image_path'] );
				if ( method_exists( $photo, 'autoOrient' ) ) { $photo->autoOrient(); }
				$photo->cropThumbnailImage( $l['photo']['w'], $l['photo']['h'] );
				$img->compositeImage( $photo, \Imagick::COMPOSITE_OVER, $l['photo']['x'], $l['photo']['y'] );
				$photo->clear(); $photo->destroy();
				$overlay = new \Imagick();
				$overlay->newPseudoImage( $l['photo']['w'], $l['photo']['h'], 'gradient:rgba(0,0,0,0.18)-rgba(0,0,0,0.72)' );
				$overlay->setImageFormat( 'png' );
				$img->compositeImage( $overlay, \Imagick::COMPOSITE_OVER, $l['photo']['x'], $l['photo']['y'] );
				$overlay->clear(); $overlay->destroy();
			} else {
				$placeholder = new \ImagickDraw();
				$placeholder->setFillColor( new \ImagickPixel( $p['deep'] ) );
				$placeholder->rectangle( $l['photo']['x'], $l['photo']['y'], $l['photo']['x'] + $l['photo']['w'], $l['photo']['y'] + $l['photo']['h'] );
				$cx = $l['photo']['x'] + (int) round( $l['photo']['w'] / 2 ); $cy = $l['photo']['y'] + (int) round( $l['photo']['h'] / 2 ); $rr = (int) round( min( $l['photo']['w'], $l['photo']['h'] ) * 0.18 );
				$placeholder->setFillColor( new \ImagickPixel( $rgba( $p['accent'], 0.92 ) ) ); $placeholder->circle( $cx, $cy, $cx + $rr, $cy );
				$placeholder->setFillColor( new \ImagickPixel( $rgba( '#ffffff', 0.16 ) ) ); $placeholder->circle( $cx, $cy, $cx + (int) round( $rr * 0.58 ), $cy );
				$img->drawImage( $placeholder );
				$text_box( $img, __( 'Imagen de la actividad', 'convoca-enroll' ), array( 'x' => $l['photo']['x'] + (int) ( $l['photo']['w'] * 0.18 ), 'y' => $cy + $rr + 22, 'w' => (int) ( $l['photo']['w'] * 0.64 ), 'h' => 80 ), self::font( self::FONT_MONTSERRAT ), '#ffffff', max( 24, (int) round( min( $l['photo']['w'], $l['photo']['h'] ) * 0.045 ) ), 1.1, 'center' );
			}

			$panel_text = '#0f172a'; $panel_muted = '#334155';
			$round_rect( $img, $l['panel'], $rgba( '#ffffff', 0.95 ), $l['radius'], $rgba( '#000000', 0.10 ), 2 );

			$round_rect( $img, $l['badge'], $p['accent'], (int) round( $l['badge']['h'] / 2 ) );
			$text_box( $img, mb_strtoupper( $d['type_label'] ?: __( 'Actividad', 'convoca-enroll' ) ), array( 'x' => $l['badge']['x'] + 18, 'y' => $l['badge']['y'], 'w' => $l['badge']['w'] - 36, 'h' => $l['badge']['h'] ), self::font( self::FONT_MONTSERRAT ), $p['accent_text'], max( 18, (int) round( $l['badge']['h'] * 0.36 ) ), 1.0, 'center', false, 1 );

			$title_size = $l['title_size'];
			while ( $title_size > max( 28, (int) round( $l['title_size'] * 0.46 ) ) ) { $lines = $wrap( $img, $d['title'], self::font( self::FONT_PLAYFAIR ), $title_size, $l['title']['w'] ); if ( count( $lines ) * (int) round( $title_size * 1.04 ) <= $l['title']['h'] ) { break; } $title_size = (int) round( $title_size * 0.90 ); }
			$text_box( $img, $d['title'], $l['title'], self::font( self::FONT_PLAYFAIR ), $panel_text, $title_size, 1.04, 'left', true );
			if ( $d['subtitle'] ) { $text_box( $img, $d['subtitle'], $l['subtitle'], self::font( self::FONT_MONTSERRAT ), $panel_muted, $l['subtitle_size'], 1.18, 'left', false, 2 ); }

			$meta_lines = array(
			array( __( 'Fecha', 'convoca-enroll' ), (string) ( $d['date'] ?: __( 'Por confirmar', 'convoca-enroll' ) ) ),
			array( __( 'Hora', 'convoca-enroll' ), (string) ( $d['time'] ?: __( 'Por confirmar', 'convoca-enroll' ) ) ),
			array( __( 'Lugar', 'convoca-enroll' ), (string) ( $d['location'] ?: __( 'Por confirmar', 'convoca-enroll' ) ) ),
			array( __( 'Plazas', 'convoca-enroll' ), $d['places'] ? /* translators: %d: number of available spots */ sprintf( _n( '%d plaza', '%d plazas', $d['places'], 'convoca-enroll' ), $d['places'] ) : __( 'Por confirmar', 'convoca-enroll' ) ),
			array( __( 'Precio', 'convoca-enroll' ), (string) ( $d['price'] ?: __( 'Por confirmar', 'convoca-enroll' ) ) )
		);
			if ( ! empty( $d['organizer'] ) ) { $meta_lines[] = array( __( 'Organiza', 'convoca-enroll' ), $d['organizer'] ); }
			if ( ! empty( $d['age'] ) ) { $meta_lines[] = array( __( 'Edad', 'convoca-enroll' ), $d['age'] ); }
			if ( $meta_lines ) {
				$gap = max( 4, (int) round( $l['meta']['h'] * 0.030 ) );
				$row_h = max( 28, min( 52, (int) floor( ( $l['meta']['h'] - $gap * ( count( $meta_lines ) - 1 ) ) / count( $meta_lines ) ) ) );
				$y = $l['meta']['y'];
				foreach ( $meta_lines as $line ) { $row = array( 'x' => $l['meta']['x'], 'y' => $y, 'w' => $l['meta']['w'], 'h' => $row_h ); $round_rect( $img, $row, $rgba( '#111827', 0.055 ), (int) round( $row_h * 0.24 ) ); $label_w = min( 170, (int) round( $row['w'] * 0.26 ) ); $text_box( $img, mb_strtoupper( $line[0] ), array( 'x' => $row['x'] + 18, 'y' => $row['y'], 'w' => $label_w, 'h' => $row['h'] ), self::font( self::FONT_MONTSERRAT ), $p['accent'], max( 14, (int) round( $row_h * 0.25 ) ), 1.0, 'left', false, 1 ); $text_box( $img, $line[1], array( 'x' => $row['x'] + $label_w + 18, 'y' => $row['y'], 'w' => $row['w'] - $label_w - 36, 'h' => $row['h'] ), self::font( self::FONT_MONTSERRAT ), $panel_text, max( 18, (int) round( $row_h * 0.38 ) ), 1.05, 'left', false, 2 ); $y += $row_h + $gap; if ( $y > $l['meta']['y'] + $l['meta']['h'] ) { break; } }
			}

			$round_rect( $img, $l['cta'], $p['accent'], (int) round( $l['cta']['h'] / 2 ) );
			$cta_text = __( 'Apúntate', 'convoca-enroll' );
			if ( $d['price'] && __( 'Gratuito', 'convoca-enroll' ) !== $d['price'] ) { $cta_text .= ' · ' . $d['price']; }
			$text_box( $img, $cta_text, array( 'x' => $l['cta']['x'] + 22, 'y' => $l['cta']['y'], 'w' => $l['cta']['w'] - 44, 'h' => $l['cta']['h'] ), self::font( self::FONT_MONTSERRAT ), $p['accent_text'], max( 20, (int) round( $l['cta']['h'] * 0.36 ) ), 1.0, 'center', false, 1 );

			if ( self::has_image( $d['qr_path'] ) ) { $pad = max( 10, (int) round( $l['qr']['size'] * 0.08 ) ); $round_rect( $img, array( 'x' => $l['qr']['x'] - $pad, 'y' => $l['qr']['y'] - $pad, 'w' => $l['qr']['size'] + 2 * $pad, 'h' => $l['qr']['size'] + 2 * $pad ), '#ffffff', max( 14, (int) round( $pad * 1.5 ) ), $rgba( '#000000', 0.10 ), 1 ); $qr = new \Imagick( $d['qr_path'] ); $qr->resizeImage( $l['qr']['size'], $l['qr']['size'], \Imagick::FILTER_LANCZOS, 1, true ); $img->compositeImage( $qr, \Imagick::COMPOSITE_OVER, $l['qr']['x'], $l['qr']['y'] ); $qr->clear(); $qr->destroy(); }
			$text_box( $img, $d['org_name'], array( 'x' => $l['logo']['x'], 'y' => $l['logo']['y'], 'w' => $l['logo']['size'] ?? 240, 'h' => $l['logo']['size'] ?? 80 ), self::font( self::FONT_MONTSERRAT ), $panel_muted, max( 18, (int) round( ( $l['logo']['size'] ?? 70 ) * 0.32 ) ), 1.1, 'right', false, 2 );

			if ( 'jpg' === $export ) { $img->setImageBackgroundColor( new \ImagickPixel( '#ffffff' ) ); $img = $img->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN ); $img->setImageFormat( 'jpeg' ); $img->setImageCompressionQuality( 92 ); }
			elseif ( 'webp' === $export ) { $img->setImageFormat( 'webp' ); $img->setImageCompressionQuality( 92 ); }
			else { $img->setImageFormat( 'png' ); $img->setImageCompressionQuality( 92 ); }
			$img->stripImage();
			$ok = $img->writeImage( $path );
			$img->clear(); $img->destroy();
		} catch ( \Throwable $e ) {
			/* translators: %s: error message */
			return new \WP_Error( 'imagick_render_error', sprintf( __( 'Error generando el cartel: %s', 'convoca-enroll' ), $e->getMessage() ) );
		}

		if ( ! $ok || ! file_exists( $path ) || filesize( $path ) < 1000 ) { return new \WP_Error( 'save_error', __( 'No se pudo guardar el cartel generado.', 'convoca-enroll' ) ); }
		return true;
	}
	/* ─────────────────────────────────────────────
	 *  LAYOUT CALCULATION
	 * ───────────────────────────────────────────── */

	private static function layout( string $format, int $w, int $h ): array {
		$landscape = $w > $h;
		$min_dim   = min( $w, $h );
		$m         = (int) round( $min_dim * 0.055 );
		$gap       = (int) round( $min_dim * 0.022 );
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
		$title_h      = (int) round( $available_h * 0.28 );
		$subtitle_h   = (int) round( $available_h * 0.15 );
		$cta_h        = 48;
		$meta_h       = max( 110, $available_h - $badge_h - $title_h - $subtitle_h - $cta_h - 3 * $gap );
		$meta_h       = max( 40, $meta_h );

		$badge_y    = $panel['y'] + $gap;
		$title_y    = $badge_y + $badge_h + $gap;
		$subtitle_y = $title_y + $title_h + $gap;
		$meta_y     = $subtitle_y + $subtitle_h + $gap;
		$cta_y      = $panel['y'] + $panel['h'] - $gap - $cta_h;
		$meta_h     = max( 60, min( $meta_h, $cta_y - $meta_y - $gap ) );

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
			'square'   => 0.38,
			default   => 0.45,
		};
		$photo_h  = (int) round( $h * $photo_ratio );
		$photo    = array( 'x' => 0, 'y' => 0, 'w' => $w, 'h' => $photo_h );

		$overlap  = (int) round( min( $w, $h ) * 0.055 );
		$panel_y  = $photo_h - $overlap;
		$panel    = array( 'x' => $m, 'y' => $panel_y, 'w' => $w - 2 * $m, 'h' => $h - $panel_y - $m );

		$qr_size   = (int) round( min( $w, $h ) * ( 'story' === $format ? 0.16 : ( $format === 'square' ? 0.09 : 0.12 ) ) );
		$logo_size = (int) round( min( $w, $h ) * ( 'story' === $format ? 0.075 : 0.065 ) );

		$font_base = max( 42, (int) round( min( $w, $h ) * ( 'story' === $format ? 0.065 : 0.060 ) ) );

		$available_h      = $panel['h'] - 2 * $gap;
		$bottom_zone_h    = max( $qr_size, $logo_size ) + $gap;
		$text_available_h = $available_h - $bottom_zone_h;

		$badge_h    = (int) round( min( $w, $h ) * ( $format === 'square' ? 0.040 : 0.058 ) );
		$title_h    = (int) round( $text_available_h * ( $format === 'square' ? 0.20 : 0.28 ) );
		$subtitle_h = (int) round( $text_available_h * 0.10 );
		$cta_h      = (int) round( min( $w, $h ) * ( $format === 'square' ? 0.050 : 0.062 ) );
		$meta_h     = max( 130, $text_available_h - $badge_h - $title_h - $subtitle_h - $cta_h - 3 * $gap );

		$badge_y    = $panel['y'] + $gap;
		$title_y    = $badge_y + $badge_h + $gap;
		$subtitle_y = $title_y + $title_h + $gap;
		$meta_y     = $subtitle_y + $subtitle_h + $gap;
		$cta_y      = $panel['y'] + $panel['h'] - $gap - $bottom_zone_h - $gap - $cta_h;
		$meta_h     = max( 80, min( $meta_h, $cta_y - $meta_y - $gap ) );

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
	 *  FILE HELPERS
	 * ───────────────────────────────────────────── */

	private static function has_image( string $path ): bool {
		return $path && file_exists( $path ) && filesize( $path ) > 100;
	}

	/* ─────────────────────────────────────────────
	 *  FONT HELPERS
	 * ───────────────────────────────────────────── */
	private static function font( string $name ): string {
		$files = array(
			self::FONT_MONTSERRAT => 'assets/fonts/Montserrat.ttf',
			self::FONT_PLAYFAIR   => 'assets/fonts/PlayfairDisplay.ttf',
		);
		$path  = CONVOCA_ENROLL_DIR . ( $files[ $name ] ?? '' );
		return file_exists( $path ) ? $path : '';
	}

}
