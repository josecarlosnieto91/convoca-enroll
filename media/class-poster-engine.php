<?php
/**
 * Poster Engine — Server-side image composition engine.
 *
 * Uses Imagick (primary) with GD fallback for rendering poster templates
 * with activity data.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main poster rendering engine.
 *
 * Takes an activity + template + data and produces a poster image.
 */
class Poster_Engine {

	const CACHE_GROUP = 'convoca_poster';

	/**
	 * Render a poster for an activity.
	 *
	 * @param int          $actividad_id Activity post ID.
	 * @param string|int   $template_slug_or_id Template slug or ID.
	 * @param array        $overrides   Optional overrides: { image_id, formats, quality }.
	 * @return array{files: array, url: string}|WP_Error
	 */
	public static function render( int $actividad_id, $template_slug_or_id = 'naturaleza', array $overrides = array() ) {
		$template = Template_Manager::get_config( $template_slug_or_id );
		if ( ! $template ) {
			return new \WP_Error( 'template_not_found', __( 'Plantilla no encontrada.', 'convoca-enroll' ) );
		}

		$actividad = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' ) {
			return new \WP_Error( 'invalid_activity', __( 'Actividad no válida.', 'convoca-enroll' ) );
		}

		// Gather data.
		$data = self::gather_data( $actividad_id );

		// Resolve main image.
		$image_id = $overrides['image_id'] ?? get_post_thumbnail_id( $actividad_id );
		if ( ! $image_id ) {
			$image_id = self::find_first_image( $actividad_id );
		}

		$upload_dir = wp_upload_dir();
		$cache_dir  = $upload_dir['basedir'] . '/convoca-posters/';
		if ( ! is_dir( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}

		$formats = $overrides['formats'] ?? array_keys( $template['formats'] ?? array( 'square' ) );
		$quality = $overrides['quality'] ?? 85;
		$export_type = $overrides['export_type'] ?? 'png';

		$files   = array();
		$base_name = 'poster-' . $actividad_id . '-' . sanitize_title( $template_slug_or_id );

		foreach ( $formats as $format_key ) {
			if ( ! isset( $template['formats'][ $format_key ] ) ) {
				continue;
			}

			$format_def = $template['formats'][ $format_key ];
			if ( isset( $format_def['width'] ) ) {
				$target_w = $format_def['width'];
				$target_h = $format_def['height'];
			} elseif ( is_array( $format_def ) ) {
				$vals = array_values( $format_def );
				$target_w = $vals[0] ?? $template['width'] ?? 1080;
				$target_h = $vals[1] ?? $template['height'] ?? 1080;
			} else {
				$target_w = $template['width'] ?? 1080;
				$target_h = $template['height'] ?? 1080;
			}
			$cache_key   = $base_name . '-' . $format_key . '.' . $export_type;
			$output_path = $cache_dir . $cache_key;

			// Cache check.
			if ( file_exists( $output_path ) && empty( $overrides['force'] ) ) {
				$files[ $format_key ] = $output_path;
				continue;
			}

			// Create canvas.
			try {
				$canvas = new \Imagick();
				$canvas->newImage( $target_w, $target_h, new \ImagickPixel( 'transparent' ) );
				$canvas->setImageFormat( $export_type );
				if ( $export_type === 'jpg' ) {
					$canvas->setImageCompression( \Imagick::COMPRESSION_JPEG );
				}
				$canvas->setImageCompressionQuality( $quality );

				// Render each layer.
				foreach ( $template['layers'] as $layer_def ) {
					self::render_layer( $canvas, $layer_def, $data, $image_id, $template, $format_key );
				}

				$canvas->writeImage( $output_path );
				$canvas->clear();
				$files[ $format_key ] = $output_path;

			} catch ( \Exception $e ) {
				return new \WP_Error( 'render_error', $e->getMessage() );
			}
		}

		return array(
			'files' => $files,
			'url'   => str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], reset( $files ) ),
		);
	}

	/**
	 * Render a single layer onto the canvas.
	 *
	 * @param \Imagick $canvas   Target canvas.
	 * @param array    $def      Layer definition.
	 * @param array    $data     Resolved activity data.
	 * @param int      $image_id Featured image ID (if any).
	 * @param array    $template Full template config.
	 */
	private static function render_layer( \Imagick $canvas, array $def, array $data, ?int $image_id, array $template, string $format_key = 'square' ): void {
		// Resolve responsive overrides for this format.
		if ( ! empty( $def['responsive'][ $format_key ] ) ) {
			foreach ( $def['responsive'][ $format_key ] as $rk => $rv ) {
				$def[ $rk ] = $rv;
			}
		}

		$x = $def['x'] ?? 0;
		$y = $def['y'] ?? 0;
		$w = $def['w'] ?? 100;
		$h = $def['h'] ?? 100;
		$type = $def['type'] ?? '';

		switch ( $type ) {
			case 'background':
				self::render_background( $canvas, $def );

			case 'image':
				self::render_image_layer( $canvas, $def, $image_id );

			case 'overlay':
				self::render_overlay( $canvas, $def );

			case 'text':
				self::render_text_layer( $canvas, $def, $data, $template );

			case 'logo':
				self::render_logo( $canvas, $def );

			case 'qr':
				self::render_qr( $canvas, $def, $data['actividad_id'] );

			case 'badge':
				self::render_badge( $canvas, $def, $data );

			case 'rect':
				self::render_rect( $canvas, $def );

			case 'cta':
				self::render_cta( $canvas, $def, $data );

			case 'price_badge':
				self::render_price_badge( $canvas, $def, $data );



		}
	}

	/**
	 * Render background layer.
	 */
	private static function render_background( \Imagick $canvas, array $def ): void {
		if ( ! empty( $def['gradient'] ) && is_array( $def['gradient'] ) ) {
			$gradient = new \Imagick();
			$angle    = $def['angle'] ?? 0;
			$w        = $canvas->getImageWidth();
			$h        = $canvas->getImageHeight();

			if ( $angle === 0 || $angle === 90 ) {
				$gradient->newPseudoImage( $w, $h, sprintf( 'gradient:%s-%s', $def['gradient'][0], $def['gradient'][1] ) );
			} else {
				// For angled gradients, create a larger image and rotate.
				$diag = (int) ceil( sqrt( $w * $w + $h * $h ) );
				$gradient->newPseudoImage( $diag, $diag, sprintf( 'gradient:%s-%s', $def['gradient'][0], $def['gradient'][1] ) );
				$gradient->rotateImage( new \ImagickPixel( 'transparent' ), $angle );
				$gradient->cropImage( $w, $h, (int) ( ( $diag - $w ) / 2 ), (int) ( ( $diag - $h ) / 2 ) );
			}
			$canvas->compositeImage( $gradient, \Imagick::COMPOSITE_OVER, 0, 0 );
			$gradient->clear();
		} elseif ( ! empty( $def['color'] ) ) {
			$w = $canvas->getImageWidth();
			$h = $canvas->getImageHeight();
			$fill = new \Imagick();
			$fill->newImage( $w, $h, new \ImagickPixel( $def['color'] ), 'png' );
			$canvas->compositeImage( $fill, \Imagick::COMPOSITE_DEFAULT, 0, 0 );
			$fill->clear();
		}
	}

	/**
	 * Render main image layer.
	 */
	private static function render_image_layer( \Imagick $canvas, array $def, ?int $image_id ): void {
		if ( ! $image_id ) {
			return;
		}
		$img_path = get_attached_file( $image_id );
		if ( ! $img_path || ! file_exists( $img_path ) ) {
			return;
		}

		try {
			$layer = new \Imagick( $img_path );
			$fit   = $def['fit'] ?? 'cover';
			$tw    = $def['w'] ?? $canvas->getImageWidth();
			$th    = $def['h'] ?? $canvas->getImageHeight();
			$tx    = $def['x'] ?? 0;
			$ty    = $def['y'] ?? 0;

			$lw = $layer->getImageWidth();
			$lh = $layer->getImageHeight();

			if ( $fit === 'cover' ) {
				$scale = max( $tw / $lw, $th / $lh );
				$layer->resizeImage( (int) ( $lw * $scale ), (int) ( $lh * $scale ), \Imagick::FILTER_LANCZOS, 1 );
				$layer->cropImage( $tw, $th, (int) ( ( $layer->getImageWidth() - $tw ) / 2 ), (int) ( ( $layer->getImageHeight() - $th ) / 2 ) );
			} else {
				$layer->resizeImage( $tw, $th, \Imagick::FILTER_LANCZOS, 1, true );
			}

			if ( isset( $def['opacity'] ) ) {
			}

			// Border radius.
			if ( ! empty( $def['border_radius'] ) ) {
				self::round_corners( $layer, (int) $def['border_radius'] );
			}

			$canvas->compositeImage( $layer, \Imagick::COMPOSITE_OVER, $tx, $ty );
			$layer->clear();
		} catch ( \Exception $e ) {
			// Silently skip if image can't be loaded.
		}
	}

	/**
	 * Render gradient overlay.
	 */
	private static function render_overlay( \Imagick $canvas, array $def ): void {
		if ( empty( $def['gradient'] ) ) {
			return;
		}

		$w = $def['w'] ?? $canvas->getImageWidth();
		$h = $def['h'] ?? 100;
		$x = $def['x'] ?? 0;
		$y = $def['y'] ?? 0;

		try {
			$overlay = new \Imagick();
			$overlay->newPseudoImage( $w, $h, sprintf( 'gradient:%s-%s', $def['gradient'][0], $def['gradient'][1] ) );
			$canvas->compositeImage( $overlay, \Imagick::COMPOSITE_OVER, $x, $y );
			$overlay->clear();
		} catch ( \Exception $e ) {
			// Ignore.
		}
	}

	/**
	 * Render text layer.
	 */
	private static function render_text_layer( \Imagick $canvas, array $def, array $data, array $template ): void {
		$ref  = $def['ref'] ?? '';
		$text = $data[ $ref ] ?? '';

		if ( empty( $text ) ) {
			return;
		}

		// Resolve font config.
		$font_key   = $def['font'] ?? ( $ref === 'title' ? 'title' : ( in_array( $ref, array( 'cta', 'date', 'location' ), true ) ? $ref : 'meta' ) );
		// v2 schema: design_tokens.typography. v1 fallback: fonts.
		$font_cfg   = $template['design_tokens']['typography'][ $font_key ]
			?? $template['fonts'][ $font_key ]
			?? $template['design_tokens']['typography']['body']
			?? $template['fonts']['meta']
			?? array( 'family' => 'Lato', 'size' => 28, 'color' => '#ffffff' );
		$font_size  = $def['font_size'] ?? $font_cfg['size'] ?? 28;

		$draw = new \ImagickDraw();
		// Auto-shrink title if enabled and text is too long.
		if ( ! empty( $def['auto_shrink'] ) && ! empty( $def['max_lines'] ) ) {
			$min_size  = $def['auto_shrink_min'] ?? 24;
			$max_width = $def['w'] ?? $canvas->getImageWidth() - ( $def['x'] ?? 0 );
			$words     = str_word_count( $text, 2 );
			$approx_lines = ( $font_size > 0 ) ? (int) ceil( array_sum( array_map( 'strlen', $words ) ) * $font_size * 0.5 / $max_width ) : 1;
			while ( $approx_lines > $def['max_lines'] && $font_size > $min_size ) {
				$font_size -= 4;
				$approx_lines = (int) ceil( array_sum( array_map( 'strlen', $words ) ) * $font_size * 0.5 / $max_width );
			}
		}

		$draw->setFontSize( $font_size );

		// Font file resolution.
		$font_family = $font_cfg['family'] ?? 'Lato';
		$font_file   = self::resolve_font( $font_family, $font_cfg['weight'] ?? 400 );
		if ( $font_file ) {
			$draw->setFont( $font_file );
			if ( ! empty( $font_cfg['weight'] ) ) {
				// Weight set via variable font selection
			}
		}

		// Color.
		$text_color = $def['color'] ?? $font_cfg['color'] ?? '#000000';
		$draw->setFillColor( new \ImagickPixel( $text_color ) );

		// Text shadow for readability (when template has auto_text_shadow).
		$do_shadow = ! empty( $template['smart']['auto_text_shadow'] ) || ! empty( $template['design_tokens']['smart']['auto_text_shadow'] );
		if ( $do_shadow && in_array( $ref, array( 'title', 'meta_block', 'date', 'cta' ), true ) ) {
			$shadow = new \ImagickDraw();
			$shadow->setFont( $draw->getFont() );
			$shadow->setFontSize( $font_size );
			$shadow->setTextAlignment( $draw->getTextAlignment() );
			$shadow->setFillColor( new \ImagickPixel( 'rgba(0,0,0,0.35)' ) );
		}

		// Alignment.
		$align = $def['align'] ?? 'left';
		$draw->setTextAlignment(
			$align === 'center' ? \Imagick::ALIGN_CENTER : ( $align === 'right' ? \Imagick::ALIGN_RIGHT : \Imagick::ALIGN_LEFT )
		);

		// Word wrap.
		$x      = $def['x'] ?? 0;
		$y      = $def['y'] ?? 0;
		$mw     = $def['w'] ?? $canvas->getImageWidth() - $x;
		$lines  = self::word_wrap( $text, $font_size, $mw, $font_file );

		$line_h = (int) ( $font_size * 1.3 );
		$ly     = $y + $line_h; // baseline offset

		foreach ( $lines as $line ) {
			if ( $ly > $y + ( $def['h'] ?? 9999 ) ) {
			}
			$draw->annotation( $x, $ly, $line );
			$ly += $line_h;
		$canvas->drawImage( $draw );
		}
	}

	/**
	 * Render organization logo.
	 */
	private static function render_logo( \Imagick $canvas, array $def ): void {
		// Use custom logo if uploaded in settings, or fallback to site icon.
		$settings = get_option( 'conv_enroll_settings', array() );
		$logo_id  = $settings['poster_logo_id'] ?? get_option( 'site_icon' );
		if ( ! $logo_id ) {
			return;
		}

		$logo_path = get_attached_file( $logo_id );
		if ( ! $logo_path || ! file_exists( $logo_path ) ) {
			return;
		}

		try {
			$logo = new \Imagick( $logo_path );

			$max_w = $def['w'] ?? 120;
			$max_h = $def['h'] ?? 120;
			$logo->resizeImage( $max_w, $max_h, \Imagick::FILTER_LANCZOS, 1, true );


			$x = $def['x'] ?? 0;
			$y = $def['y'] ?? 0;

			// Center alignment within the defined area.
			if ( ! empty( $def['align'] ) && $def['align'] === 'center' ) {
				$x += ( ( $def['w'] ?? 0 ) - $logo->getImageWidth() ) / 2;
			}

			$canvas->compositeImage( $logo, \Imagick::COMPOSITE_OVER, (int) $x, (int) $y );
			$logo->clear();
		} catch ( \Exception $e ) {
			// Ignore logo errors.
		}
	}

	/**
	 * Render QR code layer.
	 */
	private static function render_qr( \Imagick $canvas, array $def, int $actividad_id ): void {
		try {
			$qr_path = QR_Generator::generate( $actividad_id, array(
				'size' => $def['size'] ?? 300,
			) );

			if ( ! $qr_path || ! file_exists( $qr_path ) ) {
				return;
			}

			$qr = new \Imagick( $qr_path );
			$canvas->compositeImage( $qr, \Imagick::COMPOSITE_OVER, $def['x'] ?? 0, $def['y'] ?? 0 );
			$qr->clear();
		} catch ( \Throwable $e ) {
			// QR generation is optional — skip silently.
		}
	}

	/**
	 * Render activity type badge.
	 */
	private static function render_badge( \Imagick $canvas, array $def, array $data ): void {
		$badge_text = $data['badge_text'] ?? '';
		if ( empty( $badge_text ) ) {
			return;
		}

		$badge_color = $data['badge_color'] ?? '#ff8700';
		$badge_size  = $def['size'] ?? 36;
		$x           = $def['x'] ?? 0;
		$y           = $def['y'] ?? 0;

		// Background pill.
		$draw = new \ImagickDraw();
		$draw->setFillColor( new \ImagickPixel( $badge_color ) );
		$draw->setFontSize( $badge_size - 4 );

		$text_w = strlen( $badge_text ) * ( $badge_size * 0.5 );
		$pill_w = (int) $text_w + 30;
		$pill_h = (int) ( $badge_size * 1.2 );

		$draw->roundRectangle( $x, $y, $x + $pill_w, $y + $pill_h, $pill_h / 2, $pill_h / 2 );

		// Text.
		$draw->setFillColor( new \ImagickPixel( '#ffffff' ) );
		$draw->setTextAlignment( \Imagick::ALIGN_CENTER );
		$font_file = self::resolve_font( 'Outfit', 600 );
		if ( $font_file ) {
			$draw->setFont( $font_file );
			if ( ! empty( $font_cfg['weight'] ) ) {
				// Weight set via variable font selection
			}
		}
		$draw->annotation( $x + $pill_w / 2, $y + $pill_h - 8, $badge_text );
		$canvas->drawImage( $draw );
	}

	/**
	 * Render a colored rectangle (e.g., CTA button background).
	 */
	private static function render_rect( \Imagick $canvas, array $def ): void {
		$draw = new \ImagickDraw();
		$draw->setFillColor( new \ImagickPixel( $def['color'] ?? '#ff8700' ) );

		if ( ! empty( $def['border_radius'] ) ) {
			$r = (int) $def['border_radius'];
			$draw->roundRectangle(
				$def['x'] ?? 0, $def['y'] ?? 0,
				( $def['x'] ?? 0 ) + ( $def['w'] ?? 100 ),
				( $def['y'] ?? 0 ) + ( $def['h'] ?? 100 ),
				$r, $r
			);
		} else {
			$draw->rectangle(
				$def['x'] ?? 0, $def['y'] ?? 0,
				( $def['x'] ?? 0 ) + ( $def['w'] ?? 100 ),
				( $def['y'] ?? 0 ) + ( $def['h'] ?? 100 )
			);
		}

		$canvas->drawImage( $draw );
	}

	// ─── Helpers ───────────────────────────────────────────────

	/**
	 * Render a CTA button (colored pill + text).
	 */
	private static function render_cta( \Imagick $canvas, array $def, array $data ): void {
		$cta_text = $data['cta'] ?? __( 'Inscríbete aquí', 'convoca-enroll' );
		$x        = $def['x'] ?? 0;
		$y        = $def['y'] ?? 0;
		$w        = $def['w'] ?? 360;
		$h        = $def['h'] ?? 60;
		$align    = $def['align'] ?? 'left';
		$font_cfg = $data['_font_cta']
			?? $template['design_tokens']['typography']['cta']
			?? $template['fonts']['cta']
			?? array( 'family' => 'Outfit', 'weight' => 600, 'size' => 30, 'color' => '#ffffff' );

		// Background pill.
		$draw = new \ImagickDraw();
		$draw->setFillColor( new \ImagickPixel( '#ffffff' ) );
		$draw->roundRectangle( $x, $y, $x + $w, $y + $h, $h / 2, $h / 2 );
		$canvas->drawImage( $draw );

		// Text.
		$draw = new \ImagickDraw();
		$font_file = self::resolve_font( $font_cfg['family'], $font_cfg['weight'] );
		if ( $font_file ) {
			$draw->setFont( $font_file );
		}
		$draw->setFontSize( $font_cfg['size'] );
		$draw->setFillColor( new \ImagickPixel( '#1a1a1a' ) );
		$draw->setTextAlignment(
			$align === 'center' ? \Imagick::ALIGN_CENTER : ( $align === 'right' ? \Imagick::ALIGN_RIGHT : \Imagick::ALIGN_LEFT )
		);

		$tx = $align === 'center' ? $x + $w / 2 : ( $align === 'right' ? $x + $w - 12 : $x + 24 );
		$ty = $y + ( $h + $font_cfg['size'] * 0.35 ) / 2;
		$draw->annotation( $tx, (int) $ty, $cta_text );
		$canvas->drawImage( $draw );
	}
	/**
	 * Render a price badge (free pill or price tag).
	 */
	private static function render_price_badge( \Imagick $canvas, array $def, array $data ): void {
		$price = $data['price'] ?? '';
		$free  = ! empty( $data['free'] );
		$x     = $def['x'] ?? 0;
		$y     = $def['y'] ?? 0;
		$w     = $def['w'] ?? 200;
		$h     = $def['h'] ?? 36;

		if ( $free ) {
			$label = 'Gratuito';
			$color = '#4caf50';
		} elseif ( $price ) {
			$label = $price;
			$color = '#ff8700';
		} else {
			return;
		}

		$draw = new \ImagickDraw();
		$draw->setFillColor( new \ImagickPixel( $color ) );
		$draw->roundRectangle( $x, $y, $x + $w, $y + $h, $h / 2, $h / 2 );
		$canvas->drawImage( $draw );

		$draw = new \ImagickDraw();
		$draw->setFillColor( new \ImagickPixel( '#ffffff' ) );
		$draw->setFont( '/usr/share/fonts/TTF/Outfit-variable.ttf' );
		$draw->setFontSize( 18 );
		$draw->setTextAlignment( \Imagick::ALIGN_CENTER );
		$draw->annotation( $x + $w / 2, $y + $h - 8, $label );
		$canvas->drawImage( $draw );
	}

		private static function gather_data( int $actividad_id ): array {
		// Try new meta keys first, then legacy _bde_ keys as fallback.
		$fecha_inicio = get_post_meta( $actividad_id, 'conv_fecha_inicio', true );
		if ( empty( $fecha_inicio ) ) {
			$fecha_inicio = get_post_meta( $actividad_id, '_bde_fecha_inicio', true );
		}

		$fecha_fin = get_post_meta( $actividad_id, 'conv_fecha_fin', true );
		if ( empty( $fecha_fin ) ) {
			$fecha_fin = get_post_meta( $actividad_id, '_bde_fecha_fin', true );
		}

		$plazas = 0;

		$ubicacion = get_post_meta( $actividad_id, 'conv_ubicacion', true );
		if ( empty( $ubicacion ) ) {
			$ubicacion = get_post_meta( $actividad_id, '_bde_ubicacion', true );
		}

		$tipo  = get_post_meta( $actividad_id, 'conv_tipo_actividad', true );
		$badge = self::get_badge( $tipo );

		$title    = get_the_title( $actividad_id );
		$extracto = get_the_excerpt( $actividad_id ) ?: wp_trim_words( strip_tags( get_post_field( 'post_content', $actividad_id ) ), 30 );
		$time_str = '';

		if ( $fecha_inicio ) {
			$timestamp = strtotime( $fecha_inicio );
			$time_str  = date( 'H:i', $timestamp );
		}

		// Build meta block string: date · time · location
		$meta_parts = array();
		if ( $fecha_inicio ) {
			$meta_parts[] = \Convoca\Core\Utils::format_date( $fecha_inicio, 'j F Y' );
		}
		if ( $time_str ) {
			$meta_parts[] = $time_str . ' h';
		}
		if ( $ubicacion ) {
			$meta_parts[] = $ubicacion;
		}
		$meta_block = implode( '  ·  ', $meta_parts );
		if ( $plazas ) {
			$meta_block .= '  ·  ' . $plazas . ' plazas';
		}

		// Gather additional fields for richer posters.
		$precio_socio  = get_post_meta( $actividad_id, 'conv_precio_socio', true );
		if ( empty( $precio_socio ) ) {
			$precio_socio = get_post_meta( $actividad_id, '_bde_precio_socio', true );
		}
		$precio_general = get_post_meta( $actividad_id, 'conv_precio_general', true );
		if ( empty( $precio_general ) ) {
			$precio_general = get_post_meta( $actividad_id, '_bde_precio_general', true );
		}
		$tags = wp_get_post_tags( $actividad_id, array( 'fields' => 'names' ) );
		$hashtags = $tags ? '#' . implode( ' #', $tags ) : '';
		$organizadores = get_post_meta( $actividad_id, 'conv_organizadores', true );
		$duracion = get_post_meta( $actividad_id, 'conv_duracion', true );

		return array(
			'actividad_id'  => $actividad_id,
			'title'         => $title ?: 'Actividad',
			'subtitle'      => $extracto ?: 'Te esperamos!',
			'date'          => $fecha_inicio ? \Convoca\Core\Utils::format_date( $fecha_inicio, 'j F Y' ) : '',
			'time'          => $time_str,
			'location'      => $ubicacion ?: '',
			'meta_block'    => $meta_block,
			'price'         => ( $precio_socio !== '' && $precio_socio !== false ) ? $precio_socio . '€' : ( $precio_general ? $precio_general . '€' : '' ),
			'free'          => ( $precio_socio === '' || $precio_socio === false || $precio_socio === '0' ) && ( $precio_general === '' || $precio_general === false || $precio_general === '0' ),
			'hashtags'      => $hashtags,
			'organizadores' => $organizadores ?: '',
			'duracion'      => $duracion ?: '',
			'cta'           => __( 'Inscríbete aquí', 'convoca-enroll' ),
			'badge_text'    => $badge['label'] ?? '',
			'badge_color'   => $badge['color'] ?? '#ff8700',
			'badge_icon'    => $badge['icon'] ?? '',
			'permalink'     => get_permalink( $actividad_id ),
		);
	}
	/**
	 * Resolve badge data based on activity type taxonomy.
	 */
	private static function get_badge( $tipo ): array {
		return \Convoca\Enroll\Media\Event_Style_Registry::get( $tipo );
	}

	/**
	 * Resolve TTF font file path.
	 */
	private static function resolve_font( string $family, int $weight = 400 ): ?string {
		$fonts = array(
			'Outfit'  => array(
				'var' => '/usr/share/fonts/TTF/Outfit-variable.ttf',
				400 => '/usr/share/fonts/TTF/Outfit-variable.ttf',
				500 => '/usr/share/fonts/TTF/Outfit-variable.ttf',
				600 => '/usr/share/fonts/TTF/Outfit-variable.ttf',
				700 => '/usr/share/fonts/TTF/Outfit-variable.ttf',
				800 => '/usr/share/fonts/TTF/Outfit-variable.ttf',
			),
			'Lato'    => array(
				300 => '/usr/share/fonts/TTF/Lato-Light.ttf',
				400 => '/usr/share/fonts/TTF/Lato-Regular.ttf',
				700 => '/usr/share/fonts/TTF/Lato-Bold.ttf',
			),
		);

		if ( isset( $fonts[ $family ][ $weight ] ) && file_exists( $fonts[ $family ][ $weight ] ) ) {
			return $fonts[ $family ][ $weight ];
		}

		// Fallback: try common paths.
		$fallbacks = array(
			"/usr/share/fonts/truetype/{$family}-{$weight}.ttf",
			"/usr/share/fonts/{$family}-{$weight}.ttf",
			"/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
			"/usr/share/fonts/TTF/DejaVuSans.ttf",
		);

		foreach ( $fallbacks as $fb ) {
			if ( file_exists( $fb ) ) {
				return $fb;
			}
		}

		return null;
	}

	/**
	 * Simple word wrap for Imagick annotation.
	 */
	private static function word_wrap( string $text, int $font_size, int $max_width, ?string $font_file = null ): array {
		$words = explode( ' ', $text );
		$lines = array();
		$line  = '';

		foreach ( $words as $word ) {
			$test = $line ? $line . ' ' . $word : $word;
			$w    = self::text_width( $test, $font_size, $font_file );
			if ( $w > $max_width && $line ) {
				$lines[] = $line;
				$line    = $word;
			} else {
				$line = $test;
			}
		}
		if ( $line ) {
			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * Estimate text width using Imagick query metrics.
	 */
	private static function text_width( string $text, int $size, ?string $font_file ): int {
		$draw = new \ImagickDraw();
		$draw->setFontSize( $size );
		if ( $font_file ) {
			$draw->setFont( $font_file );
			if ( ! empty( $font_cfg['weight'] ) ) {
				// Weight set via variable font selection
			}
		}
		$metrics = ( new \Imagick() )->queryFontMetrics( $draw, $text );
		return (int) ( $metrics['textWidth'] ?? strlen( $text ) * $size * 0.5 );
	}

	/**
	 * Round corners of an Imagick image.
	 */
	private static function round_corners( \Imagick $image, int $radius ): void {
		$w = $image->getImageWidth();
		$h = $image->getImageHeight();

		$mask = new \Imagick();
		$mask->newImage( $w, $h, new \ImagickPixel( 'transparent' ), 'png' );

		$draw = new \ImagickDraw();
		$draw->setFillColor( new \ImagickPixel( 'white' ) );
		$draw->roundRectangle( 0, 0, $w, $h, $radius, $radius );
		$mask->drawImage( $draw );

		$image->compositeImage( $mask, \Imagick::COMPOSITE_COPYOPACITY, 0, 0 );
		$mask->clear();
	}

	/**
	 * Find first image embedded in activity content.
	 */
	private static function find_first_image( int $post_id ): ?int {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		preg_match( '/wp:image {"id":(\d+)}/', $post->post_content, $matches );
		if ( ! empty( $matches[1] ) ) {
			return (int) $matches[1];
		}

		preg_match( '/<img[^>]+wp-image-(\d+)/', $post->post_content, $matches );
		return ! empty( $matches[1] ) ? (int) $matches[1] : null;
	}
}
