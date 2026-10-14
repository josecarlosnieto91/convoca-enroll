<?php
/**
 * La maquetación de un cartel: que no se solape nada, que nada se salga del lienzo y que no se
 * pierda ni un dato.
 *
 * Defecto real (medido el 13/10/2026 sobre los carteles del banco de pruebas): el bloque de datos
 * se quedaba con «lo que sobraba» y el bucle del motor descartaba las filas que no cabían **en
 * silencio** — el cuadrado con siete datos dibujaba cinco y el cartel mentía por omisión.
 *
 * Estas pruebas sustituyen al `Poster_Layout_Validator` borrado en la pasada 9: aquel validaba HTML
 * (que este motor no genera), era código muerto y no lo cargaba nadie. La comprobación se hace
 * ahora sobre las cajas de verdad y sin Imagick, así que corre en cualquier sitio.
 *
 * Prueba en negativo: devolviendo el bloque de datos a «lo que sobra» (sin reservar sitio para las
 * filas), `test_ninguna_fila_de_datos_se_queda_fuera_en_ningun_formato` falla en los cinco formatos
 * que apretaban.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Poster_Engine;
use Convoca\Enroll\Media\Poster_Layout;
use PHPUnit\Framework\TestCase;

class MaquetacionDeCartelesTest extends TestCase {

	/**
	 * Los seis formatos con su lienzo real.
	 *
	 * @return array<string, array{0:int,1:int}>
	 */
	private function formatos(): array {
		$d = Poster_Engine::all_format_dimensions();
		return array(
			'square'   => array( $d['square']['width'], $d['square']['height'] ),
			'portrait' => array( $d['portrait']['width'], $d['portrait']['height'] ),
			'story'    => array( $d['story']['width'], $d['story']['height'] ),
			'facebook' => array( $d['facebook']['width'], $d['facebook']['height'] ),
			'banner'   => array( $d['banner']['width'], $d['banner']['height'] ),
			'a4'       => array( $d['a4']['width'], $d['a4']['height'] ),
		);
	}

	public function test_nada_se_solapa_ni_se_sale_del_lienzo(): void {
		foreach ( $this->formatos() as $formato => $wh ) {
			foreach ( array( 5, 7 ) as $filas ) {
				$l        = Poster_Layout::calcular( $formato, $wh[0], $wh[1], $filas );
				$problemas = Poster_Layout::problemas( $l, $wh[0], $wh[1] );
				$this->assertSame(
					array(),
					$problemas,
					sprintf( 'En %s con %d filas: %s', $formato, $filas, implode( ' | ', $problemas ) )
				);
			}
		}
	}

	public function test_ninguna_fila_de_datos_se_queda_fuera_en_ningun_formato(): void {
		foreach ( $this->formatos() as $formato => $wh ) {
			foreach ( array( 5, 7 ) as $filas ) {
				$l     = Poster_Layout::calcular( $formato, $wh[0], $wh[1], $filas );
				$gap   = Poster_Layout::fila_gap( $l['meta']['h'] );
				$caben = Poster_Layout::filas_que_caben( $l['meta']['h'], $gap );
				$this->assertGreaterThanOrEqual(
					$filas,
					$caben,
					sprintf( 'En %s el bloque de datos deja %d filas fuera de %d (bloque %d px).', $formato, $filas - $caben, $filas, $l['meta']['h'] )
				);
			}
		}
	}

	public function test_el_texto_del_a4_no_baja_del_minimo_de_imprenta(): void {
		// El A4 de imprenta salía con etiquetas de 14 px, que sobre 210 mm son 1,2 mm.
		$d     = Poster_Engine::all_format_dimensions();
		$l     = Poster_Layout::calcular( 'a4', $d['a4']['width'], $d['a4']['height'], 7 );
		$gap   = Poster_Layout::fila_gap( $l['meta']['h'] );
		$m     = Poster_Layout::medidas_de_fila( $l['meta']['h'], $l['meta_size'], 7, $gap );
		$this->assertGreaterThanOrEqual( 19, $m['etiqueta'], 'La etiqueta del A4 vuelve a ser de 1,2 mm.' );
		$this->assertGreaterThanOrEqual( 28, $m['valor'], 'El valor del A4 es demasiado pequeño para imprimir.' );
	}

	public function test_la_foto_no_desaparece_cuando_hay_muchos_datos(): void {
		// Reservar sitio para los datos no puede vaciar el cartel de foto: hay un suelo.
		foreach ( $this->formatos() as $formato => $wh ) {
			foreach ( array( 5, 7, 9 ) as $filas ) {
				$l = Poster_Layout::calcular( $formato, $wh[0], $wh[1], $filas );
				$this->assertGreaterThanOrEqual(
					(int) round( $wh[1] * Poster_Layout::MIN_RATIO_FOTO ) - 2,
					$l['photo']['h'],
					sprintf( 'En %s con %d filas la foto se queda por debajo del suelo.', $formato, $filas )
				);
			}
		}
	}

	public function test_el_validador_de_maquetacion_muerto_ya_no_existe_ni_se_carga(): void {
		$raiz = dirname( __DIR__, 2 );
		$this->assertFileDoesNotExist( $raiz . '/media/class-layout-validator.php', 'El validador muerto sigue en el repo.' );
		$this->assertFalse(
			class_exists( '\Convoca\Enroll\Media\Poster_Layout_Validator' ),
			'Alguien sigue cargando el validador de maquetación borrado.'
		);

		// Y nadie lo invoca por nombre: si volviera a aparecer una llamada, la prueba lo dice.
		$mapa = $raiz . '/vendor/composer/autoload_classmap.php';
		if ( file_exists( $mapa ) ) {
			$this->assertStringNotContainsString(
				'Poster_Layout_Validator',
				file_get_contents( $mapa ),
				'El mapa de clases de Composer todavía apunta al validador borrado: hay que regenerarlo.'
			);
		}
	}

	public function test_la_clave_de_la_cache_depende_de_la_version_del_motor(): void {
		// Defecto medido: un cartel ya publicado no se regeneraba al actualizar el plugin, así que
		// seguía sirviendo el dibujo viejo. La clave de la caché tiene que llevar la versión del
		// motor. Se mira el CÓDIGO (sin comentarios: el comentario que explica el defecto nombra
		// justo `ENGINE_VERSION`, y con el fuente en crudo la prueba pasaría sola).
		$fuente = '';
		foreach ( token_get_all( file_get_contents( dirname( __DIR__, 2 ) . '/media/class-poster-engine.php' ) ) as $t ) {
			if ( is_array( $t ) && in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$fuente .= is_array( $t ) ? $t[1] : $t;
		}
		$this->assertStringContainsString( 'ENGINE_VERSION', $fuente, 'El motor no declara su versión.' );

		$lineas = array_values(
			array_filter(
				explode( "\n", $fuente ),
				static fn( $l ) => false !== strpos( $l, 'self::ENGINE_VERSION' )
			)
		);
		$this->assertNotEmpty( $lineas, 'La versión del motor no entra en la clave de la caché: los carteles viejos se quedan.' );

		// Y va dentro del md5 que forma el nombre del fichero, no en un comentario de al lado.
		$bloque = implode( "\n", array_slice( explode( "\n", $fuente ), 0, 200 ) );
		$this->assertMatchesRegularExpression(
			'/md5\\([^;]*ENGINE_VERSION[^;]*\\)/s',
			$bloque,
			'ENGINE_VERSION se usa, pero no dentro del md5 que nombra el fichero del cartel.'
		);
	}
}
