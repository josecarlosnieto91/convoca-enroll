<?php
/**
 * Plantillas de correo: lo guardado manda, pero no puede quedarse sin lo imprescindible.
 *
 * El problema que resuelve: en un sitio había 11 plantillas guardadas desde septiembre y ninguna
 * tenía `{qr_code}`. El cuerpo decía «Adjuntamos tu código QR» —texto del usuario, que manda— pero el
 * marcador que pinta el QR no existía en ninguna parte, y `maybe_migrate()` solo traducía marcadores
 * viejos escritos como URL: no podía inventar uno que nunca estuvo.
 *
 * Regla: al cargar, si la plantilla de serie trae un marcador crítico que la guardada no tiene, se
 * añade el párrafo de serie que lo contiene. Nunca al revés: no se quita ni se reescribe nada del
 * usuario.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fusión de marcadores entre la plantilla de serie y la guardada.
 */
final class Plantillas {

	/**
	 * Marcadores sin los cuales el correo promete algo que no envía.
	 *
	 * Solo estos: son los que el texto de serie da por hechos. Añadir aquí `{album_url}` o
	 * `{importe}` volvería a meter secciones que el usuario pudo quitar a propósito.
	 *
	 * @var string[]
	 */
	public const CRITICOS = array( '{qr_code}', '{url_checkin}' );

	/**
	 * Aplica la fusión a todas las plantillas guardadas.
	 *
	 * @param array<string, mixed> $defectos  Plantillas de serie.
	 * @param array<string, mixed> $guardadas Plantillas guardadas.
	 * @return array<string, mixed>
	 */
	public static function fusionar_todas( array $defectos, array $guardadas ): array {
		foreach ( $guardadas as $slug => $tpl ) {
			if ( ! isset( $defectos[ $slug ] ) || ! is_array( $tpl ) ) {
				continue;
			}

			$guardadas[ $slug ]['body'] = self::fusionar(
				(string) ( $tpl['body'] ?? '' ),
				(string) ( $defectos[ $slug ]['body'] ?? '' )
			);
		}

		return array_merge( $defectos, $guardadas );
	}

	/**
	 * Devuelve el cuerpo guardado con los párrafos de serie que faltan.
	 *
	 * @param string $guardado Cuerpo de la plantilla guardada.
	 * @param string $defecto  Cuerpo de la plantilla de serie.
	 * @return string
	 */
	public static function fusionar( string $guardado, string $defecto ): string {
		foreach ( self::CRITICOS as $marcador ) {
			if ( false !== strpos( $guardado, $marcador ) ) {
				continue;
			}

			$parrafo = self::parrafo_con( $defecto, $marcador );
			if ( '' !== $parrafo ) {
				$guardado = rtrim( $guardado ) . "\n" . $parrafo;
			}
		}

		return $guardado;
	}

	/**
	 * Migra un mapa de plantillas guardadas: normaliza textos y completa marcadores.
	 *
	 * @param array<string, mixed> $templates Plantillas guardadas.
	 * @param string               $dominios  Dominios de correo del sitio.
	 * @return array<string, mixed>
	 */
	public static function migrar( array $templates, string $dominios ): array {
		foreach ( $templates as $slug => $tpl ) {
			if ( ! is_array( $tpl ) ) {
				continue;
			}

			foreach ( array( 'subject', 'body' ) as $field ) {
				if ( isset( $tpl[ $field ] ) ) {
					$templates[ $slug ][ $field ] = self::normalizar( (string) $tpl[ $field ], $dominios );
				}
			}
		}

		return $templates;
	}

	/**
	 * Normaliza un texto guardado: marcadores escritos como URL y correos del sitio.
	 *
	 * Es la migración de siempre, sacada de `maybe_migrate()` para que aquel método no engorde.
	 *
	 * @param string $texto    Texto guardado (asunto o cuerpo).
	 * @param string $dominios Dominios de correo del sitio, para el patrón.
	 * @return string
	 */
	public static function normalizar( string $texto, string $dominios ): string {
		$map = array(
			'http://panel_reservas'  => '{panel_reservas}',
			'http://album_url'       => '{album_url}',
			'http://qr_code'         => '{qr_code}',
			'http://url_checkin'     => '{url_checkin}',
			'http://calendario_link' => '{calendario_link}',
			'http://codigo_reserva'  => '{codigo_reserva}',
		);

		$texto = str_replace( array_keys( $map ), array_values( $map ), $texto );

		return (string) preg_replace( '/[a-z0-9._%+\-]+@(?:' . $dominios . ')/i', '{admin_email}', $texto );
	}

	/**
	 * El bloque de la plantilla de serie que contiene un marcador.
	 *
	 * Se corta por párrafos (`</p>`) y, si no hay, por líneas: así se añade la unidad mínima que tiene
	 * sentido, no un fragmento suelto.
	 *
	 * @param string $cuerpo   Cuerpo de serie.
	 * @param string $marcador Marcador buscado.
	 * @return string
	 */
	private static function parrafo_con( string $cuerpo, string $marcador ): string {
		$bloques = preg_split( '/(?<=<\/p>)\s*/i', $cuerpo );

		foreach ( (array) $bloques as $bloque ) {
			if ( false !== strpos( (string) $bloque, $marcador ) ) {
				return trim( (string) $bloque );
			}
		}

		foreach ( (array) preg_split( '/\R/', $cuerpo ) as $linea ) {
			if ( false !== strpos( (string) $linea, $marcador ) ) {
				return trim( (string) $linea );
			}
		}

		return '';
	}
}
