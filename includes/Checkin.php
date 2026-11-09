<?php

/**
 * Convoca Enroll — puerta única del check-in.
 *
 * Las tres rutas (enlace directo, escáner AJAX y botón de la lista) tienen que aplicar las mismas
 * reglas: la inscripción está vigente, se llama dentro de la ventana de la actividad, se sella la
 * hora y quién lo marca, y un escaneo repetido se distingue del primero. Antes cada ruta hacía su
 * parte: el enlace público marcaba sin sesión y sin ventana, el botón de la lista marcaba
 * canceladas y no dejaba hora, y el escáner contestaba «confirmada» siempre.
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace Convoca\Enroll;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reglas y registro del check-in.
 */
final class Checkin {

	/**
	 * Ajustes por defecto de la ventana (horas).
	 */
	const ANTES_POR_DEFECTO   = 2;
	const DESPUES_POR_DEFECTO = 2;

	/**
	 * Ventana configurada.
	 *
	 * @return array{antes: int, despues: int}
	 */
	public static function ajustes(): array {
		$settings = get_option( 'convoca_enroll_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'antes'   => absint( $settings['checkin_window_before_hours'] ?? self::ANTES_POR_DEFECTO ),
			'despues' => absint( $settings['checkin_window_after_hours'] ?? self::DESPUES_POR_DEFECTO ),
		);
	}

	/**
	 * Ventana de check-in de una actividad, en instantes.
	 *
	 * Desde N horas antes del inicio hasta N horas después del fin. Si la actividad no tiene fecha,
	 * no hay ventana que aplicar (`null`). Si no tiene fin, se suponen dos horas de duración.
	 *
	 * @param int $actividad_id Actividad.
	 * @return array{desde: int, hasta: int, inicio: int, fin: int}|null
	 */
	public static function ventana( int $actividad_id ): ?array {
		$inicio = Fechas::instante( (string) get_post_meta( $actividad_id, CPT_Actividad::META_PREFIX . 'fecha_inicio', true ) );
		if ( null === $inicio ) {
			return null;
		}

		$fin = Fechas::instante( (string) get_post_meta( $actividad_id, CPT_Actividad::META_PREFIX . 'fecha_fin', true ) );
		if ( null === $fin || $fin < $inicio ) {
			$fin = $inicio + 2 * HOUR_IN_SECONDS;
		}

		$ajustes = self::ajustes();
		$ventana = array(
			'desde'  => $inicio - $ajustes['antes'] * HOUR_IN_SECONDS,
			'hasta'  => $fin + $ajustes['despues'] * HOUR_IN_SECONDS,
			'inicio' => $inicio,
			'fin'    => $fin,
		);

		/**
		 * Permite ajustar la ventana por actividad o por sitio.
		 *
		 * @param array{desde: int, hasta: int, inicio: int, fin: int} $ventana Ventana calculada.
		 * @param int                                                  $actividad_id Actividad.
		 */
		$filtrada = apply_filters( 'convoca_enroll_ventana_checkin', $ventana, $actividad_id );

		if ( is_array( $filtrada ) && isset( $filtrada['desde'], $filtrada['hasta'], $filtrada['inicio'], $filtrada['fin'] ) ) {
			return array(
				'desde'  => (int) $filtrada['desde'],
				'hasta'  => (int) $filtrada['hasta'],
				'inicio' => (int) $filtrada['inicio'],
				'fin'    => (int) $filtrada['fin'],
			);
		}

		return $ventana;
	}

	/**
	 * ¿Se puede marcar asistencia ahora mismo en esa actividad?
	 *
	 * @param int $actividad_id Actividad.
	 * @param int $ahora        Instante actual.
	 * @return true|WP_Error
	 */
	public static function validar_ventana( int $actividad_id, int $ahora ): true|WP_Error {
		$ventana = self::ventana( $actividad_id );
		if ( null === $ventana ) {
			return true;
		}

		if ( $ahora < (int) $ventana['desde'] ) {
			return new WP_Error(
				'checkin_temprano',
				sprintf(
					/* translators: %s: hora a la que abre el check-in */
					__( 'Todavía no es la hora de esta actividad. El check-in abre a las %s.', 'convoca-enroll' ),
					wp_date( 'H:i', (int) $ventana['desde'] )
				)
			);
		}

		if ( $ahora > (int) $ventana['hasta'] ) {
			return new WP_Error(
				'checkin_cerrado',
				__( 'Esta actividad ya terminó: el plazo de check-in está cerrado.', 'convoca-enroll' )
			);
		}

		return true;
	}

	/**
	 * Marca la asistencia de una inscripción con todas las reglas.
	 *
	 * @param int $inscripcion_id Inscripción.
	 * @param int $user_id        Quién lo registra. Sin usuario, se toma la sesión actual.
	 * @return array{inscripcion_id: int, cambiada: bool, anterior: string, hora: string, ya_marcada: bool}|WP_Error
	 */
	public static function registrar( int $inscripcion_id, int $user_id = 0 ): array|WP_Error {
		$post = get_post( $inscripcion_id );
		if ( ! $post || 'inscripcion' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Inscripción no encontrada.', 'convoca-enroll' ) );
		}

		$estado = (string) get_post_meta( $inscripcion_id, '_convoca_estado', true );
		if ( ! Estados::es_vigente( $estado ) ) {
			return new WP_Error( 'not_confirmed', __( 'La inscripción no está confirmada.', 'convoca-enroll' ) );
		}

		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'no_user', __( 'El check-in lo tiene que registrar una persona del equipo.', 'convoca-enroll' ) );
		}

		$actividad_id = (int) get_post_meta( $inscripcion_id, '_convoca_actividad_id', true );
		$ahora        = time();

		$ventana = self::validar_ventana( $actividad_id, $ahora );
		if ( is_wp_error( $ventana ) ) {
			return $ventana;
		}

		$anterior   = (string) get_post_meta( $inscripcion_id, '_convoca_asistencia', true );
		$ya_marcada = 'si' === $anterior;
		$hora       = (string) get_post_meta( $inscripcion_id, '_convoca_checkin_hora', true );

		if ( $ya_marcada ) {
			if ( '' === $hora ) {
				$hora = (string) current_time( 'mysql' );
				update_post_meta( $inscripcion_id, '_convoca_checkin_hora', $hora );
			}
			update_post_meta( $inscripcion_id, '_convoca_checkin_by', $user_id );

			return array(
				'inscripcion_id' => $inscripcion_id,
				'cambiada'       => false,
				'anterior'       => 'si',
				'hora'           => $hora,
				'ya_marcada'     => true,
			);
		}

		$resultado = Motor_Inscripcion::cambiar_asistencia( $inscripcion_id, 'si' );
		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}

		$hora = (string) current_time( 'mysql' );
		update_post_meta( $inscripcion_id, '_convoca_checkin_hora', $hora );
		update_post_meta( $inscripcion_id, '_convoca_checkin_by', $user_id );

		return array(
			'inscripcion_id' => $inscripcion_id,
			'cambiada'       => true,
			'anterior'       => $anterior,
			'hora'           => $hora,
			'ya_marcada'     => false,
		);
	}

	/**
	 * Hora del primer check-in, formateada para un mensaje.
	 *
	 * @param string $hora Hora guardada (`Y-m-d H:i:s`).
	 * @return string
	 */
	public static function hora_legible( string $hora ): string {
		$instante = Fechas::instante( $hora );

		return null === $instante ? $hora : (string) wp_date( 'H:i', $instante );
	}
}
