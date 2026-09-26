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
 * Enlace de check-in: construcción y validación de la firma.
 */
class Checkin_Link {

	/**
	 * Clave del meta donde vive el token de check-in.
	 */
	const META_TOKEN = '_convoca_checkin_token';

	/**
	 * Firma de una inscripción.
	 *
	 * El `h` autoriza el check-in de ESA inscripción y de ninguna otra: es lo que permite
	 * que el enlace funcione para quien recibe el QR sin darle permisos de personal.
	 * Se firma el identificador con la sal persistente del sitio (estable, no rota al
	 * cambiar las claves de WordPress), para que un QR enviado ayer siga valiendo.
	 *
	 * @param int $inscripcion_id Identificador de la inscripción.
	 * @return string Firma hexadecimal.
	 */
	public static function hmac( int $inscripcion_id ): string {
		return hash_hmac( 'sha256', (string) $inscripcion_id, \Convoca\Core\Utils::get_persistent_salt() );
	}

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

		return home_url( '/checkin/' . $token . '/?h=' . self::hmac( $inscripcion_id ) );
	}

	/**
	 * ¿La firma corresponde a esa inscripción?
	 *
	 * @param int    $inscripcion_id Identificador de la inscripción.
	 * @param string $h              Firma recibida.
	 * @return bool
	 */
	public static function verify( int $inscripcion_id, string $h ): bool {
		if ( $inscripcion_id <= 0 || '' === $h ) {
			return false;
		}

		return hash_equals( self::hmac( $inscripcion_id ), $h );
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
