<?php
/**
 * El validador de plantillas tiene que decir lo mismo que el esquema de las plantillas.
 *
 * Antes exigía width/height en la raíz y las plantillas de fábrica solo los tienen por formato
 * (formats.square, formats.story...), así que la plantilla que trae el plugin no pasaba su propio
 * validador: cualquier pantalla que la validara la marcaba como inválida (issue #11).
 *
 * Estas pruebas NO tocan la base de datos (van en la suite unitaria): leen las plantillas de fábrica
 * de su fuente, Template_Defaults::get_defaults().
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Template_Defaults;
use Convoca\Enroll\Media\Template_Manager;
use PHPUnit\Framework\TestCase;

class ValidacionDePlantillasTest extends TestCase {

	/**
	 * Las ocho plantillas de fábrica pasan el validador. Si una falla, el panel diría que la plantilla
	 * que trae el plugin es inválida.
	 */
	public function test_las_plantillas_de_fabrica_pasan_el_validador(): void {
		$plantillas = Template_Defaults::get_defaults();
		$this->assertGreaterThanOrEqual( 3, count( $plantillas ) );

		foreach ( $plantillas as $plantilla ) {
			$config = is_string( $plantilla['config'] )
				? json_decode( $plantilla['config'], true )
				: $plantilla['config'];

			$this->assertSame(
				array(),
				Template_Manager::validate_config( $config ),
				sprintf( 'La plantilla de fábrica «%s» no pasa su propio validador.', $plantilla['slug'] )
			);
		}
	}

	/**
	 * Dimensiones declaradas por formato (que es como las declaran las de fábrica): válido.
	 */
	public function test_dimensiones_solo_por_formato(): void {
		$config = array(
			'formats' => array( 'square' => array( 'width' => 1080, 'height' => 1080 ) ),
			'layers'  => array( array( 'type' => 'background' ) ),
		);
		$this->assertSame( array(), Template_Manager::validate_config( $config ) );
	}

	/**
	 * Dimensiones en la raíz: válido.
	 */
	public function test_dimensiones_en_la_raiz(): void {
		$config = array(
			'width'  => 1080,
			'height' => 1080,
			'layers' => array( array( 'type' => 'background' ) ),
		);
		$this->assertSame( array(), Template_Manager::validate_config( $config ) );
	}

	/**
	 * Y en NEGATIVO: sin dimensiones por ninguna de las dos vías, sigue siendo error. Esto es lo que
	 * protege una importación de plantilla.
	 */
	public function test_sin_dimensiones_ni_formato_es_error(): void {
		$config = array(
			'formats' => array( 'square' => array( 'width' => 0, 'height' => 0 ) ),
			'layers'  => array( array( 'type' => 'background' ) ),
		);
		$errores = Template_Manager::validate_config( $config );
		$this->assertNotEmpty( $errores );
		$this->assertStringContainsString( 'dimensiones', strtolower( implode( ' ', $errores ) ) );
	}

	/**
	 * En NEGATIVO: sin capas, error.
	 */
	public function test_sin_capas_es_error(): void {
		$config  = array( 'width' => 1080, 'height' => 1080, 'layers' => array() );
		$errores = Template_Manager::validate_config( $config );
		$this->assertNotEmpty( $errores );
	}

	/**
	 * En NEGATIVO: una capa sin tipo, error.
	 */
	public function test_capa_sin_tipo_es_error(): void {
		$config = array(
			'width'  => 1080,
			'height' => 1080,
			'layers' => array( array( 'type' => 'background' ), array() ),
		);
		$this->assertNotEmpty( Template_Manager::validate_config( $config ) );
	}
}
