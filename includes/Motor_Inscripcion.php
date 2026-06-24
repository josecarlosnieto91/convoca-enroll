<?php
/**
 * Core enrollment engine — inscription, cancellation, waitlist promotion.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

use Convoca\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Motor_Inscripcion {

	/**
	 * Stores the last PDF generation error message, if any.
	 *
	 * @var string|null
	 */
	public static ?string $last_pdf_error = null;



	public function __construct() {
		// Nothing to hook — methods are called directly.
	}

	/**
	 * Create a new inscription.
	 *
	 * @param int   $actividad_id  Activity post ID.
	 * @param array $datos         { nombre, email, telefono, es_socio, notas }
	 * @return int|\WP_Error  Inscription post ID or error.
	 */
	public static function inscribir( int $actividad_id, array $datos ): int|\WP_Error {
		// Rate limiting: max 5 enrollment attempts per IP per hour.
		if ( ! \Convoca\Core\Utils::check_rate_limit( 'enroll_inscribir', 5, 3600 ) ) {
			return new \WP_Error( 'rate_limit', __( 'Demasiados intentos. Inténtalo de nuevo más tarde.', 'convoca-enroll' ) );
		}

		$actividad = get_post( $actividad_id );
		if ( ! $actividad || $actividad->post_type !== 'actividad' ) {
			return new \WP_Error( 'invalid_activity', __( 'Actividad no encontrada.', 'convoca-enroll' ) );
		}

		// Prevent enrollment in past activities.
		$fecha_inicio = get_post_meta( $actividad_id, CPT_Actividad::META_PREFIX . 'fecha_inicio', true );
		if ( ! empty( $fecha_inicio ) && strtotime( $fecha_inicio ) < time() ) {
			return new \WP_Error( 'activity_ended', __( 'Esta actividad ya ha finalizado.', 'convoca-enroll' ) );
		}

		$is_member   = false;
		$member_data = null;
		if ( class_exists( '\\Convoca\\Members\\Member_Auth' ) && \Convoca\Members\Member_Auth::is_authenticated() ) {
			$member_data = \Convoca\Members\Member_Auth::get_current_member_data();
			$is_member   = true;
		}

		if ( $is_member && $member_data ) {
			$nombre                    = sanitize_text_field( $member_data['name'] );
			$email                     = sanitize_email( $member_data['email'] );
			$tel                       = sanitize_text_field( $member_data['phone'] );
			$dni                       = sanitize_text_field( $member_data['dni'] );
			$datos['tipo_inscripcion'] = 'socio';
			$datos['es_socio']         = true;
		} else {
			// Validate required fields explicitly provided by non-members.
			$nombre = sanitize_text_field( $datos['nombre'] ?? '' );
			$email  = sanitize_email( $datos['email'] ?? '' );
			$tel    = sanitize_text_field( $datos['telefono'] ?? '' );
			$dni    = strtoupper( str_replace( array( ' ', '-' ), '', sanitize_text_field( $datos['dni'] ?? '' ) ) );

			if ( empty( $nombre ) ) {
				return new \WP_Error( 'missing_nombre', __( 'El nombre es obligatorio.', 'convoca-enroll' ) );
			}
			if ( ! is_email( $email ) ) {
				return new \WP_Error( 'invalid_email', __( 'El email no es válido.', 'convoca-enroll' ) );
			}

			// Validar que el dominio del email tenga registros MX.
			$domain = substr( strrchr( $email, '@' ), 1 );
			if ( ! empty( $domain ) && ! checkdnsrr( $domain, 'MX' ) && ! checkdnsrr( $domain, 'A' ) ) {
				return new \WP_Error( 'invalid_email_domain', __( 'El dominio del email no es válido.', 'convoca-enroll' ) );
			}

			if ( ! empty( $dni ) && ! self::validar_dni( $dni ) ) {
				return new \WP_Error( 'invalid_dni', __( 'El DNI/NIE no es válido.', 'convoca-enroll' ) );
			}

			// Always assign Trasgu (socio_dia) to non-logged users.
			$datos['tipo_inscripcion'] = 'socio_dia';
			$datos['es_socio']         = false;
		}

		// Validate RGDP consent.
		$rgpd = $datos['rgpd'] ?? '';
		if ( empty( $rgpd ) ) {
			return new \WP_Error( 'missing_rgpd', __( 'Debes aceptar la política de privacidad.', 'convoca-enroll' ) );
		}

		// Minor/dependent fields.
		$es_menor            = ! empty( $datos['es_menor'] );
		$nombre_participante = sanitize_text_field( $datos['nombre_participante'] ?? '' );
		$edad_participante   = (int) ( $datos['edad_participante'] ?? 0 );

		// Validate minor age is reasonable (0-17).
		if ( $es_menor && ( $edad_participante < 0 || $edad_participante > 17 ) ) {
			return new \WP_Error( 'invalid_edad', __( 'La edad del menor debe estar entre 0 y 17 años.', 'convoca-enroll' ) );
		}

		// Check global one-reservation-per-person rule.
		$settings      = get_option( 'convoca_enroll_settings', array() );
		$limite_activo = ! empty( $settings['limite_una_reserva'] );

		if ( $limite_activo && ! $es_menor ) {
			$limit_error = self::check_limite_reservas( $email );
			if ( is_wp_error( $limit_error ) ) {
				return $limit_error;
			}
		}

		// Check duplicate in same activity.
		$dup_query = array(
			'post_type'      => 'inscripcion',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => CPT_Inscripcion::META_PREFIX . 'actividad_id',
					'value' => $actividad_id,
				),
				array(
					'key'   => CPT_Inscripcion::META_PREFIX . 'email',
					'value' => $email,
				),
				array(
					'key'     => CPT_Inscripcion::META_PREFIX . 'estado',
					'value'   => array( 'cancelada' ),
					'compare' => 'NOT IN',
				),
			),
		);

		// If registering a minor, also match participant name to avoid true duplicates.
		if ( $es_menor && ! empty( $nombre_participante ) ) {
			$dup_query['meta_query'][] = array(
				'key'   => CPT_Inscripcion::META_PREFIX . 'nombre_participante',
				'value' => $nombre_participante,
			);
		}

		$existing = get_posts( $dup_query );

		if ( ! empty( $existing ) ) {
			return new \WP_Error( 'duplicate', __( 'Ya estás inscrito/a en esta actividad.', 'convoca-enroll' ) );
		}

		// Check duplicate by DNI in same activity if enabled or by default.
		$bloquear_dni = $settings['bloquear_dni_duplicado'] ?? '1';
		if ( $bloquear_dni === '1' && ! empty( $dni ) ) {
			$dni_query    = array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'actividad_id',
						'value' => $actividad_id,
					),
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'dni',
						'value' => $dni,
					),
					array(
						'key'     => CPT_Inscripcion::META_PREFIX . 'estado',
						'value'   => array( 'cancelada' ),
						'compare' => 'NOT IN',
					),
				),
			);
			$existing_dni = get_posts( $dni_query );
			if ( ! empty( $existing_dni ) ) {
				return new \WP_Error( 'duplicate_dni', __( 'Ya existe una inscripción activa para este DNI en esta actividad.', 'convoca-enroll' ) );
			}
		}

		// Generate unique reservation code.
		$codigo_reserva = self::generar_codigo();

		// Start transaction for atomicity.
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create inscription post FIRST to get an ID.
			$title_name = $es_menor && $nombre_participante ? $nombre_participante : $nombre;
			$post_id    = wp_insert_post(
				array(
					'post_type'   => 'inscripcion',
					'post_title'  => $title_name . ' — ' . $actividad->post_title,
					'post_status' => 'publish',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $post_id;
			}

			$estado = $datos['estado_forzado'] ?? null;

			// Check capacity atomically - only for states that consume a spot.
			$estados_que_consumen_plaza = array( 'pendiente', 'pendiente_pago' );

			if ( empty( $datos['estado_forzado'] ) || in_array( $datos['estado_forzado'], $estados_que_consumen_plaza, true ) ) {
				$meta_actividad     = CPT_Actividad::get_meta( $actividad_id );
				$plazas_disponibles = (int) ( $meta_actividad['plazas_disponibles'] ?? 0 );

				if ( $plazas_disponibles <= 0 ) {
					$estado = 'lista_espera';
				} else {
					// Atomically decrement plazas for states that consume a spot.
					$affected = $wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->postmeta} 
                         SET meta_value = CAST(meta_value AS SIGNED) - 1 
                         WHERE post_id = %d AND meta_key = '" . CPT_Inscripcion::META_PREFIX . "plazas_disponibles' 
                         AND CAST(meta_value AS SIGNED) > 0",
							$actividad_id
						)
					);

					if ( ! $affected ) {
						$wpdb->query( 'ROLLBACK' );
						return new \WP_Error( 'no_slots', __( 'No hay plazas disponibles.', 'convoca-enroll' ) );
					}
				}
			}

			if ( ! $estado ) {
				if ( ! empty( $meta_actividad['requiere_pago'] ?? false ) ) {
					$estado = 'pendiente_pago';
				} else {
					$estado = 'pendiente';
				}
			}

			// Save meta.
			$meta = array(
				'actividad_id'             => $actividad_id,
				'nombre'                   => $nombre,
				'email'                    => $email,
				'telefono'                 => $tel,
				'dni'                      => $dni,
				'whatsapp'                 => sanitize_text_field( $datos['whatsapp'] ?? 'si' ),
				'es_socio'                 => $datos['es_socio'] ? '1' : '0',
				'tipo_inscripcion'         => $datos['tipo_inscripcion'],
				'estado'                   => $estado,
				'asistencia'               => 'no_registrada',
				'notas'                    => sanitize_textarea_field( $datos['notas'] ?? '' ),
				'consentimiento_timestamp' => current_time( 'mysql' ),
				'consentimiento_version'   => $settings['rgpd_version'] ?? '1.0',
				'codigo_reserva'           => $codigo_reserva,
				'es_menor'                 => $es_menor ? '1' : '0',
				'nombre_participante'      => $nombre_participante,
				'edad_participante'        => $edad_participante,
				'nombre_responsable'       => $es_menor ? $nombre : '',
				'checkin_token'            => self::generar_token_unico(),
			);

			foreach ( $meta as $key => $val ) {
				CPT_Inscripcion::update_meta( $post_id, $key, $val );
			}

			$wpdb->query( 'COMMIT' );

			// Fire hooks.
			\Convoca\Core\Utils::do_action( 'convoca_enroll_inscripcion_nueva', 'convoca_inscripcion_nueva', $post_id, $actividad_id, $estado );

			return $post_id;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( 'Transaction failed in inscribir: ' . $e->getMessage(), 'Enroll/Motor' );
			return new \WP_Error( 'inscription_failed', __( 'Error al procesar la inscripción.', 'convoca-enroll' ) );
		}
	}

	/**
	 * Cancel an inscription and promote waitlist.
	 */
	public static function cancelar( int $inscripcion_id, string $nota = '' ): bool|\WP_Error {

		$post = get_post( $inscripcion_id );
		if ( ! $post || $post->post_type !== 'inscripcion' ) {
			return new \WP_Error( 'not_found', __( 'Inscripción no encontrada.', 'convoca-enroll' ) );
		}

		$estado_actual = CPT_Inscripcion::get_meta( $inscripcion_id, 'estado' );
		if ( $estado_actual === 'cancelada' ) {
			return true; // Already cancelled.
		}

		$actividad_id = (int) CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' );

		// Use transaction for atomicity.
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// If it was holding a spot (was confirmed), free it up.
			if ( $estado_actual === 'confirmada' ) {
				$promoted = self::promote_waitlist( $actividad_id );

				// If nobody was promoted from waitlist, we officially have +1 capacity.
				if ( ! $promoted ) {
					$affected = $wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->postmeta} 
                         SET meta_value = CAST(meta_value AS SIGNED) + 1 
                         WHERE post_id = %d AND meta_key = '" . CPT_Inscripcion::META_PREFIX . "plazas_disponibles'",
							$actividad_id
						)
					);
				}
			}

			// Update state.
			CPT_Inscripcion::update_meta( $inscripcion_id, 'estado', 'cancelada' );

			// IMPORTANT: Invalidate QR token on cancellation for security.
			CPT_Inscripcion::update_meta( $inscripcion_id, 'checkin_token', '' );

			if ( $nota ) {
				CPT_Inscripcion::update_meta( $inscripcion_id, 'notas', $nota );
			}

			$wpdb->query( 'COMMIT' );

			\Convoca\Core\Utils::do_action( 'convoca_enroll_inscripcion_cancelada', 'convoca_inscripcion_cancelada', $inscripcion_id, $actividad_id );

			return true;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( 'Transaction failed in cancelar: ' . $e->getMessage(), 'Enroll/Motor', $inscripcion_id );
			return new \WP_Error( 'cancel_failed', __( 'Error al cancelar la inscripción.', 'convoca-enroll' ) );
		}
	}

	/**
	 * Manually confirm a pending inscription.
	 */
	public static function confirmar( int $inscripcion_id ): bool|\WP_Error {
		$estado_actual = CPT_Inscripcion::get_meta( $inscripcion_id, 'estado' );
		if ( $estado_actual === 'confirmada' ) {
			return true;
		}

		if ( ! in_array( $estado_actual, array( 'pendiente', 'lista_espera', 'pendiente_pago' ), true ) ) {
			return new \WP_Error( 'invalid_state', __( 'Solo se pueden confirmar inscripciones pendientes o en lista de espera.', 'convoca-enroll' ) );
		}

		$actividad_id = (int) CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' );

		// Use transaction for atomicity.
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// All transitions to 'confirmada' from a non-confirmed state must decrement capacity.
			if ( in_array( $estado_actual, array( 'pendiente', 'lista_espera', 'pendiente_pago' ), true ) ) {
				$affected = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} 
                     SET meta_value = CAST(meta_value AS SIGNED) - 1 
                     WHERE post_id = %d AND meta_key = '" . CPT_Inscripcion::META_PREFIX . "plazas_disponibles' AND CAST(meta_value AS SIGNED) > 0",
						$actividad_id
					)
				);

				if ( ! $affected ) {
					$wpdb->query( 'ROLLBACK' );
					return new \WP_Error( 'no_slots', __( 'No hay plazas disponibles para confirmar esta inscripción.', 'convoca-enroll' ) );
				}
			}

			// Generar o regenerar token único de check-in.
			$token = CPT_Inscripcion::get_meta( $inscripcion_id, 'checkin_token' );
			if ( empty( $token ) || $estado_actual === 'cancelada' ) {
				$token = wp_generate_password( 32, false );
				CPT_Inscripcion::update_meta( $inscripcion_id, 'checkin_token', $token );
			}

			CPT_Inscripcion::update_meta( $inscripcion_id, 'estado', 'confirmada' );

			$wpdb->query( 'COMMIT' );

			\Convoca\Core\Utils::do_action( 'convoca_enroll_inscripcion_confirmada', 'convoca_inscripcion_confirmada', $inscripcion_id, $actividad_id );

			return true;
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( 'Transaction failed in confirmar: ' . $e->getMessage(), 'Enroll/Motor', $inscripcion_id );
			return new \WP_Error( 'confirm_failed', __( 'Error al confirmar la inscripción.', 'convoca-enroll' ) );
		}
	}

	/**
	 * Promote the first waitlisted inscription to 'pendiente '
	 *
	 * @return bool True if someone was promoted.
	 */
	private static function promote_waitlist( int $actividad_id ): bool {
		$waitlist = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'actividad_id',
						'value' => $actividad_id,
					),
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'estado',
						'value' => 'lista_espera',
					),
				),
			)
		);

		if ( ! empty( $waitlist ) ) {
			global $wpdb;
			foreach ( $waitlist as $promoted ) {
				// Atomic update to ensure only one process promotes this specific record.
				$affected = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->postmeta} SET meta_value = 'pendiente' 
                     WHERE post_id = %d AND meta_key = '" . CPT_Inscripcion::META_PREFIX . "estado' AND meta_value = 'lista_espera'",
						$promoted->ID
					)
				);

				if ( $affected > 0 ) {
					// Success! This one is promoted.
					CPT_Inscripcion::update_meta( $promoted->ID, 'pagado', 0 ); // Reset payment just in case.
					\Convoca\Core\Utils::do_action( 'convoca_enroll_inscripcion_promovida', 'convoca_inscripcion_promovida', $promoted->ID, $actividad_id );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if the email already has an active reservation (one-per-person rule).
	 */
	public static function check_limite_reservas( string $email ): true|\WP_Error {
		$existing = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'email',
						'value' => $email,
					),
					array(
						'key'     => CPT_Inscripcion::META_PREFIX . 'estado',
						'value'   => array( 'confirmada', 'pagada', 'pendiente', 'pendiente_pago', 'lista_espera' ),
						'compare' => 'IN',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => CPT_Inscripcion::META_PREFIX . 'es_menor',
							'value'   => '1',
							'compare' => '!=',
						),
						array(
							'key'     => CPT_Inscripcion::META_PREFIX . 'es_menor',
							'compare' => 'NOT EXISTS',
						),
					),
				),
			)
		);

		if ( ! empty( $existing ) ) {
			return new \WP_Error(
				'limite_reservas',
				__( 'Ya tienes una reserva activa. Cada persona solo puede realizar una única reserva de taller. Si quieres inscribir a un menor o persona a tu cargo, marca la opción correspondiente.', 'convoca-enroll' )
			);
		}

		return true;
	}

	/**
	 * Generate unique 8-character alphanumeric reservation code.
	 */
	public static function generar_codigo( int $attempt = 0 ): string {
		// Safety limit to prevent infinite recursion.
		if ( $attempt >= 10 ) {
			// Fallback to timestamp-based unique code.
			return strtoupper( substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 ) );
		}

		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No I,O,0,1 to avoid confusion.
		$code  = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$code .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
		}

		// Ensure uniqueness.
		$exists = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'codigo_reserva',
						'value' => $code,
					),
				),
			)
		);

		return empty( $exists ) ? $code : self::generar_codigo( $attempt + 1 );
	}

	/**
	 * Find an inscription by email + reservation code (panel access).
	 */
	public static function buscar_por_codigo( string $email, string $codigo ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'email',
						'value' => sanitize_email( $email ),
					),
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'codigo_reserva',
						'value' => strtoupper( sanitize_text_field( $codigo ) ),
					),
				),
			)
		);

		return $posts[0] ?? null;
	}

	/**
	 * Get all active reservations for an email address.
	 */
	public static function get_reservas_por_email( string $email ): array {
		return get_posts(
			array(
				'post_type'      => 'inscripcion',
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => CPT_Inscripcion::META_PREFIX . 'email',
						'value' => sanitize_email( $email ),
					),
					array(
						'key'     => CPT_Inscripcion::META_PREFIX . 'estado',
						'value'   => 'cancelada',
						'compare' => '!=',
					),
				),
			)
		);
	}

	/**
	 * Validate Spanish DNI/NIE checksum.
	 * Delegates to Convoca\Core\Utils to avoid duplication.
	 *
	 * @deprecated Use Utils::validar_dni() instead
	 */
	public static function validar_dni( string $dni ): bool {
		return Utils::validar_dni( $dni );
	}

	/**
	 * Set the attendance status for an inscription.
	 *
	 * @param int    $inscripcion_id Inscription ID.
	 * @param string $asistencia     'si', 'no', or 'no_registrada '
	 * @return bool|\WP_Error
	 */
	public static function set_asistencia( int $inscripcion_id, string $asistencia ): bool|\WP_Error {
		$post = get_post( $inscripcion_id );
		if ( ! $post || $post->post_type !== 'inscripcion' ) {
			return new \WP_Error( 'not_found', __( 'Inscripción no encontrada.', 'convoca-enroll' ) );
		}

		$valid_states = array( 'si', 'no', 'no_registrada' );
		if ( ! in_array( $asistencia, $valid_states, true ) ) {
			return new \WP_Error( 'invalid_state', __( 'Estado de asistencia no válido. Valores permitidos: si, no, no_registrada.', 'convoca-enroll' ) );
		}

		$user_id = get_current_user_id();
		CPT_Inscripcion::update_meta( $inscripcion_id, 'asistencia', $asistencia );
		CPT_Inscripcion::update_meta( $inscripcion_id, 'checkin_by', $user_id );

		\Convoca\Core\Logger::info(
			sprintf( 'Asistencia actualizada a "%s" por usuario #%d', $asistencia, $user_id ),
			'Enroll/Motor',
			$inscripcion_id
		);

		\Convoca\Core\Utils::do_action( 'convoca_enroll_asistencia_cambiada', 'convoca_asistencia_cambiada', $inscripcion_id, $asistencia );

		return true;
	}

	/**
	 * Generate a unique check-in token (unpredictable).
	 */
	public static function generar_token_unico(): string {
		return bin2hex( random_bytes( 24 ) ); // 48 chars
	}

	/**
	 * Regenerates the check-in token for an inscription.
	 * Use this if a token is leaked or after moving from 'cancelada' to another state.
	 */
	public static function regenerar_token_checkin( int $inscripcion_id ): string {
		$token = self::generar_token_unico();
		CPT_Inscripcion::update_meta( $inscripcion_id, 'checkin_token', $token );
		return $token;
	}

	/**
	 * Create the convoca_reservation_codes table for atomic unique code storage.
	 * Called during plugin activation and upgrade.
	 */
	public static function create_reservation_codes_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'convoca_reservation_codes';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
            code varchar(12) NOT NULL,
            post_id bigint(20) NOT NULL,
            PRIMARY KEY (code),
            KEY post_id (post_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Migrate existing codes from post_meta.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO $table_name (code, post_id)
             SELECT meta_value, post_id FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value != ''",
				'_conv_codigo_reserva'
			)
		);
	}
}
