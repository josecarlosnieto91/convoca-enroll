<?php
/**
 * Qué inscripciones cuentan y para qué.
 *
 * Una sola fuente de verdad para dos preguntas que antes respondía cada clase por su cuenta:
 *
 * - `vigentes()`: quién tiene plaza (recordatorios, QR, check-in, álbum y encuesta). Filtraban por el
 *   literal `confirmada`, así que las inscripciones antiguas nacidas `pendiente` se quedaban sin
 *   recordatorios y el escáner las rechazaba.
 * - `ocupan_plaza()`: qué estados consumen cupo. Al cancelar y al recalcular el cupo se usaban listas
 *   distintas, y ampliar plazas devolvía al cupo plazas ya ocupadas.
 *
 * Vive fuera de `Motor_Inscripcion` porque esa clase ya está en el límite de métodos del ratchet: una
 * regla compartida no debe engordar la clase que la aplica.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estados de una inscripción.
 */
final class Estados {

	/**
	 * Estados que dan derecho a plaza: recordatorios, QR, check-in, álbum y encuesta.
	 *
	 * `pendiente` entra a propósito: son las inscripciones creadas con la regla vieja, que tenían plaza
	 * aunque nadie las confirmara. `pendiente_pago` NO entra: sin cobro no hay plaza que acreditar.
	 *
	 * @return string[]
	 */
	public static function vigentes(): array {
		return array( 'confirmada', 'pendiente', 'pagada' );
	}

	/**
	 * Estados que consumen una plaza del cupo.
	 *
	 * `lista_espera` y `cancelada` no consumen.
	 *
	 * @return string[]
	 */
	public static function ocupan_plaza(): array {
		return array( 'pendiente', 'pendiente_pago', 'confirmada', 'pagada' );
	}

	/**
	 * ¿Este estado da derecho a plaza?
	 *
	 * @param string $estado Estado de la inscripción.
	 * @return bool
	 */
	public static function es_vigente( string $estado ): bool {
		return in_array( $estado, self::vigentes(), true );
	}
}
