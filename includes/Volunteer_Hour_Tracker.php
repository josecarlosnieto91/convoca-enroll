<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
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

		// El permiso se exige para ACREDITAR horas, no para retirarlas: retirar una asistencia es
		// una corrección y debe funcionar siempre (si no, revocar el voluntariado dejaría horas
		// atascadas para siempre). Ver el bloque del caso 'si'.
		if ( $asistencia === 'si' ) {
			// Regla única y compartida (convoca-members): el compromiso del alta es una solicitud y
			// las horas exigen aprobación. La fuente de verdad es el usuario (rol o meta), que es
			// donde escribe la aprobación. Antes se aceptaba además la clave
			// `_convoca_es_voluntario` en el usuario — que nadie escribe y se confunde con el meta
			// del mismo nombre en la ficha —, así que el compromiso parecía habilitar las horas.
			// Quién acredita horas lo dice la puerta del libro de Convoca Core, no una clase de otro
			// plugin ni un rol leído aquí: eso era proveer funcionalidad ajena, y además el fallback
			// acreditaba horas en sitios donde no hay gestión de personas. La puerta está CERRADA por
			// defecto: sin gestión de personas, no se acredita nada.
			if ( ! \Convoca\Core\Hour_Ledger::puede_acreditar_horas( (int) $user->ID ) ) {
				return;
			}

			$actividad_id = CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' );

			// Las horas salen de la ACTIVIDAD, no de restar las fechas (issue #19): la resta daba 0 h en
			// una actividad de un día y 48 h en un taller de tres, que son días de calendario y no horas de
			// nadie. Si la actividad no dice cuántas horas son, se estiman por días completos.
			$hours = self::horas_de_actividad( $actividad_id );
			if ( $hours > 0 ) {
				self::add_hours( $inscripcion_id, $user, $hours, $actividad_id );
			}

			// Quien imparte la actividad suma sus horas aunque no esté inscrito (decisión de JC, 15/10):
			// los monitores y responsables no se apuntan a su propio taller. La asistencia marcada es la
			// señal de que la actividad se ha dado.
			self::sincronizar_responsables( $actividad_id, $user );
			return;
		}

		// Case 2: Attendance changed from 'si' to something else - subtract hours.
		$was_counted = get_post_meta( $inscripcion_id, '_convoca_horas_contadas', true );
		if ( $was_counted === '1' ) {
			self::subtract_hours( $inscripcion_id, $user );
		}

		// Y las de los responsables, por el mismo motivo: si ya nadie tiene la asistencia marcada, la
		// actividad no se ha dado (o se deshizo la marca) y sus horas no se sostienen.
		$actividad_id = (int) CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' );
		if ( $actividad_id ) {
			self::sincronizar_responsables( $actividad_id, $user );
		}
	}

	/**
	 * Retira las horas acreditadas cuando la asistencia deja de estar marcada.
	 *
	 * La acreditación se **invalida** (no se borra) a través del libro de horas común, así que
	 * deja de contar en Members, certificados y renovación; y volver a marcar reactiva el MISMO
	 * registro en lugar de crear otro. El agregado del usuario se ajusta con las horas que
	 * realmente se habían acreditado, no con las que la actividad tenga ahora.
	 */
	private static function subtract_hours( int $inscripcion_id, $user ): void {
		$log_id = \Convoca\Core\Hour_Ledger::find( \Convoca\Core\Hour_Ledger::ORIGEN_INSCRIPCION, $inscripcion_id );
		$hours  = $log_id ? (float) get_post_meta( $log_id, '_convoca_horas', true ) : 0.0;

		// 1) Invalida la acreditación de ESTA asistencia (las de otras actividades no se tocan).
		\Convoca\Core\Hour_Ledger::revoke(
			\Convoca\Core\Hour_Ledger::ORIGEN_INSCRIPCION,
			$inscripcion_id,
			__( 'Asistencia retirada', 'convoca-enroll' )
		);

		// 2) Ajusta el agregado del voluntario y libera el marcador de la inscripción.
		global $wpdb;
		$meta_key_total = '_convoca_horas_voluntariado_total';

		$wpdb->query( 'START TRANSACTION' );

		try {
			if ( $hours > 0 ) {
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
			}

			delete_post_meta( $inscripcion_id, '_convoca_horas_contadas' );

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

		global $wpdb;
		$meta_key_total   = '_convoca_horas_voluntariado_total';
		$meta_key_counted = '_convoca_horas_contadas';

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

		// Un único registro por asistencia: si ya existe (marcada antes y retirada), se
		// reactiva en lugar de crear otro, así que marcar/desmarcar no acumula horas.
		\Convoca\Core\Hour_Ledger::credit(
			\Convoca\Core\Hour_Ledger::ORIGEN_INSCRIPCION,
			$inscripcion_id,
			(int) $user->ID,
			$hours,
			array(
				'title'        => sprintf( 'Horas Actividad #%d - %s', $actividad_id, $user->display_name ),
				'tareas'       => 'Asistencia a actividad programada',
				'actividad_id' => $actividad_id,
			)
		);

		\Convoca\Core\Logger::info(
			sprintf( 'Sumadas %.2f horas al voluntario ID %d por actividad %d', $hours, $user->ID, $actividad_id ),
			'Enroll/Volunteer',
			$inscripcion_id
		);

		\Convoca\Core\Utils::do_action( 'convoca_after_horas_voluntario_actualizadas', 'convoca_horas_voluntario_actualizadas', $user->ID, $hours );
	}

	/**
	 * Horas que acredita una actividad (issue #19).
	 *
	 * Decisión de JC (15/10/2026): las horas no salen de restar las fechas —eso daba **0 h** en una actividad
	 * de un día (cuatro de las seis de la demo) y **48 h** en un taller de tres, que son días de calendario—.
	 * El orden es:
	 *
	 *   1. `_convoca_horas_actividad`, el dato explícito y editable en la actividad.
	 *   2. Si no está puesto: días de la actividad × `horas_por_dia` de los ajustes (4 h por defecto). El
	 *      mismo día cuenta como un día entero, que es justo lo que faltaba.
	 *   3. Sin fechas y sin campo → 0 h: no se inventan horas.
	 *
	 * @param int $actividad_id Actividad.
	 * @return float Horas acreditables.
	 */
	private static function horas_de_actividad( int $actividad_id ): float {
		$horas = (float) get_post_meta( $actividad_id, '_convoca_horas_actividad', true );
		if ( $horas > 0 ) {
			return round( $horas, 2 );
		}

		$meta = CPT_Actividad::get_meta( $actividad_id );
		$dias = self::dias_de_actividad( (string) ( $meta['fecha_inicio'] ?? '' ), (string) ( $meta['fecha_fin'] ?? '' ) );
		if ( $dias <= 0 ) {
			return 0.0;
		}

		$settings = get_option( 'convoca_enroll_settings', array() );
		$por_dia  = (float) ( is_array( $settings ) ? ( $settings['horas_por_dia'] ?? 4 ) : 4 );
		if ( $por_dia <= 0 ) {
			$por_dia = 4.0;
		}

		return round( $dias * $por_dia, 2 );
	}

	/**
	 * Días que ocupa una actividad, contando el de inicio (issue #19).
	 *
	 * @param string $inicio Fecha/hora de inicio (Y-m-d H:i).
	 * @param string $fin    Fecha/hora de fin.
	 * @return int Días (0 si no hay inicio).
	 */
	private static function dias_de_actividad( string $inicio, string $fin ): int {
		$ini = strtotime( $inicio );
		if ( ! $ini ) {
			return 0;
		}

		$end = strtotime( $fin );
		if ( ! $end || $end < $ini ) {
			return 1;
		}

		// Se comparan DÍAS, no instantes: un taller de 09:00 a 20:00 del mismo día es un día, no 0,45.
		$segundos_dia = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$dias         = (int) floor( ( strtotime( gmdate( 'Y-m-d', $end ) ) - strtotime( gmdate( 'Y-m-d', $ini ) ) ) / $segundos_dia ) + 1;

		return max( 1, $dias );
	}

	/**
	 * Acredita (o retira) las horas de la actividad a sus responsables (issue #19).
	 *
	 * Los monitores y responsables no se apuntan a su propia actividad, así que no tienen inscripción donde
	 * marcar asistencia. Decisión de JC (15/10/2026): suman las horas de la actividad **sin depender de una
	 * inscripción**, y la señal de que la actividad se ha dado es que haya **alguna** asistencia marcada. Con
	 * ninguna, sus horas se retiran (simétrico).
	 *
	 * @param int         $actividad_id Actividad.
	 * @param object|null $excepto      Usuario ya acreditado por su inscripción, para no contarlo dos veces.
	 */
	private static function sincronizar_responsables( int $actividad_id, $excepto = null ): void {
		$responsables = self::responsables_de( $actividad_id );
		if ( empty( $responsables ) ) {
			return;
		}

		$horas   = self::horas_de_actividad( $actividad_id );
		$hay     = self::asistencias_marcadas( $actividad_id );
		$excepto = ( is_object( $excepto ) && isset( $excepto->ID ) ) ? (int) $excepto->ID : 0;

		foreach ( $responsables as $user_id ) {
			if ( $user_id <= 0 || $user_id === $excepto ) {
				continue;
			}

			$origen = self::origen_responsable( $actividad_id, $user_id );
			$log_id = \Convoca\Core\Hour_Ledger::find( \Convoca\Core\Hour_Ledger::ORIGEN_RESPONSABLE, $origen );

			if ( ! $hay || $horas <= 0 ) {
				if ( $log_id ) {
					$antes = (float) get_post_meta( $log_id, '_convoca_horas', true );
					\Convoca\Core\Hour_Ledger::revoke( \Convoca\Core\Hour_Ledger::ORIGEN_RESPONSABLE, $origen, 'La actividad deja de tener asistencia marcada.' );
					self::ajustar_total( $user_id, -1 * $antes );
				}
				continue;
			}

			// Los responsables también pasan por la puerta del libro: es la única fuente de verdad de quién
			// acredita horas, y por defecto está cerrada (sin gestión de personas, no se acredita).
			if ( ! \Convoca\Core\Hour_Ledger::puede_acreditar_horas( $user_id ) ) {
				continue;
			}

			$antes = $log_id ? (float) get_post_meta( $log_id, '_convoca_horas', true ) : 0.0;
			$nuevo = \Convoca\Core\Hour_Ledger::credit(
				\Convoca\Core\Hour_Ledger::ORIGEN_RESPONSABLE,
				$origen,
				$user_id,
				$horas,
				array(
					'actividad' => $actividad_id,
					'fecha'     => self::fecha_de_actividad( $actividad_id ),
				)
			);

			if ( $nuevo ) {
				self::ajustar_total( $user_id, $horas - $antes );
			}
		}
	}

	/**
	 * Identificador del hecho «esta persona imparte esta actividad» en el libro de horas.
	 *
	 * @param int $actividad_id Actividad.
	 * @param int $user_id      Usuario.
	 * @return int
	 */
	private static function origen_responsable( int $actividad_id, int $user_id ): int {
		return ( $actividad_id * 1000000 ) + $user_id;
	}

	/**
	 * Usuarios responsables de una actividad.
	 *
	 * @param int $actividad_id Actividad.
	 * @return int[]
	 */
	private static function responsables_de( int $actividad_id ): array {
		$raw = get_post_meta( $actividad_id, '_convoca_responsables', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$raw = preg_split( '/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		}

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $raw ) ) ) );
	}

	/**
	 * Inscripciones de la actividad con la asistencia marcada.
	 *
	 * @param int $actividad_id Actividad.
	 * @return int Cuántas.
	 */
	private static function asistencias_marcadas( int $actividad_id ): int {
		$ids = get_posts(
			array(
				'post_type'      => 'inscripcion',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_convoca_actividad_id',
						'value' => (string) $actividad_id,
					),
					array(
						'key'   => '_convoca_asistencia',
						'value' => 'si',
					),
				),
			)
		);

		return count( $ids );
	}

	/**
	 * Fecha con la que se anotan las horas acreditadas por una actividad.
	 *
	 * @param int $actividad_id Actividad.
	 * @return string Y-m-d
	 */
	private static function fecha_de_actividad( int $actividad_id ): string {
		$meta   = CPT_Actividad::get_meta( $actividad_id );
		$inicio = (string) ( $meta['fecha_inicio'] ?? '' );
		$fecha  = $inicio ? substr( $inicio, 0, 10 ) : '';

		return $fecha ? $fecha : wp_date( 'Y-m-d' );
	}

	/**
	 * Ajusta el total acumulado de horas del usuario (agregado heredado que lee Members).
	 *
	 * El libro de horas es la fuente de verdad; esto se ajusta con el mismo delta que el registro acreditado,
	 * no sumando a ciegas ni dejando el agregado por debajo de cero.
	 *
	 * @param int   $user_id Usuario.
	 * @param float $delta   Horas a sumar (negativas para restar).
	 */
	private static function ajustar_total( int $user_id, float $delta ): void {
		if ( $user_id <= 0 || 0.0 === $delta ) {
			return;
		}

		global $wpdb;
		$meta_key = '_convoca_horas_voluntariado_total';
		$existe   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
				$user_id,
				$meta_key
			)
		);

		if ( $existe ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta} SET meta_value = GREATEST(0, CAST(meta_value AS DECIMAL(10,2)) + %f) WHERE user_id = %d AND meta_key = %s",
					$delta,
					$user_id,
					$meta_key
				)
			);
			return;
		}

		if ( $delta > 0 ) {
			$wpdb->insert(
				$wpdb->usermeta,
				array(
					'user_id'    => $user_id,
					'meta_key'   => $meta_key,
					'meta_value' => $delta,
				)
			);
		}
	}
}
