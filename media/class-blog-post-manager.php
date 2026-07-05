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
 * Blog Post Manager — auto-create/reuse blog posts for activities.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle creation and management of blog posts associated with activities.
 */
class Blog_Post_Manager {

	const META_BLOG_POST_ID = '_convoca_media_blog_post_id';
	const META_ACTIVIDAD_ID = '_convoca_media_actividad_id';

	/**
	 * Create or reuse a blog post for the given activity.
	 *
	 * @param int         $actividad_id Activity post ID.
	 * @param string|null $poster_url   Optional poster image URL to embed.
	 * @param string      $status       Post status (draft, publish, pending).
	 * @return int|\WP_Error Post ID.
	 */
	public static function create_or_update( int $actividad_id, ?string $poster_url = null, string $status = 'draft' ) {
		// Check if post already exists.
		$existing = get_post_meta( $actividad_id, self::META_BLOG_POST_ID, true );
		if ( $existing && get_post( $existing ) ) {
			return self::update( $existing, $actividad_id, $poster_url, $status );
		}

		return self::create( $actividad_id, $poster_url, $status );
	}

	/**
	 * Create a new blog post from activity data.
	 *
	 * @param int         $actividad_id Activity ID.
	 * @param string|null $poster_url   Poster image URL.
	 * @param string      $status       Post status.
	 * @return int|\WP_Error
	 */
	private static function create( int $actividad_id, ?string $poster_url, string $status ) {
		$actividad = get_post( $actividad_id );
		if ( ! $actividad ) {
			return new \WP_Error( 'invalid_activity', __( 'Actividad no encontrada.', 'convoca-enroll' ) );
		}

		$meta_prefix     = 'convoca_';
		$fecha_inicio    = get_post_meta( $actividad_id, $meta_prefix . 'fecha_inicio', true );
		$fecha_fin       = get_post_meta( $actividad_id, $meta_prefix . 'fecha_fin', true );
		$ubicacion       = get_post_meta( $actividad_id, $meta_prefix . 'ubicacion', true );
		$precio_socio    = get_post_meta( $actividad_id, $meta_prefix . 'precio_socio', true );
		$plazas          = get_post_meta( $actividad_id, $meta_prefix . 'plazas_totales', true );
		$permalink       = get_permalink( $actividad_id );
		$inscripcion_url = $permalink ? $permalink . '#inscribirme' : '';

		$content = self::build_content( $actividad, $poster_url, $fecha_inicio, $fecha_fin, $ubicacion, $precio_socio, $plazas, $inscripcion_url );

		$post_id = wp_insert_post(
			array(
				'post_title'   => $actividad->post_title,
				'post_content' => $content,
				'post_excerpt' => get_the_excerpt( $actividad_id ) ?: wp_trim_words( $actividad->post_content, 40 ),
				'post_status'  => $status,
				'post_type'    => 'post',
				'post_author'  => get_post_field( 'post_author', $actividad_id ),
				'tags_input'   => self::get_tags( $actividad_id ),
				'category'     => self::get_category_id(),
				'meta_input'   => array(
					self::META_ACTIVIDAD_ID => $actividad_id,
					'_convoca_schema_event'    => self::build_schema( $actividad, $fecha_inicio, $fecha_fin, $ubicacion, $permalink ),
				),
			) 
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Link activity -> blog post.
		update_post_meta( $actividad_id, self::META_BLOG_POST_ID, $post_id );

		// Set featured image from poster.
		if ( $poster_url ) {
			self::set_featured_image_from_url( $post_id, $poster_url, $actividad_id );
		} elseif ( has_post_thumbnail( $actividad_id ) ) {
			set_post_thumbnail( $post_id, get_post_thumbnail_id( $actividad_id ) );
		}

		\Convoca\Enroll\Media\Media_Logger::log(
			'blog_post',
			$post_id,
			'created',
			'ok',
			array(
				'actividad_id' => $actividad_id,
				'status'       => $status,
			) 
		);

		return $post_id;
	}

	/**
	 * Update an existing blog post with fresh activity data.
	 */
	private static function update( int $post_id, int $actividad_id, ?string $poster_url, string $status ): int {
		$actividad = get_post( $actividad_id );
		if ( ! $actividad ) {
			return $post_id;
		}

		$meta_prefix     = 'convoca_';
		$fecha_inicio    = get_post_meta( $actividad_id, $meta_prefix . 'fecha_inicio', true );
		$fecha_fin       = get_post_meta( $actividad_id, $meta_prefix . 'fecha_fin', true );
		$ubicacion       = get_post_meta( $actividad_id, $meta_prefix . 'ubicacion', true );
		$precio_socio    = get_post_meta( $actividad_id, $meta_prefix . 'precio_socio', true );
		$plazas          = get_post_meta( $actividad_id, $meta_prefix . 'plazas_totales', true );
		$permalink       = get_permalink( $actividad_id );
		$inscripcion_url = $permalink ? $permalink . '#inscribirme' : '';

		$content = self::build_content( $actividad, $poster_url, $fecha_inicio, $fecha_fin, $ubicacion, $precio_socio, $plazas, $inscripcion_url );

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => $actividad->post_title,
				'post_content' => $content,
				'post_excerpt' => get_the_excerpt( $actividad_id ) ?: wp_trim_words( $actividad->post_content, 40 ),
				'post_status'  => $status,
			) 
		);

		// Update schema.
		update_post_meta( $post_id, '_convoca_schema_event', self::build_schema( $actividad, $fecha_inicio, $fecha_fin, $ubicacion, $permalink ) );

		// Update featured image if poster changed.
		if ( $poster_url ) {
			self::set_featured_image_from_url( $post_id, $poster_url, $actividad_id );
		}

		\Convoca\Enroll\Media\Media_Logger::log(
			'blog_post',
			$post_id,
			'updated',
			'ok',
			array(
				'actividad_id' => $actividad_id,
			) 
		);

		return $post_id;
	}

	/**
	 * Build Gutenberg-ready post content.
	 */
	private static function build_content( \WP_Post $actividad, ?string $poster_url, $fecha_inicio, $fecha_fin, $ubicacion, $precio_socio, $plazas, $inscripcion_url ): string {
		$blocks = array();

		// 1. Poster image if available.
		if ( $poster_url ) {
			$blocks[] = sprintf(
				'<!-- wp:image {"align":"full","sizeSlug":"large"} -->
				<figure class="wp-block-image alignfull size-large"><img src="%s" alt="%s" /></figure>
				<!-- /wp:image -->',
				esc_url( $poster_url ),
				esc_attr( $actividad->post_title )
			);
		}

		// 2. Activity content.
		$blocks[] = '<!-- wp:paragraph --><p>' . wp_kses_post( $actividad->post_content ) . '</p><!-- /wp:paragraph -->';

		// 3. Event details.
		$details = array();
		if ( $fecha_inicio ) {
			$details[] = sprintf( '<strong>📅 %s:</strong> %s', __( 'Fecha', 'convoca-enroll' ), \Convoca\Core\Utils::format_date( $fecha_inicio, 'j F Y' ) );
		}
		if ( $fecha_fin ) {
			$details[] = sprintf( '<strong>⏰ %s:</strong> %s', __( 'Fin', 'convoca-enroll' ), \Convoca\Core\Utils::format_date( $fecha_fin, 'j F Y' ) );
		}
		if ( $ubicacion ) {
			$details[] = sprintf( '<strong>📍 %s:</strong> %s', __( 'Ubicación', 'convoca-enroll' ), esc_html( $ubicacion ) );
		}
		if ( $plazas ) {
			$details[] = sprintf( '<strong>👥 %s:</strong> %d', __( 'Plazas', 'convoca-enroll' ), $plazas );
		}
		if ( $precio_socio !== '' ) {
			$details[] = sprintf( '<strong>💰 %s:</strong> %s€', __( 'Precio socio', 'convoca-enroll' ), $precio_socio );
		}

		if ( $details ) {
			$blocks[] = '<!-- wp:group {"style":{"spacing":{"padding":"16px"}},"backgroundColor":"gray-100"} -->
			<div class="wp-block-group has-gray-100-background-color has-background" style="padding:16px">'
				. implode( '<br>', $details ) . '</div>
			<!-- /wp:group -->';
		}

		// 4. Inscription CTA
		if ( $inscripcion_url ) {
			$blocks[] = sprintf(
				'<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
				<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"naranja","textColor":"blanco"} -->
				<div class="wp-block-button"><a class="wp-block-button__link has-blanco-color has-naranja-background-color has-text-color has-background" href="%s">%s</a></div>
				<!-- /wp:button --></div>
				<!-- /wp:buttons -->',
				esc_url( $inscripcion_url ),
				esc_html__( 'Inscríbete en esta actividad', 'convoca-enroll' )
			);
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Build Schema.org Event JSON-LD.
	 */
	private static function build_schema( \WP_Post $actividad, $fecha_inicio, $fecha_fin, $ubicacion, $permalink ): string {
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => $actividad->post_title,
			'description' => wp_trim_words( $actividad->post_content, 30 ),
			'url'         => $permalink,
			'startDate'   => $fecha_inicio ? gmdate( 'c', strtotime( $fecha_inicio ) ) : '',
			'endDate'     => $fecha_fin ? gmdate( 'c', strtotime( $fecha_fin ) ) : '',
			'location'    => $ubicacion ? array(
				'@type' => 'Place',
				'name'  => $ubicacion,
			) : null,
		);

		return wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Set featured image from a remote/local URL.
	 */
	private static function set_featured_image_from_url( int $post_id, string $url, int $actividad_id ): void {
		// Check if it's a local file.
		$upload_dir = wp_upload_dir();
		$local_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		if ( file_exists( $local_path ) ) {
			$filetype = wp_check_filetype( $local_path );
			$filename = 'poster-actividad-' . $actividad_id . '.' . ( $filetype['ext'] ?? 'png' );

			$attachment_id = media_handle_sideload(
				array(
					'name'     => $filename,
					'tmp_name' => $local_path,
				),
				$post_id 
			);

			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}
	}

	/**
	 * Get tags from activity.
	 */
	private static function get_tags( int $actividad_id ): array {
		$tags = array( 'actividad', 'convoca' );
		$tipo = get_post_meta( $actividad_id, '_convoca_tipo_actividad', true );
		if ( $tipo ) {
			$tags[] = $tipo;
		}
		return $tags;
	}

	/**
	 * Get or create default category for activity posts.
	 */
	private static function get_category_id(): int {
		$cat = get_term_by( 'slug', 'actividades', 'category' );
		if ( $cat ) {
			return (int) $cat->term_id;
		}
		$new = wp_insert_term( 'Actividades', 'category', array( 'slug' => 'actividades' ) );
		if ( ! is_wp_error( $new ) ) {
			return (int) $new['term_id'];
		}
		return 1; // Uncategorized fallback.
	}
}
