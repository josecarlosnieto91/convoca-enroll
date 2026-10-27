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
 * Media & Social REST API endpoints.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register REST routes for poster generation, templates, and blog posts.
 */
class Media_Rest_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		// ── Poster ──
		register_rest_route( 'convoca/v1', '/media/poster/render', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'render_poster' ),
			'permission_callback' => array( $this, 'can_manage_media' ),
			'args'                => $this->poster_args(),
		) );

		register_rest_route( 'convoca/v1', '/media/poster/regenerate', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'regenerate_poster' ),
			'permission_callback' => array( $this, 'can_manage_media' ),
			'args'                => array(
				'actividad_id' => array( 'required' => true, 'validate_callback' => 'absint' ),
				'template'     => array( 'required' => false, 'default' => 'naturaleza' ),
			),
		) );

		// ── Templates ──
		register_rest_route( 'convoca/v1', '/media/templates', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_templates' ),
			'permission_callback' => array( $this, 'can_manage_media' ),
		) );

		register_rest_route( 'convoca/v1', '/media/templates/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_template' ),
			'permission_callback' => array( $this, 'can_manage_media' ),
		) );

		// ── Blog Post ──
		register_rest_route( 'convoca/v1', '/media/blog/create', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'create_blog_post' ),
			'permission_callback' => array( $this, 'can_manage_media' ),
			'args'                => array(
				'actividad_id' => array( 'required' => true, 'validate_callback' => 'absint' ),
				'status'       => array( 'required' => false, 'default' => 'draft' ),
			),
		) );
	}


	/**
	 * Resuelve un colaborador del módulo de medios, permitiendo sustituirlo con un filtro.
	 *
	 * El endpoint no tiene por qué construir sus colaboradores a pelo: sin nadie enganchado al filtro el
	 * comportamiento es exactamente el de antes (se usa la clase real), y una prueba puede aportar su propio
	 * motor sin arrastrar Imagick ni la base de datos. Es aditivo y reversible.
	 *
	 * @param string   $nombre  Nombre corto del colaborador (`poster`, `qr`, `templates`, `blog`).
	 * @param callable $defecto Implementación real, usada si nadie filtra.
	 * @return callable
	 */
	private function colaborador( string $nombre, callable $defecto ): callable {
		$inyectado = apply_filters( 'convoca_media_' . $nombre, null );

		return is_callable( $inyectado ) ? $inyectado : $defecto;
	}

	public function render_poster( \WP_REST_Request $request ): \WP_REST_Response {
		$actividad_id = (int) $request->get_param( 'actividad_id' );
		$template     = $request->get_param( 'template' ) ?: 'naturaleza';
		$format       = $request->get_param( 'format' ) ?: 'square';
		$image_id     = absint( $request->get_param( 'image_id' ) ?: 0 );
		$force        = (bool) $request->get_param( 'force' );
		$export       = $request->get_param( 'export' ) ?: 'png';

		$render = $this->colaborador( 'poster', static function ( $id, $slug, $opciones ) {
			return Poster_Engine::render( $id, $slug, $opciones );
		} );

		$result = $render( $actividad_id, $template, array(
			'formats'  => array( $format ),
			'image_id' => $image_id,
			'force'    => $force,
			'export'   => $export,
		) );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		\Convoca\Enroll\Media\Media_Logger::log( 'poster', $actividad_id, 'generated', 'ok', array(
			'template' => $template,
			'format'   => $format,
			'url'      => $result['url'],
		) );

		return new \WP_REST_Response( $result, 200 );
	}

	public function regenerate_poster( \WP_REST_Request $request ): \WP_REST_Response {
		$actividad_id = (int) $request->get_param( 'actividad_id' );
		$template     = $request->get_param( 'template' ) ?: 'naturaleza';

		$invalidar = $this->colaborador( 'qr', static function ( $id ) {
			QR_Generator::invalidate( $id );
		} );
		$invalidar( $actividad_id );

		$render = $this->colaborador( 'poster', static function ( $id, $slug, $opciones ) {
			return Poster_Engine::render( $id, $slug, $opciones );
		} );
		$result = $render( $actividad_id, $template, array( 'force' => true ) );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		\Convoca\Enroll\Media\Media_Logger::log( 'poster', $actividad_id, 'regenerated', 'ok', array(
			'template' => $template,
		) );

		return new \WP_REST_Response( $result, 200 );
	}

	public function list_templates(): \WP_REST_Response {
		$listar    = $this->colaborador( 'templates', static function () {
			return Template_Manager::get_all();
		} );
		$templates = $listar();
		return new \WP_REST_Response( $templates, 200 );
	}

	public function get_template( \WP_REST_Request $request ): \WP_REST_Response {
		$buscar = $this->colaborador( 'template', static function ( $id ) {
			return Template_Manager::get( $id );
		} );
		$tpl    = $buscar( (int) $request->get_param( 'id' ) );
		if ( ! $tpl ) {
			return new \WP_REST_Response( array( 'error' => __( 'Template not found', 'convoca-enroll' ) ), 404 );
		}
		return new \WP_REST_Response( $tpl, 200 );
	}

	public function create_blog_post( \WP_REST_Request $request ): \WP_REST_Response {
		$actividad_id = (int) $request->get_param( 'actividad_id' );
		$status       = $request->get_param( 'status' ) ?: 'draft';

		$crear   = $this->colaborador( 'blog', static function ( $id, $previo, $estado ) {
			return Blog_Post_Manager::create_or_update( $id, $previo, $estado );
		} );
		$post_id = $crear( $actividad_id, null, $status );
		if ( is_wp_error( $post_id ) ) {
			return new \WP_REST_Response( array( 'error' => $post_id->get_error_message() ), 400 );
		}

		return new \WP_REST_Response( array(
			'post_id'  => $post_id,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'status'   => get_post_status( $post_id ),
		), 200 );
	}

	public function can_manage_media(): bool {
		return current_user_can( 'convoca_manage_media' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Documentación del método.
	 *
	 * @return array<string, mixed>
	 */
	private function poster_args(): array {
		return array(
			'actividad_id' => array( 'required' => true, 'validate_callback' => 'absint' ),
			'template'     => array( 'required' => false, 'default' => 'naturaleza' ),
			'format'       => array( 'required' => false, 'default' => 'square' ),
			'image_id'     => array( 'required' => false, 'validate_callback' => 'absint' ),
			'force'        => array( 'required' => false, 'default' => false ),
			'export'       => array( 'required' => false, 'default' => 'png' ),
		);
	}
}
