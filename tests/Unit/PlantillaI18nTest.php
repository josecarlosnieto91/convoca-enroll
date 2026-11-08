<?php
/**
 * Las plantillas no llevan texto escrito a mano: todo pasa por el catálogo (issue #71).
 *
 * El formulario público es lo que ve todo el mundo y era justo lo que estaba fuera de `__()`: el plugin
 * se distribuye y esos textos no se podían traducir ni adaptar por sitio.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

final class PlantillaI18nTest extends TestCase {

	/**
	 * Texto visible en una plantilla, fuera de PHP y de las etiquetas.
	 *
	 * @param string $fichero Ruta.
	 * @return array<int, string> Líneas con texto sin traducir.
	 */
	private function texto_sin_traducir( string $fichero ): array {
		$codigo = (string) file_get_contents( $fichero );

		// Fuera los bloques PHP: lo que imprimen ya viene del catálogo.
		$html = (string) preg_replace( '/<\?php.*?\?>/s', '', $codigo );
		// Fuera comentarios HTML.
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );
		// Fuera las etiquetas (y sus atributos).
		$html = (string) preg_replace( '/<[^>]*>/s', "\n", $html );

		$fallos = array();
		foreach ( explode( "\n", $html ) as $n => $linea ) {
			$texto = trim( (string) preg_replace( '/\s+/', ' ', $linea ) );
			// Un marcador (`{admin_email}`) no es texto: lo sustituye el sistema.
			if ( preg_match( '/^\{[a-z0-9_]+\}$/i', $texto ) ) {
				continue;
			}

			// Texto con letras: es contenido visible que debería salir de una llamada de traducción.
			if ( preg_match( '/[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]{4,}/u', $texto ) ) {
				$fallos[] = basename( $fichero ) . ':' . ( $n + 1 ) . ' «' . mb_substr( $texto, 0, 70 ) . '»';
			}
		}

		return $fallos;
	}

	/**
	 * Ninguna plantilla lleva texto sin traducir.
	 */
	public function test_las_plantillas_no_llevan_texto_sin_traducir(): void {
		$raiz   = dirname( __DIR__, 2 ) . '/templates';
		$fallos = array();

		foreach ( glob( $raiz . '/*.php' ) ?: array() as $fichero ) {
			$fallos = array_merge( $fallos, $this->texto_sin_traducir( $fichero ) );
		}

		$this->assertSame( array(), $fallos, "Texto fuera del catálogo:\n  " . implode( "\n  ", $fallos ) );
	}
}
