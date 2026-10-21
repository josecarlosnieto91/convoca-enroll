<?php
/**
 * Doble de `Convoca\Core\Member_Identity` para los tests unitarios de Enroll.
 *
 * El contrato vive en Convoca Core, que aquí no está cargado. Los dos valores por defecto son los del
 * núcleo de verdad: **nadie autenticado** y **prioridad concedida** (sin gestión de socios no hay cuota
 * que verificar, y `es_socio = 1` ya daba prioridad).
 *
 * Para que un test diga «hay un socio»:  $GLOBALS['__identidad'] = array( 'autenticado' => true, … );
 * Para quitar la prioridad:              $GLOBALS['__prioridad'] = false;
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\Core\Member_Identity' ) ) {

	/**
	 * Doble mínimo del contrato de identidad.
	 */
	class Member_Identity {

		/**
		 * Respuesta del contrato, como la devolvería el filtro.
		 *
		 * @return array{autenticado: bool, member_id: int, datos: array}
		 */
		private static function datos(): array {
			$crudo = $GLOBALS['__identidad'] ?? array();

			return array(
				'autenticado' => ! empty( $crudo['autenticado'] ),
				'member_id'   => (int) ( $crudo['member_id'] ?? 0 ),
				'datos'       => isset( $crudo['datos'] ) && is_array( $crudo['datos'] ) ? $crudo['datos'] : array(),
			);
		}

		/**
		 * ¿Hay socio con sesión abierta?
		 *
		 * @return bool
		 */
		public static function is_authenticated(): bool {
			return self::datos()['autenticado'];
		}

		/**
		 * Id de la ficha del socio.
		 *
		 * @return int
		 */
		public static function current_member_id(): int {
			return self::datos()['member_id'];
		}

		/**
		 * Datos de la ficha del socio.
		 *
		 * @return array<string, mixed>
		 */
		public static function current_member_data(): array {
			return self::datos()['datos'];
		}

		/**
		 * Prioridad de socio: por defecto sí, como el núcleo.
		 *
		 * @param int $inscripcion_id Inscripción.
		 * @return bool
		 */
		public static function prioridad_de_socio( int $inscripcion_id ): bool {
			if ( isset( $GLOBALS['__prioridad'] ) ) {
				return (bool) $GLOBALS['__prioridad'];
			}

			return $inscripcion_id > 0;
		}

		/**
		 * Olvida la respuesta guardada (el doble no guarda nada, pero la firma es la misma).
		 */
		public static function olvidar(): void {
		}
	}
}
