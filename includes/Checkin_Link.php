<?php

/**
 * Convoca Enroll — enlace de check-in.
 *
 * Un solo sitio construye y valida la URL de check-in de una inscripción, para que el
 * correo y el handler no puedan discrepar. Discreparon: el correo escribía
 * `/checkin/?token=…` y el handler esperaba `/checkin/<token>/`, así que el QR del
 * correo no hacía nada.
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
 * Enlace de check-in: construcción y resolución del token.
 */
class Checkin_Link {

	/**
	 * Clave del meta donde vive el token de check-in.
	 */
	const META_TOKEN = '_convoca_checkin_token';

	/**
	 * URL de check-in de una inscripción, tal y como la espera el handler.
	 *
	 * @param int $inscripcion_id Identificador de la inscripción.
	 * @return string URL, o cadena vacía si la inscripción no tiene token.
	 */
	public static function url( int $inscripcion_id ): string {
		$token = (string) get_post_meta( $inscripcion_id, self::META_TOKEN, true );
		if ( '' === $token ) {
			return '';
		}

		return home_url( '/checkin/' . $token . '/' );
	}

	/**
	 * Inscripción a la que pertenece un token.
	 *
	 * @param string $token Token de check-in.
	 * @return int Identificador, o 0 si no hay ninguna.
	 */
	public static function id_for_token( string $token ): int {
		if ( '' === $token ) {
			return 0;
		}

		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = 'inscripcion'
				 AND pm.meta_key = %s
				 AND pm.meta_value = %s
				 LIMIT 1",
				self::META_TOKEN,
				$token
			)
		);

		return (int) $id;
	}
}
