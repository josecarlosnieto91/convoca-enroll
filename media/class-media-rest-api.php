<?php
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

	public function render_poster( \WP_REST_Request $request ): \WP_REST_Response {
		$actividad_id = (int) $request->get_param( 'actividad_id' );
		$template     = $request->get_param( 'template' ) ?: 'naturaleza';
		$format       = $request->get_param( 'format' ) ?: 'square';
		$image_id     = absint( $request->get_param( 'image_id' ) ?: 0 );
		$force        = (bool) $request->get_param( 'force' );
		$export       = $request->get_param( 'export' ) ?: 'png';

		$result = Poster_Engine::render( $actividad_id, $template, array(
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

		QR_Generator::invalidate( $actividad_id );

		$result = Poster_Engine::render( $actividad_id, $template, array( 'force' => true ) );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'error' => $result->get_error_message() ), 400 );
		}

		\Convoca\Enroll\Media\Media_Logger::log( 'poster', $actividad_id, 'regenerated', 'ok', array(
			'template' => $template,
		) );

		return new \WP_REST_Response( $result, 200 );
	}

	public function list_templates(): \WP_REST_Response {
		$templates = Template_Manager::get_all();
		return new \WP_REST_Response( $templates, 200 );
	}

	public function get_template( \WP_REST_Request $request ): \WP_REST_Response {
		$tpl = Template_Manager::get( (int) $request->get_param( 'id' ) );
		if ( ! $tpl ) {
			return new \WP_REST_Response( array( 'error' => 'Template not found' ), 404 );
		}
		return new \WP_REST_Response( $tpl, 200 );
	}

	public function create_blog_post( \WP_REST_Request $request ): \WP_REST_Response {
		$actividad_id = (int) $request->get_param( 'actividad_id' );
		$status       = $request->get_param( 'status' ) ?: 'draft';

		$post_id = Blog_Post_Manager::create_or_update( $actividad_id, null, $status );
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
