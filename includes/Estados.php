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
		// Una `pendiente` no reserva nada: si nadie la confirma, la plaza queda libre y la caducidad la
		// cierra del todo (#74). `pendiente_pago` sí mientras el cobro está en curso.
		return array( 'pendiente_pago', 'confirmada' );
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

	/**
	 * Cambia el estado ajustando el aforo con una sola regla para todos los estados.
	 *
	 * @param int    $inscripcion_id Inscripción.
	 * @param string $nuevo          Estado destino.
	 * @param string $motivo         Motivo para el registro.
	 * @return true|\WP_Error
	 */
	public static function cambiar( int $inscripcion_id, string $nuevo, string $motivo = '' ): true|\WP_Error {
		if ( ! in_array( $nuevo, CPT_Inscripcion::STATES, true ) ) {
			return new \WP_Error( 'invalid_state', __( 'Estado no válido.', 'convoca-enroll' ) );
		}

		$actual = (string) CPT_Inscripcion::get_meta( $inscripcion_id, 'estado' );
		if ( $actual === $nuevo ) {
			return true;
		}

		$actividad_id = (int) CPT_Inscripcion::get_meta( $inscripcion_id, 'actividad_id' );
		$ocupaba      = in_array( $actual, self::ocupan_plaza(), true );
		$ocupara      = in_array( $nuevo, self::ocupan_plaza(), true );

		if ( $ocupaba && ! $ocupara ) {
			self::liberar_plaza( $actividad_id );
		} elseif ( ! $ocupaba && $ocupara ) {
			if ( ! self::descontar_plaza( $actividad_id ) ) {
				return new \WP_Error( 'no_slots', __( 'No hay plazas disponibles para confirmar esta inscripción.', 'convoca-enroll' ) );
			}
		}

		CPT_Inscripcion::update_meta( $inscripcion_id, 'estado', $nuevo );
		if ( 'cancelada' === $nuevo ) {
			CPT_Inscripcion::update_meta( $inscripcion_id, 'asistencia', 'no_registrada' );
			CPT_Inscripcion::update_meta( $inscripcion_id, 'checkin_token', '' );
		}

		\Convoca\Core\Logger::info(
			sprintf( 'Estado #%d: %s → %s%s', $inscripcion_id, $actual, $nuevo, '' !== $motivo ? ' (' . $motivo . ')' : '' ),
			'Enroll/Motor',
			$inscripcion_id
		);

		return true;
	}

	/**
	 * Libera una plaza: la ocupa el primero de la lista o sube el cupo.
	 *
	 * @param int $actividad_id Actividad.
	 * @return void
	 */
	private static function liberar_plaza( int $actividad_id ): void {
		if ( Motor_Inscripcion::promover_hasta_cubrir( $actividad_id, 1 ) > 0 ) {
			return;
		}
		$disponibles = (int) CPT_Actividad::get_meta_value( $actividad_id, 'plazas_disponibles' );
		CPT_Actividad::update_meta( $actividad_id, 'plazas_disponibles', $disponibles + 1 );
	}

	/**
	 * Descuenta una plaza si queda alguna.
	 *
	 * @param int $actividad_id Actividad.
	 * @return bool
	 */
	private static function descontar_plaza( int $actividad_id ): bool {
		$disponibles = (int) CPT_Actividad::get_meta_value( $actividad_id, 'plazas_disponibles' );
		if ( $disponibles <= 0 ) {
			return false;
		}

		CPT_Actividad::update_meta( $actividad_id, 'plazas_disponibles', $disponibles - 1 );

		return true;
	}
}
