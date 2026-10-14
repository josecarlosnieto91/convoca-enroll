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
 * Pasada 9: la paleta y las medidas se mudaron a `Poster_Palette` y `Poster_Layout` (cálculo puro);
 * este fichero apunta ahí. El comportamiento que vigila es el mismo.
 *
 * Prueba en negativo: volviendo a poner `$p['accent']` como color de etiqueta, falla el primer caso
 * en las ocho plantillas; quitando la guarda de las 00:00, falla el segundo.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Poster_Engine;
use Convoca\Enroll\Media\Poster_Layout;
use Convoca\Enroll\Media\Poster_Palette;
use PHPUnit\Framework\TestCase;

class LegibilidadDeCartelesTest extends TestCase {

	/** Fondo real de la fila de datos, medido en los carteles generados (#e6e8e8). */
	const FONDO_FILA = Poster_Palette::FONDO_FILA;

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

	public function test_la_etiqueta_de_cada_plantilla_se_lee_sobre_la_fila(): void {
		foreach ( $this->plantillas() as $plantilla ) {
			$paleta = Poster_Palette::para( $plantilla, '' );

			$this->assertArrayHasKey( 'label', $paleta, "La plantilla {$plantilla} no calcula color de etiqueta." );

			$r = Poster_Palette::contraste( $paleta['label'], self::FONDO_FILA );
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
		$lineas = $this->lineas_de_codigo( dirname( __DIR__, 2 ) . '/media/class-poster-engine.php' );

		$pintado = '';
		foreach ( $lineas as $l ) {
			if ( false !== strpos( $l, '$line[0]' ) && false !== strpos( $l, 'text_box' ) ) {
				$pintado = $l;
				break;
			}
		}
		$this->assertNotSame( '', $pintado, 'No encuentro la línea que pinta la etiqueta de cada fila.' );

		$this->assertStringContainsString( "'label'", $pintado, 'La etiqueta no se pinta con el color legible.' );
		$this->assertStringNotContainsString( "'accent'", $pintado, 'La etiqueta se pinta con el acento crudo: vuelve a quedar por debajo del contraste mínimo.' );
	}

	/**
	 * Devuelve solo el CÓDIGO de un fichero: sin comentarios ni cadenas de comentario.
	 *
	 * Un test que lee el código fuente se cae si el comentario que explica el defecto contiene el
	 * patrón buscado; `token_get_all` lo evita (regla anti-trampa 7).
	 *
	 * @param string $ruta Fichero PHP.
	 * @return array<int, string> Líneas de código.
	 */
	private function lineas_de_codigo( string $ruta ): array {
		$this->assertFileExists( $ruta );
		$out = array();
		foreach ( token_get_all( file_get_contents( $ruta ) ) as $t ) {
			if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$out[] = is_array( $t ) ? $t[1] : $t;
		}
		return explode( "\n", implode( '', $out ) );
	}

	public function test_el_color_de_etiqueta_conserva_el_tono_de_la_plantilla(): void {
		// No vale cambiarlo todo por negro: cada plantilla mantiene su acento, solo más oscuro.
		$vistos = array();
		foreach ( $this->plantillas() as $plantilla ) {
			$vistos[ $plantilla ] = Poster_Palette::para( $plantilla, '' )['label'];
		}
		$this->assertSame( count( $this->plantillas() ), count( array_unique( $vistos ) ), 'Hay plantillas que comparten etiqueta: se ha perdido la identidad de cada una.' );
	}

	public function test_el_texto_sobre_el_acento_se_elige_por_contraste(): void {
		// El umbral de luminancia daba texto blanco sobre el naranja de la casa (#ff8700 = 2,37:1)
		// y sobre el ámbar de `kids-family` (#f59e0b = 2,14:1): rótulos y botón por debajo del
		// mínimo. Se elige midiendo los dos contrastes y quedándose con el mayor.
		$this->assertSame( '#000000', Poster_Palette::para( 'modern-ngo', '#ff8700' )['accent_text'] );
		$this->assertSame( '#000000', Poster_Palette::para( 'kids-family', '#f59e0b' )['accent_text'] );
		$this->assertSame( '#ffffff', Poster_Palette::para( 'modern-ngo', '#320028' )['accent_text'] );
	}

	public function test_ningun_acento_de_plantilla_deja_su_texto_por_debajo_del_minimo(): void {
		foreach ( $this->plantillas() as $plantilla ) {
			$paleta = Poster_Palette::para( $plantilla, '' );
			$r      = Poster_Palette::contraste( $paleta['accent_text'], $paleta['accent'] );
			$this->assertGreaterThanOrEqual(
				4.5,
				$r,
				sprintf( 'El texto sobre el acento de %s da %.2f:1.', $plantilla, $r )
			);
		}
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
		$cuadrado = Poster_Layout::medidas_de_fila( 134, 32, 5, 4 );
		$a4       = Poster_Layout::medidas_de_fila( 336, 74, 5, 11 );

		$this->assertGreaterThan( $cuadrado['alto'], $a4['alto'], 'La fila del A4 no es más alta que la del cuadrado.' );
		$this->assertGreaterThan( $cuadrado['etiqueta'], $a4['etiqueta'], 'La etiqueta del A4 no es más grande.' );
		$this->assertGreaterThan( $cuadrado['valor'], $a4['valor'], 'El valor del A4 no es más grande.' );
	}

	public function test_las_filas_caben_en_su_bloque(): void {
		// Casos reales de bloque y de número de filas.
		foreach ( array( array( 134, 32, 5, 4 ), array( 336, 74, 5, 11 ), array( 336, 74, 7, 11 ) ) as $caso ) {
			list( $meta_h, $meta_size, $filas, $gap ) = $caso;
			$m     = Poster_Layout::medidas_de_fila( $meta_h, $meta_size, max( 1, min( $filas, Poster_Layout::filas_que_caben( $meta_h, $gap ) ) ), $gap );
			$usado = $filas * $m['alto'] + $gap * ( $filas - 1 );
			$this->assertLessThanOrEqual(
				$meta_h + 2,
				$usado,
				sprintf( 'Con %d filas de %d px y %d de hueco se usan %d px en un bloque de %d.', $filas, $m['alto'], $gap, $usado, $meta_h )
			);
		}
	}

	public function test_el_texto_no_es_mas_pequeno_que_el_minimo_legible(): void {
		$m = Poster_Layout::medidas_de_fila( 100, 20, 7, 4 );   // el caso más apretado
		$this->assertGreaterThanOrEqual( Poster_Layout::MIN_LETRA_ETIQUETA, $m['etiqueta'] );
		$this->assertGreaterThanOrEqual( Poster_Layout::MIN_LETRA_VALOR, $m['valor'] );
	}

	public function test_un_rango_de_horas_se_mantiene(): void {
		$this->assertSame(
			'10:00 – 13:00',
			$this->llamar( 'time_label', array( '2026-09-10 10:00', '2026-09-10 13:00', '' ) )
		);
	}
}
