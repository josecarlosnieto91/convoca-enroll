<?php

/**
 * Convoca Enroll — fechas del sitio.
 *
 * Las fechas de las actividades se guardan en hora LOCAL del sitio (`Y-m-d\TH:i`), pero
 * `strtotime()` las interpreta en la zona por defecto de PHP, que WordPress fija en UTC. Comparar
 * una fecha local con `time()` desplaza todas las decisiones (apertura, recordatorios, ventana de
 * cancelación y check-in) el desfase del sitio: una hora en invierno y dos en verano.
 *
 * Aquí vive la única forma de convertir una fecha guardada a instante. Nadie más llama a
 * `strtotime()` con una fecha de actividad.
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
 * Conversión de fechas locales del sitio a instantes.
 */
final class Fechas {

	/**
	 * Zona horaria del sitio.
	 *
	 * @return \DateTimeZone
	 */
	public static function zona(): \DateTimeZone {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		try {
			return new \DateTimeZone( (string) wp_timezone_string() );
		} catch ( \Throwable $e ) {
			return new \DateTimeZone( 'UTC' );
		}
	}

	/**
	 * Instante de una fecha guardada en hora local del sitio.
	 *
	 * @param string $fecha Fecha `Y-m-d H:i:s` o `Y-m-d\TH:i`.
	 * @return int|null Timestamp, o null si la fecha está vacía o no se puede interpretar.
	 */
	public static function instante( string $fecha ): ?int {
		$fecha = trim( $fecha );
		if ( '' === $fecha ) {
			return null;
		}

		try {
			$dt = new \DateTimeImmutable( $fecha, self::zona() );
		} catch ( \Throwable $e ) {
			return null;
		}

		return $dt->getTimestamp();
	}

	/**
	 * Fecha local del sitio (`Y-m-d`) de un instante. Sin instante, la de ahora.
	 *
	 * @param int|null $instante Timestamp.
	 * @return string
	 */
	public static function dia_local( ?int $instante = null ): string {
		return (string) wp_date( 'Y-m-d', $instante ?? time(), self::zona() );
	}
}
