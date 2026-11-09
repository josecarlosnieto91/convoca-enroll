<?php

/**
 * Convoca Enroll — quién manda los recordatorios y qué se envió ya.
 *
 * Había dos sistemas que no se conocían: los tres crons fijos (flags por actividad) y el
 * recordatorio configurable por horas. Los dos podían disparar sobre la misma actividad y cada uno
 * guardaba su marca, así que el socio recibía dos «es mañana» con media hora de diferencia
 * (enroll#87).
 *
 * Regla única: una actividad con `_convoca_reminder_hours` propio usa SOLO el configurable; sin él,
 * usan los tres flags de siempre. Además, un marcador común por inscripción y plantilla impide que
 * dos caminos envíen la misma plantilla.
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reparto de recordatorios y marcador común.
 */
final class Recordatorios {

	/**
	 * Meta de la actividad con las horas del recordatorio configurable.
	 */
	const META_MODO = '_convoca_reminder_hours';

	/**
	 * Marcador común: array slug => fecha del último envío.
	 */
	const META_MARCA = '_convoca_ultimo_recordatorio_enviado';

	/**
	 * Qué sistema gobierna los recordatorios de una actividad.
	 *
	 * @param int $actividad_id Actividad.
	 * @return string 'configurable' si tiene horas propias; 'flags' en caso contrario.
	 */
	public static function modo( int $actividad_id ): string {
		return absint( get_post_meta( $actividad_id, self::META_MODO, true ) ) > 0 ? 'configurable' : 'flags';
	}

	/**
	 * ¿Ya se envió esta plantilla a esta inscripción?
	 *
	 * @param int    $inscripcion_id Inscripción.
	 * @param string $slug           Plantilla.
	 * @return bool
	 */
	public static function ya_enviado( int $inscripcion_id, string $slug ): bool {
		$marcas = get_post_meta( $inscripcion_id, self::META_MARCA, true );

		return is_array( $marcas ) && ! empty( $marcas[ $slug ] );
	}

	/**
	 * Deja la marca común del envío.
	 *
	 * @param int    $inscripcion_id Inscripción.
	 * @param string $slug           Plantilla.
	 * @return void
	 */
	public static function marcar( int $inscripcion_id, string $slug ): void {
		$marcas = get_post_meta( $inscripcion_id, self::META_MARCA, true );
		$marcas = is_array( $marcas ) ? $marcas : array();

		$marcas[ $slug ] = current_time( 'mysql' );

		update_post_meta( $inscripcion_id, self::META_MARCA, $marcas );
	}
}
