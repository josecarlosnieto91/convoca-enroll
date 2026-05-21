<?php
/**
 * Tracker for volunteer hours upon activity check-in.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Volunteer_Hour_Tracker {

	/**
	 * Initializes hooks.
	 */
	public static function init(): void {
		add_action( 'convoca_enroll_asistencia_cambiada', array( self::class, 'handle_asistencia' ), 10, 2 );
	}

	/**
	 * Handles the attendance change hook.
	 */
	public static function handle_asistencia( int $inscripcion_id, string $asistencia ): void {
		$email = CPT_Inscripcion::get_meta( $inscripcion_id, 'email' );
		if ( ! $email ) {
			return;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return;
		}

		// Check if user is a volunteer.
		if ( ! in_array( 'voluntario_aprobado', (array) $user->roles ) && ! $user->has_cap( 'gestionar_mis_turnos' ) && ! get_user_meta( $user->ID, '_conv_es_voluntario', true ) ) {
			return;
		}

		// Case 1: Attendance changed to 'si' - add hours.
		if ( $asistencia === 'si' ) {
			$actividad_id = CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' );
			$meta_act     = CPT_Actividad::get_meta( $actividad_id );
			$fecha_inicio = $meta_act['fecha_inicio'] ?? '';
			$fecha_fin    = $meta_act['fecha_fin'] ?? '';

			if ( empty( $fecha_inicio ) || empty( $fecha_fin ) ) {
				return;
			}

			$hours = self::calculate_hours( $fecha_inicio, $fecha_fin );
			if ( $hours <= 0 ) {
				return;
			}

			self::add_hours( $inscripcion_id, $user, $hours, $actividad_id );
			return;
		}

		// Case 2: Attendance changed from 'si' to something else - subtract hours.
		$was_counted = get_post_meta( $inscripcion_id, '_bde_horas_contadas', true );
		if ( $was_counted === '1' ) {
			self::subtract_hours( $inscripcion_id, $user );
		}
	}

	/**
	 * Subtract hours when attendance is changed from 'si' to 'no '
	 */
	private static function subtract_hours( int $inscripcion_id, $user ): void {
		$actividad_id = CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' );
		$meta_act     = CPT_Actividad::get_meta( $actividad_id );

		$fecha_inicio = $meta_act['fecha_inicio'] ?? '';
		$fecha_fin    = $meta_act['fecha_fin'] ?? '';

		if ( empty( $fecha_inicio ) || empty( $fecha_fin ) ) {
			return;
		}

		$hours = self::calculate_hours( $fecha_inicio, $fecha_fin );
		if ( $hours <= 0 ) {
			return;
		}

		global $wpdb;
		$meta_key_total = '_conv_horas_voluntariado_total';

		$wpdb->query( 'START TRANSACTION' );

		try {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta} 
                 SET meta_value = GREATEST(0, CAST(meta_value AS DECIMAL(10,2)) - %f) 
                 WHERE user_id = %d AND meta_key = %s",
					$hours,
					$user->ID,
					$meta_key_total
				)
			);

			delete_post_meta( $inscripcion_id, '_bde_horas_contadas' );

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( 'Error restando horas en Enroll: ' . $e->getMessage(), 'Enroll/Volunteer', $user->ID );
		}
	}

	/**
	 * Add hours to a volunteer.
	 */
	private static function add_hours( int $inscripcion_id, $user, float $hours, int $actividad_id ): void {
		if ( $hours <= 0 ) {
			return;
		}

		$email = $user->user_email;

		global $wpdb;
		$meta_key_total   = '_conv_horas_voluntariado_total';
		$meta_key_counted = '_bde_horas_contadas';

		$wpdb->query( 'START TRANSACTION' );

		try {
			$is_counted = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s FOR UPDATE",
					$inscripcion_id,
					$meta_key_counted
				)
			);

			if ( $is_counted === '1' ) {
				$wpdb->query( 'ROLLBACK' );
				return;
			}

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s FOR UPDATE",
					$user->ID,
					$meta_key_total
				)
			);

			if ( $exists ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->usermeta} 
                     SET meta_value = CAST(meta_value AS DECIMAL(10,2)) + %f 
                     WHERE user_id = %d AND meta_key = %s",
						$hours,
						$user->ID,
						$meta_key_total
					)
				);
			} else {
				$wpdb->insert(
					$wpdb->usermeta,
					array(
						'user_id'    => $user->ID,
						'meta_key'   => $meta_key_total,
						'meta_value' => $hours,
					)
				);
			}

			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) 
                 VALUES (%d, %s, %s) 
                 ON DUPLICATE KEY UPDATE meta_value = %s",
					$inscripcion_id,
					$meta_key_counted,
					'1',
					'1'
				)
			);

			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			\Convoca\Core\Logger::error( 'Error actualizando horas totales en Enroll: ' . $e->getMessage(), 'Enroll/Volunteer', $user->ID );
			return;
		}

		if ( post_type_exists( 'registro_hora' ) ) {
			$log_id = wp_insert_post(
				array(
					'post_type'   => 'registro_hora',
					'post_title'  => sprintf( 'Horas Actividad #%d - %s', $actividad_id, $user->display_name ),
					'post_status' => 'publish',
					'post_author' => $user->ID,
				)
			);

			if ( ! is_wp_error( $log_id ) ) {
				$members = get_posts(
					array(
						'post_type'      => 'miembro',
						'meta_key'       => '_conv_email',
						'meta_value'     => $email,
						'posts_per_page' => 1,
						'fields'         => 'ids',
					)
				);

				if ( ! empty( $members ) ) {
					update_post_meta( $log_id, ' _conv_miembro_id', $members[0] );
				}

				update_post_meta( $log_id, '_conv_usuario_id', $user->ID );
				update_post_meta( $log_id, '_conv_fecha', wp_date( 'Y-m-d' ) );
				update_post_meta( $log_id, '_conv_horas', $hours );
				update_post_meta( $log_id, '_conv_actividad_id', $actividad_id );
				update_post_meta( $log_id, '_conv_estado', 'aprobada' );
				update_post_meta( $log_id, '_conv_tareas', 'Asistencia a actividad programada' );
			}
		} else {
			\Convoca\Core\Logger::warning(
				"Horas de voluntariado no registradas: CPT 'registro_hora' no disponible. Activa convoca-members.",
				'Enroll/Volunteer',
				$actividad_id
			);
		}

		\Convoca\Core\Logger::info(
			sprintf( 'Sumadas %.2f horas al voluntario ID %d por actividad %d', $hours, $user->ID, $actividad_id ),
			'Enroll/Volunteer',
			$inscripcion_id
		);

		\Convoca\Core\Utils::do_action( 'conv_after_horas_voluntario_actualizadas', 'conv_horas_voluntario_actualizadas', $user->ID, $hours );
	}

	/**
	 * Calculate hours between two dates.
	 */
	private static function calculate_hours( string $fecha_inicio, string $fecha_fin ): float {
		$start = strtotime( $fecha_inicio );
		$end   = strtotime( $fecha_fin );

		if ( ! $start || ! $end || $end <= $start ) {
			return 0.0;
		}

		return round( ( $end - $start ) / 3600, 2 );
	}
}
