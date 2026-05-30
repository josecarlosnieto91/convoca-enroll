<?php
/**
 * Poster Engine v3 — HTML → PDF → Image pipeline
 *
 * Replaces the legacy pure-Imagick composition with an editorial-grade
 * HTML/CSS templating system. Renders through mPDF and rasterizes with Imagick.
 *
 * @package Convoca\Enroll\Media
 * @version 3.0.0
 */

namespace Convoca\Enroll\Media;

// Uses Dompdf from convoca-core vendor

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Poster_Engine_v3 — Main render controller.
 */
class Poster_Engine {

	const CACHE_DIR = 'convoca-posters';
	const TEMP_DIR  = 'convoca-temp';

	/**
	 * Render a poster for an activity.
	 *
	 * 3-pass pipeline:
	 *   1. Compile HTML from template + activity data
	 *   2. Render HTML → PDF via mPDF
	 *   3. Rasterize PDF → PNG via Imagick
	 *
	 * @param int    $actividad_id
	 * @param string $template_slug
	 * @param array  $overrides {
	 *   @type string $format    'square'|'story'|'facebook'|'portrait'|'banner'|'a4'
	 *   @type bool   $force     Skip cache
	 *   @type string $export    'png'|'jpg'|'webp'
	 * }
	 * @return array|WP_Error { files: [...], url: string }
	 */
	public static function render( int $actividad_id, string $template_slug = 'nature-classic', array $overrides = [] ): array|\WP_Error {
		$format = $overrides['format'] ?? 'square';
		$force  = ! empty( $overrides['force'] );
		$export = $overrides['export'] ?? 'png';

		// ── Check cache ──
		$cache_key = "poster-{$actividad_id}-{$template_slug}-{$format}.{$export}";
		$upload    = wp_upload_dir();
		$cache_dir = trailingslashit( $upload['basedir'] ) . self::CACHE_DIR;
		$cache_url = trailingslashit( $upload['baseurl'] ) . self::CACHE_DIR;
		wp_mkdir_p( $cache_dir );

		$output_path = "{$cache_dir}/{$cache_key}";
		if ( ! $force && file_exists( $output_path ) ) {
			return [
				'files' => [ $format => $output_path ],
				'url'   => "{$cache_url}/{$cache_key}",
				'cached' => true,
			];
		}

		// ── Gather activity data ──
		$data = self::gather_activity_data( $actividad_id, $template_slug, $format );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// ── Pass 1: Compile HTML ──
		$html = self::compile_html( $template_slug, $data, $format );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		// ── Pass 2: HTML → PDF ──
		$pdf_path = self::html_to_pdf( $html, $data['width'], $data['height'] );
		if ( is_wp_error( $pdf_path ) ) {
			return $pdf_path;
		}

		// ── Pass 3: PDF → Image ──
		$result = self::pdf_to_image( $pdf_path, $output_path, $data['width'], $data['height'], $export );

		// ── Cleanup: remove temp PDF ──
		if ( file_exists( $pdf_path ) ) {
			@unlink( $pdf_path );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// ── Log ──
		$duration = microtime( true ) - ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
		Media_Logger::log( 'poster', 'render', "{$template_slug}/{$format}", [
			'actividad_id' => $actividad_id,
			'status'       => 'success',
			'duration_ms'  => round( $duration * 1000 ),
		] );

		return [
			'files' => [ $format => $output_path ],
			'url'   => "{$cache_url}/{$cache_key}",
			'cached' => false,
		];
	}

	/**
	 * Gather all data needed for the template.
	 */
	private static function gather_activity_data( int $actividad_id, string $template_slug, string $format ): array|\WP_Error {
		$post = get_post( $actividad_id );
		if ( ! $post || 'actividad' !== $post->post_type ) {
			return new \WP_Error( 'invalid_activity', 'Actividad no encontrada o tipo incorrecto.' );
		}

		$meta       = get_post_custom( $actividad_id );
		$dimensions = self::format_dimensions( $format );

		// Design tokens from template
		$template = Template_Manager::get_by_slug( $template_slug );
		$tokens   = $template ? ( $template['config']['design_tokens'] ?? [] ) : [];
		$palette  = $tokens['palette'] ?? [];
		$primary  = $palette['primary'] ?? '#2e7d32';
		$accent   = $palette['accent'] ?? '#8bc34a';

		// Resolve type style
		$type_slug = $meta['tipo_actividad'][0] ?? '';
		$style     = \Convoca\Core\Event_Style_Registry::get( $type_slug );

		// Price
		$price_raw = $meta['precio'][0] ?? '0';
		$price     = (float) $price_raw > 0 ? number_format( (float) $price_raw, 2 ) . ' €' : 'Gratuito';

		// Hero image (featured image or first gallery)
		$hero_base64 = '';
		$thumb_id    = get_post_thumbnail_id( $actividad_id );
		if ( $thumb_id ) {
			$hero_base64 = self::image_to_base64( $thumb_id, $dimensions['width'], $dimensions['height'] );
		}
		if ( ! $hero_base64 ) {
			$gallery = get_post_meta( $actividad_id, 'galeria_fotos', true ) ?: [];
			if ( is_array( $gallery ) && ! empty( $gallery ) ) {
				$gallery_id = is_numeric( $gallery[0] ) ? (int) $gallery[0] : attachment_url_to_postid( $gallery[0] );
				if ( $gallery_id ) {
					$hero_base64 = self::image_to_base64( $gallery_id, $dimensions['width'], $dimensions['height'] );
				}
			}
		}

		// QR
		$qr_image = '';
		$qr_result = QR_Generator::generate( get_permalink( $actividad_id ), $actividad_id );
		if ( ! is_wp_error( $qr_result ) ) {
			$qr_path  = $qr_result['path'] ?? '';
			if ( $qr_path && file_exists( $qr_path ) ) {
				$qr_data  = file_get_contents( $qr_path );
				$qr_image = 'data:image/png;base64,' . base64_encode( $qr_data );
			}
		}

		// Logo
		$logo_image = '';
		$site_icon  = get_site_icon_url( 512 );
		if ( $site_icon ) {
			$logo_data = file_get_contents( $site_icon );
			if ( $logo_data ) {
				$mime = self::get_image_mime( $site_icon );
				$logo_image = 'data:' . $mime . ';base64,' . base64_encode( $logo_data );
			}
		}

		// Format date + time
		$date_raw = $meta['fecha'][0] ?? '';
		$time_raw = $meta['hora'][0] ?? '';
		$date     = $date_raw ? date_i18n( 'j M, Y', strtotime( $date_raw ) ) : '';
		$time     = $time_raw ? date_i18n( 'H:i', strtotime( $time_raw ) ) : '';
		if ( $time_raw && str_contains( $time_raw, '-' ) ) {
			$parts = explode( '-', $time_raw );
			$time  = trim( $parts[0] ) . ' – ' . trim( $parts[1] ?? '' );
		}

		return [
			'title'         => $post->post_title,
			'subtitle'      => wp_trim_words( $post->post_excerpt ?: $meta['descripcion_corta'][0] ?? '', 20 ),
			'date'          => $date,
			'time'          => $time,
			'location'      => $meta['lugar'][0] ?? '',
			'price'         => $price,
			'type_label'    => $style['label'] ?? $type_slug,
			'type_icon'     => $style['icon'] ?? '🌿',
			'type_color'    => $style['color'] ?? $primary,
			'hero_image'    => $hero_base64,
			'logo_image'    => $logo_image,
			'qr_image'      => $qr_image,
			'primary_color' => $primary,
			'accent_color'  => $accent,
			'org_name'      => get_bloginfo( 'name' ),
			'format'        => $format,
			'width'         => $dimensions['width'],
			'height'        => $dimensions['height'],
		];
	}

	/**
	 * Compile the HTML template with activity data.
	 */
	private static function compile_html( string $template_slug, array $data, string $format ): string|\WP_Error {
		// Try HTML template first
		$html_file = CONV_ENROLL_DIR . "templates/html/{$template_slug}.php";
		if ( file_exists( $html_file ) ) {
			ob_start();
			// Extract data array as variables for the template
			extract( $data, EXTR_OVERWRITE );
			include $html_file;
			return ob_get_clean();
		}

		// Fallback: try legacy template
		$legacy_file = CONV_ENROLL_DIR . "media/templates/{$template_slug}.json";
		if ( file_exists( $legacy_file ) ) {
			return self::compile_legacy_html( $template_slug, $data );
		}

		return new \WP_Error( 'template_not_found', "Plantilla '{$template_slug}' no encontrada." );
	}

	/**
	 * Fallback compile: legacy JSON template → basic HTML.
	 */
	private static function compile_legacy_html( string $template_slug, array $data ): string {
		$template = Template_Manager::get_by_slug( $template_slug );
		$config   = $template['config'] ?? [];

		$tokens  = $config['design_tokens'] ?? [];
		$palette = $tokens['palette'] ?? [];
		$primary = $palette['primary'] ?? $data['primary_color'];
		$accent  = $palette['accent'] ?? $data['accent_color'];

		$p = $data;
		$pad = match($format) { 'story' => '60px', 'banner' => '80px', default => '40px' };
		$tsize = match($format) { 'story' => '64px', default => '44px' };

		return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
@page { size: {$p['width']}px {$p['height']}px; margin: 0; }
* { margin:0; padding:0; box-sizing:border-box; }
body { width:{$p['width']}px; height:{$p['height']}px; overflow:hidden; font-family:sans-serif; }
.poster { width:100%; height:100%; background:{$primary}; padding:{$pad}; color:#fff; display:flex; flex-direction:column; }
.title { font-size:{$tsize}; font-weight:700; margin-bottom:20px; }
.meta { font-size:18px; opacity:0.9; }
.footer { margin-top:auto; font-size:14px; opacity:0.7; }
</style></head><body>
<div class="poster">
<div class="title">{$p['type_icon']} {$p['title']}</div>
<div class="meta">📅 {$p['date']} ⏰ {$p['time']} 📍 {$p['location']}<br>{$p['price']}</div>
<div class="footer">{$p['org_name']}</div>
</div></body></html>
HTML;
	}

	/**
	 * Pass 2: Render HTML → PDF via Dompdf (from convoca-core).
	 */
	private static function html_to_pdf( string $html, int $width, int $height ): string|\WP_Error {
		$upload = wp_upload_dir();
		$temp_dir = trailingslashit( $upload['basedir'] ) . self::TEMP_DIR;
		wp_mkdir_p( $temp_dir );

		$pdf_path = tempnam( $temp_dir, 'conv-pdf-' ) . '.pdf';

		try {
			if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
				return new \WP_Error( 'dompdf_missing', 'Dompdf no está disponible. ¿Está activo convoca-core?' );
			}

			$dompdf = new \Dompdf\Dompdf();
			$dompdf->set_option( 'isRemoteEnabled', true );
			$dompdf->set_option( 'isHtml5ParserEnabled', true );
			$dompdf->set_option( 'defaultFont', 'Outfit' );
			$dompdf->set_option( 'defaultMediaType', 'print' );
			$dompdf->set_option( 'isFontSubsettingEnabled', true );

			// Set paper size to exact pixel dimensions
			// Dompdf accepts custom sizes in points: width_pt x height_pt
			// 1px = 0.75pt at 96 DPI base
			$width_pt  = round( $width * 0.75 );
			$height_pt = round( $height * 0.75 );
			$dompdf->setPaper( array( 0, 0, $width_pt, $height_pt ) );

			$dompdf->loadHtml( $html );
			$dompdf->render();

			$pdf_content = $dompdf->output();
			file_put_contents( $pdf_path, $pdf_content );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'pdf_error', 'Error al generar PDF: ' . $e->getMessage() );
		}

		if ( ! file_exists( $pdf_path ) || filesize( $pdf_path ) < 100 ) {
			return new \WP_Error( 'pdf_empty', 'El PDF generado está vacío o es inválido.' );
		}

		return $pdf_path;
	}

	/**
	 * Pass 3: Rasterize PDF → Image via Imagick.
	 */
	private static function pdf_to_image( string $pdf_path, string $output_path, int $width, int $height, string $export = 'png' ): bool|\WP_Error {
		if ( ! extension_loaded( 'imagick' ) ) {
			return new \WP_Error( 'imagick_missing', 'Imagick no está disponible.' );
		}

		try {
			$density = max( 72, (int) round( $width / 3 ) ); // ~360 DPI for 1080px

			$imagick = new \Imagick();
			$imagick->setResolution( $density, $density );

			if ( ! file_exists( $pdf_path ) ) {
				return new \WP_Error( 'pdf_not_found', 'Archivo PDF temporal no encontrado.' );
			}

			$imagick->readImage( $pdf_path . '[0]' );

			// Set output format
			$format_map = [
				'png'  => 'png32',
				'jpg'  => 'jpeg',
				'jpeg' => 'jpeg',
				'webp' => 'webp',
			];
			$img_format = $format_map[ $export ] ?? 'png32';
			$imagick->setImageFormat( $img_format );

			// Resize to exact dimensions
			$imagick->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1, true );

			// Strip metadata for smaller files
			$imagick->stripImage();

			// Set quality
			if ( in_array( $img_format, [ 'jpeg', 'webp' ], true ) ) {
				$imagick->setImageCompressionQuality( 92 );
			}

			$imagick->writeImage( $output_path );
			$imagick->clear();
			$imagick->destroy();

			if ( ! file_exists( $output_path ) || filesize( $output_path ) < 100 ) {
				return new \WP_Error( 'image_empty', 'La imagen generada está vacía.' );
			}

		} catch ( \Exception $e ) {
			return new \WP_Error( 'imagick_error', 'Error al rasterizar PDF: ' . $e->getMessage() );
		}

		return true;
	}

	/**
	 * Convert an attachment image to base64 data URI.
	 */
	private static function image_to_base64( int $attachment_id, int $req_w, int $req_h ): string {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			return '';
		}

		$mime = self::get_image_mime( $path );

		try {
			$img = new \Imagick( $path );
			// Resize if too large (max 1920px on longest side for performance)
			$geo  = $img->getImageGeometry();
			$long = max( $geo['width'], $geo['height'] );
			if ( $long > 1920 ) {
				$img->resizeImage( 1920, 0, \Imagick::FILTER_LANCZOS, 1 );
			}
			$img->setImageFormat( 'jpeg' );
			$img->setImageCompressionQuality( 85 );
			$blob = $img->getImageBlob();
			$img->clear();
			$img->destroy();
		} catch ( \Exception $e ) {
			return '';
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $blob );
	}

	/**
	 * Get MIME type for an image path.
	 */
	private static function get_image_mime( string $path ): string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$map = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
		];
		return $map[ $ext ] ?? 'image/jpeg';
	}

	/**
	 * Get pixel dimensions for each format.
	 */
	public static function format_dimensions( string $format ): array {
		$map = [
			'square'   => [ 'width' => 1080, 'height' => 1080 ],
			'portrait' => [ 'width' => 1080, 'height' => 1350 ],
			'story'    => [ 'width' => 1080, 'height' => 1920 ],
			'facebook' => [ 'width' => 1200, 'height' => 630 ],
			'banner'   => [ 'width' => 1920, 'height' => 1080 ],
			'a4'       => [ 'width' => 2480, 'height' => 3508 ],
		];
		return $map[ $format ] ?? $map['square'];
	}

	/**
	 * List available HTML templates.
	 */
	public static function list_html_templates(): array {
		$dir = CONV_ENROLL_DIR . 'templates/html/';
		if ( ! is_dir( $dir ) ) {
			return [];
		}
		$templates = [];
		foreach ( glob( $dir . '*.php' ) as $file ) {
			$slug = basename( $file, '.php' );
			$templates[] = [
				'slug' => $slug,
				'file' => $file,
				'type' => 'v3-html',
			];
		}
		return $templates;
	}
}