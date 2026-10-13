<?php
/**
 * Los datos de un cartel tienen que poder leerse, y el cartel no puede inventarse datos.
 *
 * Dos defectos reales, vistos mirando los carteles generados en el banco de pruebas el 13/10/2026:
 *
 *  1. Las etiquetas de cada fila (FECHA, HORA, LUGAR…) se pintaban con el color de acento de la
 *     plantilla tal cual, sobre una fila casi blanca. Contraste medido: entre **1,91:1 y 3,36:1**
 *     en las ocho plantillas, por debajo del mínimo (4,5:1). El valor del dato sí se leía (15,9:1),
 *     pero sin su etiqueta no se sabe qué es cada cosa. Ahora el color se oscurece hasta cumplir.
 *  2. Cuando la actividad no trae hora, la fecha es de solo día y el cartel anunciaba el evento a
 *     las **00:00** en vez de decir «Por confirmar».
 *
 * Prueba en negativo: volviendo a poner `$p['accent']` como color de etiqueta, falla el primer caso
 * en las ocho plantillas; quitando la guarda de las 00:00, falla el segundo.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Poster_Engine;
use PHPUnit\Framework\TestCase;

class LegibilidadDeCartelesTest extends TestCase {

	/** Fondo real de la fila de datos, medido en los carteles generados (#e6e8e8). */
	const FONDO_FILA = '#e6e8e8';

	/** Las ocho plantillas de fábrica. */
	private function plantillas(): array {
		return array(
			'nature-classic',
			'modern-ngo',
			'educational-workshop',
			'volunteer-campaign',
			'kids-family',
			'full-photo-hero',
			'story-focused',
			'minimal-corporate',
		);
	}

	private function llamar( string $metodo, array $args ) {
		$m = new \ReflectionMethod( Poster_Engine::class, $metodo );
		$m->setAccessible( true );
		return $m->invokeArgs( null, $args );
	}

	private function contraste( string $a, string $b ): float {
		$lum = static function ( string $hex ): float {
			$hex   = ltrim( $hex, '#' );
			$canal = static function ( float $v ): float {
				$v /= 255;
				return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
			};
			return 0.2126 * $canal( (float) hexdec( substr( $hex, 0, 2 ) ) )
				+ 0.7152 * $canal( (float) hexdec( substr( $hex, 2, 2 ) ) )
				+ 0.0722 * $canal( (float) hexdec( substr( $hex, 4, 2 ) ) );
		};
		$la = $lum( $a );
		$lb = $lum( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	public function test_la_etiqueta_de_cada_plantilla_se_lee_sobre_la_fila(): void {
		foreach ( $this->plantillas() as $plantilla ) {
			$paleta = $this->llamar( 'palette', array( $plantilla, '' ) );

			$this->assertArrayHasKey( 'label', $paleta, "La plantilla {$plantilla} no calcula color de etiqueta." );

			$r = $this->contraste( $paleta['label'], self::FONDO_FILA );
			$this->assertGreaterThanOrEqual(
				4.5,
				$r,
				sprintf( 'La etiqueta de %s da %.2f:1 sobre la fila; el mínimo es 4,5:1.', $plantilla, $r )
			);
		}
	}

	public function test_lo_que_se_pinta_de_verdad_es_el_color_legible(): void {
		// Esta es la que caza el defecto: la paleta puede calcular un color legible y luego
		// pintarse otro (era el caso: se calculaba `label` y se dibujaba `accent`). Se mira la
		// línea que pinta la etiqueta de cada fila, no el cálculo.
		$fuente = file_get_contents( dirname( __DIR__, 2 ) . '/media/class-poster-engine.php' );
		$this->assertIsString( $fuente );

		$lineas = array_values(
			array_filter(
				explode( "\n", $fuente ),
				static fn( $l ) => false !== strpos( $l, '$line[0]' )
			)
		);
		$this->assertNotEmpty( $lineas, 'No encuentro la línea que pinta la etiqueta de cada fila.' );

		$pintado = $lineas[0];
		$this->assertStringContainsString( "'label'", $pintado, 'La etiqueta no se pinta con el color legible.' );
		$this->assertStringNotContainsString( "'accent'", $pintado, 'La etiqueta se pinta con el acento crudo: vuelve a quedar por debajo del contraste mínimo.' );
	}

	public function test_el_color_de_etiqueta_conserva_el_tono_de_la_plantilla(): void {
		// No vale cambiarlo todo por negro: cada plantilla mantiene su acento, solo más oscuro.
		$vistos = array();
		foreach ( $this->plantillas() as $plantilla ) {
			$paleta = $this->llamar( 'palette', array( $plantilla, '' ) );
			$vistos[ $plantilla ] = $paleta['label'];
		}
		$this->assertSame( count( $this->plantillas() ), count( array_unique( $vistos ) ), 'Hay plantillas que comparten etiqueta: se ha perdido la identidad de cada una.' );
	}

	public function test_una_actividad_sin_hora_no_anuncia_medianoche(): void {
		$this->assertSame( '', $this->llamar( 'time_label', array( '2026-09-10', '', '' ) ) );
	}

	public function test_una_hora_de_verdad_se_respeta(): void {
		$this->assertSame( '18:30', $this->llamar( 'time_label', array( '2026-09-10 18:30', '', '' ) ) );
	}

	public function test_una_medianoche_explicita_se_respeta(): void {
		// Si la actividad dice explícitamente que empieza a las 00:00, eso es un dato, no un hueco.
		$this->assertSame( '00:00', $this->llamar( 'time_label', array( '2026-09-10', '', '00:00' ) ) );
	}

	public function test_la_letra_de_las_filas_crece_con_el_formato(): void {
		// El A4 de imprenta salía con etiquetas de 14 px (1,2 mm) porque el alto de fila estaba
		// topado a 28-52 px, igual para todos los formatos. Tiene que escalar con el formato.
		$cuadrado = $this->llamar( 'medidas_de_fila', array( 134, 32, 5, 4 ) );
		$a4       = $this->llamar( 'medidas_de_fila', array( 336, 74, 5, 11 ) );

		$this->assertGreaterThan( $cuadrado['alto'], $a4['alto'], 'La fila del A4 no es más alta que la del cuadrado.' );
		$this->assertGreaterThan( $cuadrado['etiqueta'], $a4['etiqueta'], 'La etiqueta del A4 no es más grande.' );
		$this->assertGreaterThan( $cuadrado['valor'], $a4['valor'], 'El valor del A4 no es más grande.' );
	}

	public function test_las_filas_caben_en_su_bloque(): void {
		// Casos reales: cuadrado con 5 y con 7 filas, y A4 con 5 y con 7.
		// OJO: el cuadrado con 7 filas NO cabe (134 px de bloque para 7 filas); está anotado en el
		// issue correspondiente, no escondido aquí.
		foreach ( array( array( 134, 32, 5, 4 ), array( 336, 74, 5, 11 ), array( 336, 74, 7, 11 ) ) as $caso ) {
			list( $meta_h, $meta_size, $filas, $gap ) = $caso;
			$m = $this->llamar( 'medidas_de_fila', array( $meta_h, $meta_size, $filas, $gap ) );
			$usado = $filas * $m['alto'] + $gap * ( $filas - 1 );
			$this->assertLessThanOrEqual(
				$meta_h + 2,
				$usado,
				sprintf( 'Con %d filas de %d px y %d de hueco se usan %d px en un bloque de %d.', $filas, $m['alto'], $gap, $usado, $meta_h )
			);
		}
	}

	public function test_el_texto_no_es_mas_pequeno_que_el_minimo_legible(): void {
		$m = $this->llamar( 'medidas_de_fila', array( 100, 20, 7, 4 ) );   // el caso más apretado
		$this->assertGreaterThanOrEqual( 14, $m['etiqueta'] );
		$this->assertGreaterThanOrEqual( 18, $m['valor'] );
	}

	public function test_un_rango_de_horas_se_mantiene(): void {
		$this->assertSame(
			'10:00 – 13:00',
			$this->llamar( 'time_label', array( '2026-09-10 10:00', '2026-09-10 13:00', '' ) )
		);
	}
}
