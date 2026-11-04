<?php

/**
 * Migraciones que corren solas al cambiar de versión.
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

namespace Convoca\Enroll;

defined( 'ABSPATH' ) || exit;

/**
 * Limpia lo que dejan atrás las versiones anteriores.
 *
 * Por qué existe: desplegamos copiando ficheros, así que ni la activación ni la desactivación llegan
 * a ejecutarse nunca. Un gancho de cron de una versión anterior se queda agendado para siempre, porque
 * WordPress reprograma los eventos recurrentes: medido el 16/10/2026, el cron del sitio arrastraba
 * ganchos del renombrado del plugin (los que empiezan por el nombre viejo) sin ningún callback, uno de
 * ellos ejecutándose **cada hora**.
 *
 * La migración va por versión guardada, no por «una vez»: así, el día que renombre otro gancho, basta
 * con añadirlo a la lista y las instalaciones se limpian solas al actualizar.
 */
class Migrator {

	/**
	 * Opción donde se guarda la última versión que migró.
	 */
	public const VERSION_OPTION = 'convoca_enroll_ganchos_migrados';

	/**
	 * Opción con los ganchos heredados de ESTE sitio.
	 *
	 * Nace para que el sitio no necesite un mu-plugin: los nombres de sus ganchos son datos suyos, y se
	 * cargan con `wp bde ganchos add <nombre>` (o se editan en la opción). El filtro se mantiene para no
	 * romper a quien ya lo usaba.
	 */
	public const OPTION_HEREDADOS = 'convoca_enroll_ganchos_heredados';

	/**
	 * Ganchos de cron que dejaron versiones anteriores del plugin, aportados por el sitio.
	 *
	 * No se hardcodean: el nombre anterior del plugin llevaba dentro el de una instalación real, y este
	 * fichero viaja en el paquete y en un repositorio público. El sitio los declara en la opción
	 * `convoca_enroll_ganchos_heredados` (comando `wp bde ganchos`) o, si ya lo tenía, con el filtro:
	 *
	 *     add_filter( 'convoca_enroll_ganchos_heredados', function () {
	 *         return array( 'prefijo_viejo_proceso_cola', … );
	 *     } );
	 *
	 * NO los programa ningún fichero del código actual (hay una prueba que lo vigila).
	 *
	 * @return array<int, string>
	 */
	public static function ganchos_heredados(): array {
		$opcion = get_option( self::OPTION_HEREDADOS, array() );
		$filtro = apply_filters( 'convoca_enroll_ganchos_heredados', array() );
		$mezcla = array_merge( is_array( $opcion ) ? $opcion : array(), is_array( $filtro ) ? $filtro : array() );

		// Solo nombres de gancho válidos: un gancho vacío en `wp_clear_scheduled_hook()` limpia de más,
		// y un nombre con caracteres raros no puede existir en WordPress.
		$limpios = array();
		foreach ( $mezcla as $gancho ) {
			if ( ! is_string( $gancho ) ) {
				continue;
			}

			if ( preg_match( '/^[a-z0-9_]+$/', $gancho ) ) {
				$limpios[] = $gancho;
			}
		}

		return array_values( array_unique( $limpios ) );
	}

	/**
	 * Engancha la migración.
	 *
	 * En `init` a propósito: así corre también en el cron y en el front, sin depender de que alguien
	 * entre al escritorio de WordPress.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'migrar_ganchos_heredados' ), 5 );
	}

	/**
	 * Limpia los ganchos heredados cuando la versión del plugin no es la que migró.
	 */
	public static function migrar_ganchos_heredados(): void {
		$actual   = defined( 'CONVOCA_ENROLL_VERSION' ) ? (string) CONVOCA_ENROLL_VERSION : '';
		$guardada = (string) get_option( self::VERSION_OPTION, '' );

		if ( '' === $actual || $guardada === $actual ) {
			return;
		}

		foreach ( self::ganchos_heredados() as $gancho ) {
			wp_clear_scheduled_hook( $gancho );
		}

		update_option( self::VERSION_OPTION, $actual );
	}
}
