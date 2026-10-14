<?php
/**
 * Convoca Enroll
 *
 * @package    Convoca\Enroll
 * @subpackage Media
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
 * Paleta y contraste de los carteles.
 *
 * Se separó del motor (pasada 9 del gauntlet) porque es **cálculo puro**: no toca WordPress ni
 * Imagick, así que se puede probar sin arrancar nada. Ahí vivían el defecto del contraste de las
 * etiquetas (1,9:1–3,4:1) y el del color del texto sobre el acento.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Paleta de color de los carteles y cálculo de contraste.
 *
 * @package Convoca\Enroll\Media
 */
class Poster_Palette {

	/** Fondo real de la fila de datos en los carteles generados, medido en píxeles (#e6e8e8). */
	const FONDO_FILA = '#e6e8e8';

	/**
	 * Paletas de fábrica: bg, deep, text, muted, accent.
	 *
	 * @return array<string, array{0:string,1:string,2:string,3:string,4:string}>
	 */
	public static function plantillas(): array {
		return array(
			'nature-classic'       => array( '#173b25', '#07150e', '#132018', '#4a5c50', '#5fa65a' ),
			'modern-ngo'           => array( '#10213a', '#07111f', '#111827', '#4b5563', '#ff8700' ),
			'educational-workshop' => array( '#243b73', '#111827', '#111827', '#475569', '#3b82f6' ),
			'volunteer-campaign'   => array( '#4a1d31', '#1a0b12', '#201018', '#614455', '#e84a5f' ),
			'kids-family'          => array( '#0f766e', '#042f2e', '#10231f', '#4b635d', '#f59e0b' ),
			'full-photo-hero'      => array( '#1f2937', '#030712', '#111827', '#4b5563', '#22c55e' ),
			'story-focused'        => array( '#312e81', '#111827', '#111827', '#475569', '#a78bfa' ),
			'minimal-corporate'    => array( '#334155', '#0f172a', '#111827', '#475569', '#0ea5e9' ),
		);
	}

	/**
	 * Paleta resuelta para una plantilla y un color de acento.
	 *
	 * @param string $template Slug de plantilla.
	 * @param string $accent   Color de acento del tipo de actividad (puede venir vacío).
	 * @return array{bg:string,deep:string,text:string,muted:string,accent:string,accent_text:string,label:string,panel:string}
	 */
	public static function para( string $template, string $accent ): array {
		$map = self::plantillas();
		$v   = $map[ sanitize_key( $template ) ] ?? $map['nature-classic'];

		$accent_color = '' !== $accent ? $accent : $v[4];

		return array(
			'bg'          => $v[0],
			'deep'        => $v[1],
			'text'        => $v[2],
			'muted'       => $v[3],
			'accent'      => $accent_color,
			'accent_text' => self::texto_sobre( $accent_color ),
			// El acento crudo como TEXTO sobre la fila clara daba entre 1,9:1 y 3,4:1 según la
			// plantilla (medido el 13/10/2026 sobre los carteles generados): se oscurece hasta
			// que cumpla, conservando el tono de la plantilla. El fondo de referencia es el
			// medido en los carteles (#e6e8e8: la tarjeta blanca al 95% sobre el degradado), no
			// un blanco teórico.
			'label'       => self::texto_legible( $accent_color, self::FONDO_FILA, 4.5 ),
			'panel'       => '#ffffff',
		);
	}

	/**
	 * Devuelve un color del mismo tono que oscurece lo necesario para leerse sobre un fondo.
	 *
	 * @param string $hex   Color de partida (#rrggbb).
	 * @param string $fondo Fondo sobre el que se va a leer (#rrggbb).
	 * @param float  $min   Contraste mínimo exigido (4.5 = AA en texto normal).
	 * @return string Color legible en #rrggbb.
	 */
	public static function texto_legible( string $hex, string $fondo, float $min = 4.5 ): string {
		$hex = ltrim( $hex, '#' );
		if ( 6 !== strlen( $hex ) ) {
			return '#334155';
		}
		$rgb = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
		for ( $k = 1.0; $k >= 0.2; $k -= 0.1 ) {
			$c     = array_map( static fn( $v ) => (int) round( $v * $k ), $rgb );
			$hex_c = sprintf( '#%02x%02x%02x', $c[0], $c[1], $c[2] );
			if ( self::contraste( $hex_c, $fondo ) >= $min ) {
				return $hex_c;
			}
		}
		return '#334155';
	}

	/**
	 * Contraste WCAG entre dos colores.
	 *
	 * @param string $a #rrggbb.
	 * @param string $b #rrggbb.
	 * @return float
	 */
	public static function contraste( string $a, string $b ): float {
		$la = self::luminancia( $a );
		$lb = self::luminancia( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Luminancia relativa WCAG de un color.
	 *
	 * @param string $hex #rrggbb.
	 * @return float
	 */
	public static function luminancia( string $hex ): float {
		$hex   = ltrim( $hex, '#' );
		$canal = static function ( float $v ): float {
			$v /= 255;
			return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * $canal( (float) hexdec( substr( $hex, 0, 2 ) ) )
			+ 0.7152 * $canal( (float) hexdec( substr( $hex, 2, 2 ) ) )
			+ 0.0722 * $canal( (float) hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Color de texto (negro o blanco) que MEJOR se lee sobre un fondo dado.
	 *
	 * Por qué no vale un umbral de luminancia: con el criterio anterior («si el fondo es claro,
	 * negro; si no, blanco») el naranja de la casa (#ff8700) salía con texto blanco a **2,37:1**,
	 * y el ámbar de la plantilla `kids-family` (#f59e0b) a **2,14:1** — los rótulos del tipo de
	 * actividad y el botón «Apúntate» de esos carteles estaban por debajo del mínimo. Midiendo los
	 * dos contrastes y quedándose con el mayor, el peor caso posible es 4,58:1, siempre conforme.
	 *
	 * @param string $fondo Color de fondo (#rgb o #rrggbb).
	 * @return string '#000000' o '#ffffff'.
	 */
	public static function texto_sobre( string $fondo ): string {
		$negro  = self::contraste( '#000000', $fondo );
		$blanco = self::contraste( '#ffffff', $fondo );
		return $negro >= $blanco ? '#000000' : '#ffffff';
	}

	/**
	 * Convierte un color a rgba() con opacidad, para las capas decorativas.
	 *
	 * @param string $hex     #rgb o #rrggbb (o ya un rgb()/rgba()).
	 * @param float  $opacity 0..1.
	 * @return string
	 */
	public static function rgba( string $hex, float $opacity ): string {
		$hex = trim( $hex );
		if ( str_starts_with( $hex, 'rgb' ) ) {
			return $hex;
		}
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$opacity = max( 0.0, min( 1.0, $opacity ) );
		if ( 6 !== strlen( $hex ) ) {
			return 'rgba(0,0,0,' . $opacity . ')';
		}
		return sprintf(
			'rgba(%d,%d,%d,%.3F)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			$opacity
		);
	}
}
