<?php
/**
 * REST API for activities and inscriptions.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_API {


	private const NS                = 'convoca-enroll/v1';
	private const RATE_LIMIT_MAX    = 5;
	private const RATE_LIMIT_WINDOW = 3600;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		/* Public: activities */
		register_rest_route(
			self::NS,
			'/actividades',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_activities' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'per_page' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/actividades/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_activity' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/me/session-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_session_status' ),
				'permission_callback' => '__return_true',
			)
		);

		/* Admin: inscriptions */
		register_rest_route(
			self::NS,
			'/inscripciones',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_inscriptions' ),
				'permission_callback' => fn() => current_user_can( 'manage_inscripciones' ),
				'args'                => array(
					'actividad_id' => array( 'type' => 'integer' ),
					'estado'       => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/inscripciones/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'update_inscription' ),
				'permission_callback' => fn() => current_user_can( 'manage_inscripciones' ),
				'args'                => array(
					'estado'     => array( 'type' => 'string' ),
					'asistencia' => array( 'type' => 'string' ),
				),
			)
		);

		/* Admin: stats */
		register_rest_route(
			self::NS,
			'/stats/(?P<actividad_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => fn() => current_user_can( 'manage_inscripciones' ),
			)
		);

		register_rest_route(
			self::NS,
			'/checkin',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_checkin' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_inscripciones' ) || in_array( 'voluntario_aprobado', (array) wp_get_current_user()->roles, true );
				},
			)
		);

		/* Public/Secure: ICS Download */
		register_rest_route(
			self::NS,
			'/ics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'download_ics' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'required' => true,
						'type'     => 'integer',
					),
					'token' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		/* Admin: search users (for chips component) */
		register_rest_route(
			self::NS,
			'/admin/users/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'admin_search_users' ),
				'permission_callback' => fn() => current_user_can( 'manage_inscripciones' ),
				'args'                => array(
					'term' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Shared check for admin endpoints.
	 */
	private function check_permission_for_activity( int $actividad_id ): bool {
		return CPT_Actividad::is_user_responsible( get_current_user_id(), $actividad_id );
	}

	/* ── Public endpoints ──────────────────────── */

	public function list_activities( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'convoca_enroll_list_activities', 30, 60 ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'Demasiadas peticiones.', 'convoca-enroll' ) ), 429 );
		}

		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );

		$cache_key = "actividades_p{$page}_pp{$per_page}";
		$items     = \Convoca\Core\Utils::rest_cache_get(
			$cache_key,
			60,
			function () use ( $per_page, $page ) {
				$all   = CPT_Actividad::get_upcoming( 999 );
				$slice = array_slice( $all, ( $page - 1 ) * $per_page, $per_page );
				return array_map( array( $this, 'fmt_activity' ), $slice );
			}
		);

		// Total count varies by page — compute total outside cache.
		$total = count( CPT_Actividad::get_upcoming( 999 ) );

		$response = new \WP_REST_Response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	public function get_activity( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'convoca_enroll_get_activity', 30, 60 ) ) {
			return new \WP_REST_Response( array( 'error' => __( 'Demasiadas peticiones.', 'convoca-enroll' ) ), 429 );
		}
		$id   = (int) $req['id'];
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'actividad' ) {
			return new \WP_Error( 'not_found', __( 'Actividad no encontrada.', 'convoca-enroll' ), array( 'status' => 404 ) );
		}
		return new \WP_REST_Response( $this->fmt_activity( $post ) );
	}

	// Removed: inscribir method (unreachable/insecure).

	public function get_session_status(): \WP_REST_Response {
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'session_status', 20, 60 ) ) {
			return new \WP_REST_Response( array( 'error' => 'Too many requests' ), 429 );
		}

		if ( \Convoca\Core\Features::is_members_active() && \Convoca\Members\Member_Auth::is_authenticated() ) {
			return new \WP_REST_Response(
				array(
					'authenticated' => true,
					'member_id'     => (int) \Convoca\Members\Member_Auth::get_current_member_id(),
				),
				200
			);
		}
		return new \WP_REST_Response( array( 'authenticated' => false ), 200 );
	}

	/* ── Admin endpoints ───────────────────────── */

	public function list_inscriptions( \WP_REST_Request $req ): \WP_REST_Response {
		$args = array(
			'post_type'      => 'inscripcion',
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_query = array();
		if ( $req->get_param( 'actividad_id' ) ) {
			$meta_query[] = array(
				'key'   => CPT_Inscripcion::META_PREFIX . 'actividad_id',
				'value' => (int) $req['actividad_id'],
			);
		}
		if ( $req->get_param( 'estado' ) ) {
			$meta_query[] = array(
				'key'   => CPT_Inscripcion::META_PREFIX . 'estado',
				'value' => sanitize_text_field( $req['estado'] ),
			);
		}
		if ( $meta_query ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_query );
		}

		$query = new \WP_Query( $args );
		$items = array_map( fn( $p ) => $this->fmt_inscription( $p ), $query->posts );

		// Filter items if user is monitor and no activity_id was provided (extra safety).
		if ( ! current_user_can( 'manage_options' ) ) {
			$allowed_ids = CPT_Actividad::get_allowed_activities_ids();
			$items       = array_filter( $items, fn( $item ) => in_array( $item['actividad_id'], $allowed_ids ?? array(), true ) );
		}

		return new \WP_REST_Response(
			array(
				'items' => array_values( $items ),
				'total' => count( $items ),
			)
		);
	}

	public function update_inscription( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$id     = (int) $req['id'];
		$act_id = (int) CPT_Inscripcion::get_meta( $id, 'actividad_id' );

		if ( ! $this->check_permission_for_activity( $act_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'No tienes permiso para gestionar esta actividad.', array( 'status' => 403 ) );
		}

		$estado = sanitize_text_field( $req['estado'] );

		if ( $estado === 'cancelada' ) {
			$result = Motor_Inscripcion::cancelar( $id );
		} elseif ( $estado === 'confirmada' ) {
			$result = Motor_Inscripcion::confirmar( $id );
		} else {
			if ( $estado ) {
				CPT_Inscripcion::update_meta( $id, 'estado', $estado );
			}
			$result = true;
		}

		$asistencia = sanitize_text_field( $req->get_param( 'asistencia' ) );
		if ( $asistencia ) {
			Motor_Inscripcion::set_asistencia( $id, $asistencia );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $this->fmt_inscription( get_post( $id ) ) );
	}

	public function get_stats( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error {
		$act_id = (int) $req['actividad_id'];
		if ( ! $this->check_permission_for_activity( $act_id ) ) {
			return new \WP_Error( 'rest_forbidden', 'No tienes permiso para ver estadísticas de esta actividad.', array( 'status' => 403 ) );
		}

		$counts = CPT_Inscripcion::count_by_activity( $act_id );
		$meta   = CPT_Actividad::get_meta( $act_id );
		$total  = (int) ( $meta['plazas_totales'] ?? 0 );
		$disp   = (int) ( $meta['plazas_disponibles'] ?? 0 );
		$ocup   = $total > 0 ? round( ( ( $total - $disp ) / $total ) * 100 ) : 0;

		return new \WP_REST_Response(
			array(
				'counts'        => $counts,
				'plazas_total'  => $total,
				'plazas_disp'   => $disp,
				'ocupacion_pct' => $ocup,
			)
		);
	}

	public function handle_checkin( \WP_REST_Request $request ): \WP_REST_Response {
		$token      = sanitize_text_field( $request['code'] ?? $request['token'] ?? '' );
		$monitor_id = get_current_user_id();

		if ( empty( $token ) ) {
			return new \WP_REST_Response( array( 'error' => 'Token required' ), 400 );
		}

		$posts = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'meta_query'     => array(
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'checkin_token',
						'value' => $token,
					),
				),
				'posts_per_page' => 1,
				'post_status'    => 'publish',
			)
		);

		if ( empty( $posts ) ) {
			\Convoca\Core\Logger::info( "Intento de check-in fallido: Token inválido ($token)", 'Enroll/Checkin', $monitor_id );
			return new \WP_REST_Response( array( 'error' => 'Invalid token' ), 404 );
		}

		$insc    = $posts[0];
		$insc_id = $insc->ID;
		$act_id  = (int) CPT_Inscripcion::get_meta( $insc_id, 'actividad_id' );

		// Check permission.
		if ( ! $this->check_permission_for_activity( $act_id ) ) {
			\Convoca\Core\Logger::info( "Intento de check-in fallido: Usuario sin permisos para actividad #$act_id", 'Enroll/Checkin', $monitor_id, array( 'inscripcion_id' => $insc_id ) );
			return new \WP_REST_Response( array( 'error' => 'No tienes permiso para realizar check-in en esta actividad.' ), 403 );
		}

		// Verify status is confirmed.
		$estado = CPT_Inscripcion::get_meta( $insc_id, 'estado' );
		if ( $estado !== 'confirmada' ) {
			\Convoca\Core\Logger::info( "Intento de check-in fallido: Inscripción #$insc_id no confirmada (Estado: $estado)", 'Enroll/Checkin', $monitor_id );
			return new \WP_REST_Response(
				array(
					'error'   => 'Inscription not confirmed',
					'estado'  => $estado,
					'message' => 'Solo se pueden confirmar asistentes con reserva confirmada.',
				),
				400
			);
		}

		// Update for assistance.
		$result   = Motor_Inscripcion::set_asistencia( $insc_id, 'si' );
		$affected = is_wp_error( $result ) ? 0 : 1;

		if ( $affected > 0 ) {
			\Convoca\Core\Logger::info( "Check-in exitoso vía QR por usuario #$monitor_id", 'Enroll/Checkin', $monitor_id, array( 'inscripcion_id' => $insc_id ) );
		} else {
			\Convoca\Core\Logger::info( "Escaneo de QR repetido por usuario #$monitor_id", 'Enroll/Checkin', $monitor_id, array( 'inscripcion_id' => $insc_id ) );
		}

		// Fetch user data.
		$nombre       = CPT_Inscripcion::get_meta( $insc_id, 'nombre' );
		$es_menor     = CPT_Inscripcion::get_meta( $insc_id, 'es_menor' ) === '1';
		$participante = $es_menor ? CPT_Inscripcion::get_meta( $insc_id, 'nombre_participante' ) : $nombre;

		return new \WP_REST_Response(
			array(
				'success'         => true,
				'nombre'          => $participante,
				'already_checked' => ( $affected === 0 ),
				'message'         => ( $affected === 0 ) ? 'Esta reserva ya había sido escaneada.' : 'Check-in realizado correctamente.',
			)
		);
	}

	/**
	 * Public endpoint to download ICS.
	 */
	public function download_ics( \WP_REST_Request $req ): void {
		$id    = (int) $req['id'];
		$token = sanitize_text_field( $req['token'] );

		// Check if ID is activity or inscription.
		$post = get_post( $id );
		if ( ! $post ) {
			wp_die( 'No encontrado.', 404 );
		}

		if ( $post->post_type === 'actividad' ) {
			// For activities, token must match a hash of the activity ID or just be public if preferred.
			// But let's use checkin_token of any inscription if they want it for THEIR activity.
			// Actually, let's just use the activity ID hash for public activities.
			$expected = hash_hmac( 'sha256', (string) $id, \Convoca\Core\Utils::get_persistent_salt() );
			if ( $token !== $expected ) {
				wp_die( 'Acceso denegado.', 403 );
			}
			$calendar = new Google_Calendar();
			$calendar->serve_ics( $id );
		} elseif ( $post->post_type === 'inscripcion' ) {
			// Validate checkin token.
			$stored_token = CPT_Inscripcion::get_meta( $id, 'checkin_token' );
			if ( ! $stored_token || ! hash_equals( $stored_token, $token ) ) {
				wp_die( 'Token de seguridad inválido.', 403 );
			}
			$calendar = new Google_Calendar();
			$calendar->serve_ics( $id, true );
		} else {
			wp_die( 'Tipo de post no válido.', 400 );
		}
	}


	/* ── Formatters ────────────────────────────── */

	private function fmt_activity( \WP_Post $p ): array {
		$meta = CPT_Actividad::get_meta( $p->ID );
		return array(
			'id'        => $p->ID,
			'titulo'    => $p->post_title,
			'extracto'  => $p->post_excerpt,
			'permalink' => get_permalink( $p ),
		) + $meta;
	}

	private function fmt_inscription( \WP_Post $p ): array {
		$m = fn( $k ) => CPT_Inscripcion::get_meta( $p->ID, $k );
		return array(
			'id'           => $p->ID,
			'nombre'       => $m( 'nombre' ),
			'email'        => $m( 'email' ),
			'telefono'     => $m( 'telefono' ),
			'es_socio'     => $m( 'es_socio' ) === '1',
			'estado'       => $m( 'estado' ),
			'asistencia'   => $m( 'asistencia' ) ?: 'no_registrada',
			'actividad_id' => (int) $m( 'actividad_id' ),
			'actividad'    => get_the_title( (int) $m( 'actividad_id' ) ),
			'fecha'        => get_the_date( 'Y-m-d H:i', $p ),
			// 'checkin_token' => $m('checkin_token'), // HIDDEN for security.
		);
	}

	private function check_rate_limit( string $action ): bool {
		return \Convoca\Core\Utils::check_rate_limit( $action, self::RATE_LIMIT_MAX, self::RATE_LIMIT_WINDOW );
	}

	/**
	 * Search users for chips component.
	 */
	public function admin_search_users( \WP_REST_Request $req ): \WP_REST_Response {
		$term = sanitize_text_field( $req->get_param( 'term' ) ?? '' );
		if ( strlen( $term ) < 2 ) {
			return new \WP_REST_Response( array(), 200 );
		}

		$users   = get_users(
			array(
				'role__in'       => array( 'administrator', 'editor', 'monitor_actividad', 'monitor' ),
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'display_name', 'user_email', 'user_login' ),
				'number'         => 20,
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);
		$results = array();
		foreach ( $users as $u ) {
			$results[] = array(
				'id'    => $u->ID,
				'name'  => $u->display_name,
				'email' => $u->user_email,
			);
		}
		return new \WP_REST_Response( $results, 200 );
	}
}
