<?php
/**
 * Caducidad de las inscripciones pendientes.
 *
 * Una inscripción `pendiente` que nadie confirma no puede ocupar una plaza para siempre: antes se quedaba
 * ahí y la actividad aparecía llena sin nadie apuntado (#74). Aquí se cierra y se libera.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cierra las inscripciones pendientes que han superado su plazo.
 */
final class Caducidad {

	/**
	 * Horas por defecto antes de caducar.
	 */
	public const HORAS_POR_DEFECTO = 48;

	/**
	 * Gancho del cron.
	 */
	public const GANCHO = 'convoca_enroll_expirar_pendientes';

	/**
	 * Engancha el cron. Idempotente: un sitio ya activo no vuelve a pasar por la activación.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::GANCHO, array( __CLASS__, 'expirar' ) );

		if ( ! wp_next_scheduled( self::GANCHO ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::GANCHO );
		}
	}

	/**
	 * Horas que aguanta una pendiente en una actividad. 0 = no caduca.
	 *
	 * @param int $actividad_id Actividad.
	 * @return int
	 */
	public static function horas( int $actividad_id ): int {
		$propias = get_post_meta( $actividad_id, '_convoca_caducidad_horas', true );
		if ( '' !== $propias && null !== $propias ) {
			return max( 0, (int) $propias );
		}

		$ajustes = (array) get_option( 'convoca_enroll_settings', array() );
		$horas   = isset( $ajustes['caducidad_pendiente_horas'] ) ? (int) $ajustes['caducidad_pendiente_horas'] : self::HORAS_POR_DEFECTO;

		return max( 0, (int) apply_filters( 'convoca_enroll_caducidad_horas', $horas, $actividad_id ) );
	}

	/**
	 * Cierra las pendientes que han pasado su plazo.
	 *
	 * @param int|null $ahora Momento actual (para las pruebas).
	 * @return int Cuántas se han cerrado.
	 */
	public static function expirar( ?int $ahora = null ): int {
		$ahora = $ahora ?? time();

		$pendientes = get_posts(
			array(
				'post_type'     => 'inscripcion',
				'post_status'   => 'any',
				// El cron corre cada hora: 100 por pasada es de sobra y no dispara el aviso de paginación.
				'numberposts'   => 100,
				'fields'        => 'ids',
				'no_found_rows' => true,
				'meta_key'      => '_convoca_estado',
				'meta_value'    => 'pendiente',
				'date_query'    => array(
					array(
						'column'    => 'post_date',
						'before'    => gmdate( 'Y-m-d H:i:s', $ahora - HOUR_IN_SECONDS ),
						'inclusive' => true,
					),
				),
			)
		);

		$cerradas = 0;
		foreach ( $pendientes as $id ) {
			$id = (int) $id;

			// Se comprueba el estado leído, no solo el de la consulta: la plaza se libera por el valor
			// real de la ficha.
			if ( 'pendiente' !== (string) get_post_meta( $id, '_convoca_estado', true ) ) {
				continue;
			}

			$actividad_id = (int) get_post_meta( $id, '_convoca_actividad_id', true );
			$horas        = self::horas( $actividad_id );

			if ( $horas <= 0 ) {
				continue;
			}

			$inscripcion = get_post( $id );
			$creada      = $inscripcion ? (int) strtotime( (string) $inscripcion->post_date ) : 0;
			if ( 0 === $creada || ( $ahora - $creada ) < ( $horas * HOUR_IN_SECONDS ) ) {
				continue;
			}

			update_post_meta( $id, '_convoca_estado', 'expirada' );
			update_post_meta( $id, '_convoca_expirada_en', current_time( 'mysql' ) );

			// La plaza se devuelve en el acto: el contador de la actividad es lo que lee el formulario.
			$ocupadas = (int) get_post_meta( $actividad_id, '_convoca_plazas_ocupadas', true );
			if ( $ocupadas > 0 ) {
				update_post_meta( $actividad_id, '_convoca_plazas_ocupadas', max( 0, $ocupadas - 1 ) );
			}

			\Convoca\Core\Logger::info(
				sprintf( 'Inscripción #%d caducada sin confirmar tras %d h; la plaza queda libre.', $id, $horas ),
				'Enroll/Caducidad',
				$id
			);
			if ( class_exists( '\Convoca\Core\Intentos' ) ) {
				\Convoca\Core\Intentos::registrar(
					'inscripcion',
					'expirada',
					array(
						'email'      => (string) get_post_meta( $id, '_convoca_email', true ),
						'referencia' => $id,
						'motivo'     => 'pendiente ' . $horas . ' h',
					)
				);
			}

			++$cerradas;
		}

		return $cerradas;
	}
}
