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
	 * Solo `confirmada`. Decisión de producto: una `pendiente` no acredita nada —ni recordatorios ni
	 * check-in—; quien la gestiona la confirma a mano si debe asistir. `pendiente_pago` tampoco: sin
	 * cobro no hay plaza.
	 *
	 * @return string[]
	 */
	public static function vigentes(): array {
		return array( 'confirmada' );
	}

	/**
	 * Estados que consumen una plaza del cupo.
	 *
	 * `lista_espera` y `cancelada` no consumen. `pagada` se retiró: nada lo escribía y ahora mismo
	 * cualquier inscripción histórica con ese valor se migra a `confirmada`.
	 *
	 * @return string[]
	 */
	public static function ocupan_plaza(): array {
		return array( 'pendiente', 'pendiente_pago', 'confirmada' );
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
