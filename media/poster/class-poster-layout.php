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
 * Geometría de los carteles: dónde va cada bloque, en cada formato, según cuántos datos traiga la
 * actividad.
 *
 * Se separó del motor (pasada 9 del gauntlet) porque es **cálculo puro** — no toca WordPress ni
 * Imagick — y porque ahí estaban los dos defectos de maquetación medidos sobre los carteles:
 *
 *  1. El bloque de datos no se dimensionaba por el número de filas: con siete datos (organizador y
 *     edad además de fecha, hora, lugar, plazas y precio) el bucle del motor **descartaba filas en
 *     silencio** (`break`) y el cartel perdía datos sin avisar.
 *  2. El validador de solapes (`Poster_Layout_Validator`) era código muerto que trabajaba sobre
 *     HTML, y el motor no produce HTML: se borra y la comprobación pasa a hacerse aquí, sobre las
 *     cajas de verdad, en las pruebas.
 *
 * @package Convoca\Enroll\Media
 */

namespace Convoca\Enroll\Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geometría de los carteles: cajas, filas de datos y solapes.
 *
 * @package Convoca\Enroll\Media
 */
class Poster_Layout {

	/** Alto mínimo de una fila de datos para que la etiqueta siga siendo legible. */
	const MIN_ALTO_FILA = 20;

	/** Alto mínimo de letra de la etiqueta de una fila. */
	const MIN_LETRA_ETIQUETA = 14;

	/** Alto mínimo de letra del valor de una fila. */
	const MIN_LETRA_VALOR = 18;

	/** Relación mínima foto/altura: por debajo, el cartel deja de tener foto. */
	const MIN_RATIO_FOTO = 0.22;

	/**
	 * Cajas del cartel para un formato, un lienzo y un número de filas de datos.
	 *
	 * @param string $format Formato (square, portrait, story, facebook, banner, a4).
	 * @param int    $w      Ancho del lienzo.
	 * @param int    $h      Alto del lienzo.
	 * @param int    $filas  Cuántas filas de datos va a llevar el cartel.
	 * @return array<string, mixed>
	 */
	public static function calcular( string $format, int $w, int $h, int $filas = 5 ): array {
		$min_dim = min( $w, $h );
		$m       = (int) round( $min_dim * 0.055 );
		$gap     = (int) round( $min_dim * 0.022 );
		$radius  = (int) round( $min_dim * 0.028 );

		return $w > $h
			? self::apaisado( $w, $h, $m, $gap, $radius, $filas )
			: self::vertical( $w, $h, $m, $gap, $radius, $format, $filas );
	}

	/**
	 * Alto de fila que se considera cómodo para un tamaño de bloque dado.
	 *
	 * No vale «cabe al mínimo»: en el A4 el mínimo de etiqueta son 14 px, que sobre 210 mm de papel
	 * son 1,2 mm (el defecto que se arregló en la pasada 8). Se apunta a `meta_size × 0,85`, con el
	 * suelo de legibilidad y el techo de siempre.
	 *
	 * @param int $meta_size Tamaño de letra que el layout calcula para ese formato.
	 * @return int
	 */
	public static function alto_objetivo( int $meta_size ): int {
		$maximo = max( 48, (int) round( $meta_size * 1.60 ) );
		return min( $maximo, max( self::MIN_ALTO_FILA, (int) round( $meta_size * 0.85 ) ) );
	}

	/**
	 * Hueco entre filas de datos. Uno solo para todo el motor: el dibujo y la reserva de sitio
	 * tienen que usar el mismo, o la maquetación reserva de más (o de menos) sin que se note.
	 *
	 * @param int $meta_h Alto del bloque de datos.
	 * @return int
	 */
	public static function fila_gap( int $meta_h ): int {
		return max( 4, (int) round( $meta_h * 0.030 ) );
	}

	/**
	 * Alto de fila y tamaño de letra de las filas de datos, según el bloque disponible.
	 *
	 * Por qué existe: con topes fijos (28-52 px de fila) el A4 de imprenta salía con las etiquetas
	 * a 14 px — 1,2 mm de alto — mientras el propio layout ya calculaba 74 px para esa medida
	 * (medido el 13/10/2026 sobre los carteles generados). El bloque manda: si hay poco sitio se
	 * aprieta, y si hay mucho la letra crece en vez de dejar un hueco blanco.
	 *
	 * @param int $meta_h    Alto del bloque de datos.
	 * @param int $meta_size Tamaño de letra que el layout calcula para ese formato.
	 * @param int $filas     Cuántas filas de datos hay.
	 * @param int $gap       Separación entre filas.
	 * @return array{alto:int,etiqueta:int,valor:int}
	 */
	public static function medidas_de_fila( int $meta_h, int $meta_size, int $filas, int $gap ): array {
		$maximo = max( 48, (int) round( $meta_size * 1.60 ) );
		$caben  = (int) floor( ( $meta_h - $gap * max( 0, $filas - 1 ) ) / max( 1, $filas ) );
		// La fila SIEMPRE tiene que caber en su bloque (si no, las últimas no se dibujan y el
		// cartel pierde datos sin avisar). El mínimo es el suelo para que el texto no desaparezca.
		$alto = max( self::MIN_ALTO_FILA, min( $maximo, $caben ) );
		return array(
			'alto'     => $alto,
			'etiqueta' => max( self::MIN_LETRA_ETIQUETA, (int) round( $alto * 0.34 ) ),
			'valor'    => max( self::MIN_LETRA_VALOR, (int) round( $alto * 0.50 ) ),
		);
	}

	/**
	 * Cuántas filas caben de verdad en un bloque de datos, con el mínimo legible.
	 *
	 * @param int $meta_h Alto del bloque.
	 * @param int $gap    Separación entre filas.
	 * @return int Filas que caben (0 si no cabe ninguna).
	 */
	public static function filas_que_caben( int $meta_h, int $gap ): int {
		$gap = max( 0, $gap );
		if ( 0 === $gap ) {
			return (int) floor( $meta_h / self::MIN_ALTO_FILA );
		}
		$n = 0;
		while ( ( $n + 1 ) * self::MIN_ALTO_FILA + $n * $gap <= $meta_h ) {
			++$n;
		}
		return $n;
	}

	/**
	 * Cajas que se solapan entre sí (o se salen del lienzo).
	 *
	 * Recupera —sobre las cajas reales— la comprobación que hacía el validador de maquetación
	 * borrado, que trabajaba sobre HTML que este motor nunca genera.
	 *
	 * @param array<string, mixed> $l Cajas calculadas.
	 * @param int                  $w Ancho del lienzo.
	 * @param int                  $h Alto del lienzo.
	 * @return array<int, string> Lista de problemas (vacía = correcto).
	 */
	public static function problemas( array $l, int $w, int $h ): array {
		$cajas = array();
		foreach ( array( 'panel', 'badge', 'title', 'subtitle', 'meta', 'cta' ) as $nombre ) {
			if ( ! isset( $l[ $nombre ]['x'] ) ) {
				continue;
			}
			$cajas[ $nombre ] = $l[ $nombre ];
		}

		$problemas = array();
		foreach ( $cajas as $nombre => $c ) {
			if ( $c['x'] < 0 || $c['y'] < 0 || $c['x'] + $c['w'] > $w || $c['y'] + $c['h'] > $h ) {
				$problemas[] = sprintf( 'fuera del lienzo: %s', $nombre );
			}
		}

		// El panel contiene al resto a propósito: no cuenta como solape.
		/**
		 * Cajas con sus medidas.
		 *
		 * @var array<string, array{x: int, y: int, w: int, h: int}> $cajas
		 */
		$nombres = array_values( array_diff( array_keys( $cajas ), array( 'panel' ) ) );
		$total   = count( $nombres );
		for ( $i = 0; $i < $total; $i++ ) {
			for ( $j = $i + 1; $j < $total; $j++ ) {
				$a = $cajas[ $nombres[ $i ] ];
				$b = $cajas[ $nombres[ $j ] ];
				if ( $a['x'] < $b['x'] + $b['w'] && $b['x'] < $a['x'] + $a['w']
					&& $a['y'] < $b['y'] + $b['h'] && $b['y'] < $a['y'] + $a['h'] ) {
					$problemas[] = sprintf( 'solape: %s × %s', $nombres[ $i ], $nombres[ $j ] );
				}
			}
		}
		return $problemas;
	}

	/**
	 * Maquetación vertical (retrato, cuadrado, story, A4).
	 *
	 * `$filas` entra aquí para que el bloque de datos se dimensione por lo que va a llevar: si no
	 * cabe a tamaño legible, se le quita sitio a la foto (hasta el mínimo) en vez de descartar
	 * filas en silencio.
	 *
	 * @param int    $w      Ancho.
	 * @param int    $h      Alto.
	 * @param int    $m      Margen.
	 * @param int    $gap    Separación.
	 * @param int    $radius Radio de esquina.
	 * @param string $format Formato.
	 * @param int    $filas  Filas de datos.
	 * @return array<string, mixed>
	 */
	private static function vertical( int $w, int $h, int $m, int $gap, int $radius, string $format, int $filas ): array {
		$min_dim = min( $w, $h );

		$photo_ratio = match ( $format ) {
			'story'   => 0.48,
			'a4'      => 0.45,
			'portrait' => 0.44,
			'square'  => 0.38,
			default   => 0.45,
		};

		$qr_size   = (int) round( $min_dim * ( 'story' === $format ? 0.16 : ( 'square' === $format ? 0.09 : 0.12 ) ) );
		$logo_size = (int) round( $min_dim * ( 'story' === $format ? 0.075 : 0.065 ) );

		$font_base = max( 42, (int) round( $min_dim * ( 'story' === $format ? 0.065 : 0.060 ) ) );

		$badge_h = (int) round( $min_dim * ( 'square' === $format ? 0.040 : 0.058 ) );
		$cta_h   = (int) round( $min_dim * ( 'square' === $format ? 0.050 : 0.062 ) );

		$overlap       = (int) round( $min_dim * 0.055 );
		$bottom_zone_h = max( $qr_size, $logo_size ) + $gap;

		$photo_h = (int) round( $h * $photo_ratio );
		$panel_y = $photo_h - $overlap;

		// Punto fijo: el panel crece hacia arriba (comiéndose foto) hasta que las filas de datos
		// caben a tamaño legible. Se recalcula el reparto porque el título y la entradilla se
		// dimensionan a partir del sitio que queda: hacerlo en un solo paso dejaba el cuadrado con
		// siete datos **cinco dibujados y dos descartados en silencio** (medido el 13/10/2026).
		// El panel sube hasta donde la foto llega a su mínimo: más arriba, el cartel se queda sin foto.
		$tope       = max( 0, (int) round( $h * self::MIN_RATIO_FOTO ) - $overlap );
		$title_h    = 0;
		$subtitle_h = 0;

		for ( $i = 0; $i < 8; $i++ ) {
			$panel            = array(
				'x' => $m,
				'y' => $panel_y,
				'w' => $w - 2 * $m,
				'h' => $h - $panel_y - $m,
			);
			$available_h      = $panel['h'] - 2 * $gap;
			$text_available_h = $available_h - $bottom_zone_h;

			$title_h    = (int) round( $text_available_h * ( 'square' === $format ? 0.20 : 0.28 ) );
			$subtitle_h = (int) round( $text_available_h * 0.10 );

			$badge_y    = $panel['y'] + $gap;
			$title_y    = $badge_y + $badge_h + $gap;
			$subtitle_y = $title_y + $title_h + $gap;
			$meta_y     = $subtitle_y + $subtitle_h + $gap;
			$cta_y      = $panel['y'] + $panel['h'] - $gap - $bottom_zone_h - $gap - $cta_h;
			$meta_h     = max( 40, $cta_y - $meta_y - $gap );
			$fila_gap   = self::fila_gap( $meta_h );
			$objetivo   = self::alto_objetivo( max( 22, (int) round( $min_dim * 0.030 ) ) );
			$alto_filas = $filas * $objetivo + ( $filas - 1 ) * $fila_gap;

			if ( $meta_h >= $alto_filas ) {
				break;
			}
			$nuevo = max( $tope, $panel_y - ( $alto_filas - $meta_h ) );
			if ( $nuevo === $panel_y ) {
				break;
			}
			$panel_y = $nuevo;
		}

		$photo_h = max( (int) round( $h * self::MIN_RATIO_FOTO ), $panel_y + $overlap );
		$photo   = array(
			'x' => 0,
			'y' => 0,
			'w' => $w,
			'h' => $photo_h,
		);
		$panel   = array(
			'x' => $m,
			'y' => $panel_y,
			'w' => $w - 2 * $m,
			'h' => $h - $panel_y - $m,
		);

		$available_h      = $panel['h'] - 2 * $gap;
		$text_available_h = $available_h - $bottom_zone_h;
		$title_h          = (int) round( $text_available_h * ( 'square' === $format ? 0.20 : 0.28 ) );
		$subtitle_h       = (int) round( $text_available_h * 0.10 );

		$badge_y    = $panel['y'] + $gap;
		$title_y    = $badge_y + $badge_h + $gap;
		$subtitle_y = $title_y + $title_h + $gap;
		$meta_y     = $subtitle_y + $subtitle_h + $gap;
		$cta_y      = $panel['y'] + $panel['h'] - $gap - $bottom_zone_h - $gap - $cta_h;
		$meta_h     = max( 40, $cta_y - $meta_y - $gap );

		$qr_logo_y = $panel['y'] + $panel['h'] - $gap - max( $qr_size, $logo_size );

		return array(
			'photo'         => $photo,
			'panel'         => $panel,
			'radius'        => $radius,
			'badge'         => array(
				'x' => $panel['x'] + $gap,
				'y' => $badge_y,
				'w' => min( $panel['w'] - 2 * $gap, 500 ),
				'h' => $badge_h,
			),
			'title'         => array(
				'x' => $panel['x'] + $gap,
				'y' => $title_y,
				'w' => $panel['w'] - 2 * $gap,
				'h' => max( 0, $title_h ),
			),
			'subtitle'      => array(
				'x' => $panel['x'] + $gap,
				'y' => $subtitle_y,
				'w' => $panel['w'] - 2 * $gap,
				'h' => max( 0, $subtitle_h ),
			),
			'meta'          => array(
				'x' => $panel['x'] + $gap,
				'y' => $meta_y,
				'w' => $panel['w'] - $qr_size - 3 * $gap,
				'h' => $meta_h,
			),
			'cta'           => array(
				'x' => $panel['x'] + $gap,
				'y' => $cta_y,
				'w' => min( 480, (int) round( $panel['w'] * 0.48 ) ),
				'h' => $cta_h,
			),
			'qr'            => array(
				'x'    => $panel['x'] + $panel['w'] - $gap - $qr_size,
				'y'    => $qr_logo_y,
				'size' => $qr_size,
			),
			'logo'          => array(
				'x'    => $panel['x'] + $panel['w'] - $gap - $logo_size,
				'y'    => $panel['y'] + $gap,
				'size' => $logo_size,
			),
			'title_size'    => $font_base,
			'subtitle_size' => max( 20, (int) round( $min_dim * 0.028 ) ),
			'meta_size'     => max( 22, (int) round( $min_dim * 0.030 ) ),
		);
	}

	/**
	 * Maquetación apaisada (Facebook/link y banner).
	 *
	 * @param int $w      Ancho.
	 * @param int $h      Alto.
	 * @param int $m      Margen.
	 * @param int $gap    Separación.
	 * @param int $radius Radio.
	 * @param int $filas  Filas de datos.
	 * @return array<string, mixed>
	 */
	private static function apaisado( int $w, int $h, int $m, int $gap, int $radius, int $filas ): array {
		$photo_w = (int) round( $w * 0.46 );
		$photo   = array(
			'x' => $w - $m - $photo_w,
			'y' => $m,
			'w' => $photo_w,
			'h' => $h - 2 * $m,
		);
		$panel_w = $w - $photo_w - 3 * $m;
		$panel   = array(
			'x' => $m,
			'y' => $m,
			'w' => $panel_w,
			'h' => $h - 2 * $m,
		);

		$badge_h = 56;
		$cta_h   = 48;

		// Mismo criterio que en vertical: las filas de datos mandan. Si no caben a tamaño legible,
		// el sitio se le quita al subtítulo y al título (nunca a los datos: un cartel sin fecha no
		// sirve, uno sin entradilla sí).
		$title_ratio    = 0.28;
		$subtitle_ratio = 0.15;
		$title_h        = 0;
		$subtitle_h     = 0;

		for ( $i = 0; $i < 12; $i++ ) {
			$available_h = $panel['h'] - 2 * $gap;
			$title_h     = (int) round( $available_h * $title_ratio );
			$subtitle_h  = (int) round( $available_h * $subtitle_ratio );

			$badge_y    = $panel['y'] + $gap;
			$title_y    = $badge_y + $badge_h + $gap;
			$subtitle_y = $title_y + $title_h + $gap;
			$meta_y     = $subtitle_y + $subtitle_h + $gap;
			$cta_y      = $panel['y'] + $panel['h'] - $gap - $cta_h;
			$meta_h     = max( 60, $cta_y - $meta_y - $gap );
			$fila_gap   = self::fila_gap( $meta_h );
			$objetivo   = self::alto_objetivo( max( 20, (int) round( min( $w, $h ) * 0.030 ) ) );
			$alto_filas = $filas * $objetivo + ( $filas - 1 ) * $fila_gap;

			if ( $meta_h >= $alto_filas ) {
				break;
			}
			if ( $subtitle_ratio > 0 ) {
				$subtitle_ratio = round( max( 0, $subtitle_ratio - 0.05 ), 2 );
				continue;
			}
			if ( $title_ratio > 0.12 ) {
				$title_ratio = round( $title_ratio - 0.03, 2 );
				continue;
			}
			break;
		}

		$meta_h    = max( 60, $cta_y - $meta_y - $gap );
		$qr_size   = (int) round( min( $w, $h ) * 0.12 );
		$logo_size = (int) round( min( $w, $h ) * 0.06 );

		return array(
			'photo'         => $photo,
			'panel'         => $panel,
			'radius'        => $radius,
			'badge'         => array(
				'x' => $panel['x'] + $gap,
				'y' => $badge_y,
				'w' => min( 460, $panel['w'] - 2 * $gap ),
				'h' => $badge_h,
			),
			'title'         => array(
				'x' => $panel['x'] + $gap,
				'y' => $title_y,
				'w' => $panel['w'] - 2 * $gap,
				'h' => max( 0, $title_h ),
			),
			'subtitle'      => array(
				'x' => $panel['x'] + $gap,
				'y' => $subtitle_y,
				'w' => $panel['w'] - 2 * $gap,
				'h' => max( 0, $subtitle_h ),
			),
			'meta'          => array(
				'x' => $panel['x'] + $gap,
				'y' => $meta_y,
				'w' => $panel['w'] - 2 * $gap,
				'h' => $meta_h,
			),
			'cta'           => array(
				'x' => $panel['x'] + $gap,
				'y' => $cta_y,
				'w' => min( 390, (int) ( $panel['w'] * 0.55 ) ),
				'h' => $cta_h,
			),
			'qr'            => array(
				'x'    => $photo['x'] + $photo['w'] - $m - $qr_size,
				'y'    => $photo['y'] + $photo['h'] - $m - $qr_size,
				'size' => $qr_size,
			),
			'logo'          => array(
				'x'    => $panel['x'] + $panel['w'] - $gap - $logo_size,
				'y'    => $panel['y'] + $gap,
				'size' => $logo_size,
			),
			'title_size'    => (int) round( min( $w, $h ) * 0.078 ),
			'subtitle_size' => max( 18, (int) round( min( $w, $h ) * 0.028 ) ),
			'meta_size'     => max( 20, (int) round( min( $w, $h ) * 0.030 ) ),
		);
	}
}
