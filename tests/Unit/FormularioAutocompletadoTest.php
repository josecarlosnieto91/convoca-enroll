<?php
/**
 * El formulario de inscripción con la sesión de socio abierta (issue members#30, requisito 3).
 *
 * «Ese rol debe poder entrar al panel e inscribirse a actividades con descuento y sin escribir sus datos si ya
 * tiene la sesión iniciada.» El descuento ya lo aplicaba el motor, pero los campos salían **vacíos** y el
 * socio no veía ni que se le aplicaba.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class FormularioAutocompletadoTest extends TestCase {

	private function plantilla(): string {
		$ruta = dirname( __DIR__, 2 ) . '/templates/form-inscripcion.php';
		$this->assertFileExists( $ruta );

		return file_get_contents( $ruta );
	}

	public function test_el_formulario_recibe_los_datos_del_socio(): void {
		$codigo = file_get_contents( dirname( __DIR__, 2 ) . '/public/class-form-inscripcion.php' );
		$this->assertStringContainsString( 'Member_Identity::is_authenticated()', $codigo, 'El formulario tiene que mirar la sesión' );
		$this->assertStringContainsString( '$miembro = array(', $codigo, 'Y pasar los datos a la plantilla' );
		$this->assertStringContainsString( "'_convoca_email'", $codigo );
		$this->assertStringContainsString( "'_convoca_dni'", $codigo );
		$this->assertStringContainsString( "'_convoca_telefono'", $codigo );
	}

	public function test_los_campos_llevan_el_valor_del_socio(): void {
		$plantilla = $this->plantilla();
		foreach ( array( 'nombre', 'email', 'dni', 'telefono' ) as $campo ) {
			$this->assertStringContainsString(
				sprintf( "\$miembro['%s']", $campo ),
				$plantilla,
				'El campo «' . $campo . '» no se autocompleta' 
			);
		}
	}

	public function test_avisa_de_que_se_le_aplica_el_precio_de_socio(): void {
		$this->assertStringContainsString(
			'precio de socio',
			$this->plantilla(),
			'Sin el aviso, el descuento existe pero no se ve'
		);
	}
}
