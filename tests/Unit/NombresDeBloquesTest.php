<?php
/**
 * Los bloques que se declaran son los bloques que se registran (enroll#22).
 *
 * MEDIDO (informe del issue, editor de la demo): el bloque «Formulario de Inscripción Convoca» **no se podía
 * insertar**. `wp.blocks.getBlockType('convoca-enroll/formulario-inscripcion')` devolvía «no registrado»,
 * mientras el JS lo registraba como `convoca/formulario-inscripcion` y su `block.json` declaraba
 * `convoca-enroll/formulario-inscripcion`. Dos nombres para el mismo bloque: el insertador no lo encuentra y el
 * formulario solo se podía poner a mano con un shortcode.
 *
 * Y de paso: el editor usa `actividadId`, `mostrarPlazas` y `mostrarPrecios`, que no estaban declarados en
 * `block.json`, así que no se guardaban al reabrir la entrada.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class NombresDeBloquesTest extends TestCase {

	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/** Ni un nombre suelto: cada block.json tiene su registro en el JS, con el mismo nombre. */
	public function test_cada_bloque_declarado_se_registra_con_el_mismo_nombre(): void {
		$js = (string) file_get_contents( $this->raiz() . '/assets/js/convoca-enroll-blocks.js' );
		preg_match_all( "/registerBlockType\\(\\s*'([^']+)'/", $js, $m );
		$registrados = $m[1] ?? array();

		$this->assertNotEmpty( $registrados, 'No se ha encontrado ningún registerBlockType: la prueba no comprobaría nada' );

		$ficheros = glob( $this->raiz() . '/blocks/*/block.json' );
		$this->assertNotEmpty( $ficheros, 'No se han encontrado block.json' );

		foreach ( $ficheros as $bj ) {
			$meta   = json_decode( (string) file_get_contents( $bj ), true );
			$nombre = (string) ( $meta['name'] ?? '' );
			if ( '' === $nombre ) {
				continue;
			}

			$this->assertContains(
				$nombre,
				$registrados,
				"El bloque «{$nombre}» está declarado en su block.json y el JS tiene que registrarlo con ESE nombre: con dos nombres distintos no aparece en el insertador (§enroll#22)"
			);
		}
	}

	/** Los atributos que usa el editor están declarados donde tienen que estar. */
	public function test_los_atributos_del_formulario_estan_declarados(): void {
		$meta = json_decode( (string) file_get_contents( $this->raiz() . '/blocks/inscripcion/block.json' ), true );
		$atributos = (array) ( $meta['attributes'] ?? array() );

		foreach ( array( 'actividadId', 'mostrarPlazas', 'mostrarPrecios' ) as $attr ) {
			$this->assertArrayHasKey( $attr, $atributos, "«{$attr}» lo usa el editor y tiene que estar en block.json: sin declararlo, no se guarda al reabrir la entrada" );
		}

		$js = (string) file_get_contents( $this->raiz() . '/assets/js/convoca-enroll-blocks.js' );
		foreach ( array( 'actividadId', 'mostrarPlazas', 'mostrarPrecios' ) as $attr ) {
			$this->assertStringContainsString( $attr, $js, "El editor usa «{$attr}»" );
		}
	}
}
