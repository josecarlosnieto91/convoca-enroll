<?php
/**
 * Doble de `Convoca\Core\Hour_Ledger` para los tests unitarios de Enroll.
 *
 * El libro de horas vive en Convoca Core. Aquí se guarda en memoria y se apunta cada movimiento: lo que hay que
 * afirmar es **qué se acredita** (origen, persona, horas) y **qué se revoca**, no cómo lo guarda el núcleo.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Core;

if ( ! class_exists( 'Convoca\Core\Hour_Ledger', false ) ) {

	/**
	 * Libro de horas.
	 */
	class Hour_Ledger {

		/** Origen: una inscripción (asistencia). */
		const ORIGEN_INSCRIPCION = 'inscripcion';

		/** Origen: responsable de la actividad. */
		const ORIGEN_RESPONSABLE = 'responsable';

		/**
		 * ¿Puede acreditar horas esta persona?
		 *
		 * @param int $user_id Usuario.
		 * @return bool
		 */
		public static function puede_acreditar_horas( $user_id ) {
			return (bool) ( $GLOBALS['__ledger']['puede'][ (int) $user_id ] ?? false );
		}

		/**
		 * Acredita horas y devuelve el id del registro.
		 *
		 * @param string $origen    Origen.
		 * @param int    $origen_id Id del origen.
		 * @param int    $user_id   Usuario.
		 * @param float  $horas     Horas.
		 * @param array  $args      Datos.
		 * @return int
		 */
		public static function credit( $origen, $origen_id, $user_id, $horas, $args = array() ) {
			$GLOBALS['__ledger']['llamadas'][] = array( 'credit', $origen, (int) $origen_id, (int) $user_id, (float) $horas, $args );

			$id = (int) ( $GLOBALS['__ledger']['siguiente'] ?? 900 );
			$GLOBALS['__ledger']['siguiente'] = $id + 1;

			$GLOBALS['__ledger']['logs'][ $id ] = array(
				'origen'    => $origen,
				'origen_id' => (int) $origen_id,
				'user_id'   => (int) $user_id,
				'horas'     => (float) $horas,
				'estado'    => 'activo',
			);

			// El producto lee las horas del registro con `get_post_meta()`.
			$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_horas'] = (string) $horas;

			return $id;
		}

		/**
		 * Busca el registro de un origen.
		 *
		 * @param string $origen    Origen.
		 * @param int    $origen_id Id del origen.
		 * @return int 0 si no hay.
		 */
		public static function find( $origen, $origen_id ) {
			foreach ( (array) ( $GLOBALS['__ledger']['logs'] ?? array() ) as $id => $log ) {
				if ( $log['origen'] === $origen && $log['origen_id'] === (int) $origen_id && 'activo' === $log['estado'] ) {
					return (int) $id;
				}
			}

			return 0;
		}

		/**
		 * Invalida el registro de un origen.
		 *
		 * @param string $origen    Origen.
		 * @param int    $origen_id Id del origen.
		 * @param string $motivo    Motivo.
		 * @return void
		 */
		public static function revoke( $origen, $origen_id, $motivo = '' ) {
			$GLOBALS['__ledger']['llamadas'][] = array( 'revoke', $origen, (int) $origen_id, $motivo );

			foreach ( (array) ( $GLOBALS['__ledger']['logs'] ?? array() ) as $id => $log ) {
				if ( $log['origen'] === $origen && $log['origen_id'] === (int) $origen_id && 'activo' === $log['estado'] ) {
					$GLOBALS['__ledger']['logs'][ $id ]['estado'] = 'revocado';
				}
			}
		}
	}
}
