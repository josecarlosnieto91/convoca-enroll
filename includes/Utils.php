<?php

/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Utility functions for Convoca Enroll.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Utils {

	/**
	 * Get the label for "Aportación" or "Donación".
	 *
	 * @param string $context Context (e.g., 'singular', 'plural', 'socio', 'trasgu').
	 * @return string
	 */
	public static function get_aportacion_label( string $context = 'singular' ): string {
		$label = __( 'Aportación', 'convoca-enroll' );

		switch ( $context ) {
			case 'plural':
				$label = __( 'Aportaciones', 'convoca-enroll' );
				break;
			case 'socio':
				$label = __( 'Aportación socio', 'convoca-enroll' );
				break;
			case 'trasgu':
				$label = __( 'Aportación Trasgu', 'convoca-enroll' );
				break;
			case 'sugerida_socio':
				$label = __( 'Aportación sugerida para socios', 'convoca-enroll' );
				break;
			case 'sugerida_trasgu':
				$label = __( 'Aportación sugerida para no socios', 'convoca-enroll' );
				break;
		}

		return apply_filters( 'convoca_enroll_aportacion_label', $label, $context );
	}
	/**
	 * Añade el .ics solo si no hay ya uno entre los adjuntos.
	 *
	 * Un correo con dos invitaciones de calendario idénticas es un defecto visible: el mailer puede
	 * haber añadido la suya (o un filtro del sitio) y el socio acaba con `invite.ics` y
	 * `actividad-….ics` del mismo tamaño. Además se puede apagar con el filtro
	 * `convoca_enroll_adjuntar_ics`.
	 *
	 * @param array<int, string> $adjuntos Adjuntos ya reunidos.
	 * @param string             $ruta     Ruta del .ics del plugin.
	 * @return array<int, string>
	 */
	public static function adjuntar_ics( array $adjuntos, string $ruta ): array {
		if ( '' === $ruta || ! apply_filters( 'convoca_enroll_adjuntar_ics', true ) ) {
			return $adjuntos;
		}

		foreach ( $adjuntos as $adjunto ) {
			if ( 'ics' === strtolower( (string) pathinfo( (string) $adjunto, PATHINFO_EXTENSION ) ) ) {
				return $adjuntos;
			}
		}

		$adjuntos[] = $ruta;

		return $adjuntos;
	}
}
