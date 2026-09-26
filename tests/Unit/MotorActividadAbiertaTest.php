<?php
/**
 * Una actividad terminada no admite inscripciones, y la ficha no lo disimula.
 *
 * La regla vive en un único sitio (`Motor_Inscripcion::esta_abierta()`) y la usan tanto el
 * motor como la ficha. Antes el motor la aplicaba por su cuenta y la ficha pintaba el
 * formulario igual: en la demo, la actividad había terminado el 10 de septiembre y el
 * visitante rellenaba los cuatro campos para recibir «Esta actividad ya ha finalizado.».
 *
 * @package       Convoca\Enroll\Tests
 *
 * @coversDefaultClass \Convoca\Enroll\Motor_Inscripcion
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Tests de la regla de actividad abierta.
 */
class MotorActividadAbiertaTest extends TestCase
{
	/**
	 * Fija la fecha de inicio de una actividad en el escenario de prueba.
	 *
	 * @param int    $id    ID de la actividad.
	 * @param string $fecha Expresión relativa o fecha.
	 */
	private function conFecha( int $id, string $fecha ): void {
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_fecha_inicio'] =
			( new \DateTimeImmutable( $fecha ) )->format( 'Y-m-d H:i:s' );
	}

	public function test_sin_fecha_no_se_bloquea(): void {
		$this->assertTrue( Motor_Inscripcion::esta_abierta( 7001 ) );
	}

	public function test_una_actividad_futura_esta_abierta(): void {
		$this->conFecha( 7002, '+10 days' );

		$this->assertTrue( Motor_Inscripcion::esta_abierta( 7002 ) );
	}

	public function test_una_actividad_ya_empezada_esta_cerrada(): void {
		$this->conFecha( 7003, '-1 hour' );

		$this->assertFalse( Motor_Inscripcion::esta_abierta( 7003 ) );
	}

	public function test_el_motor_no_deja_inscribirse_en_una_actividad_pasada(): void {
		// La prueba que importa: el motor usa LA MISMA regla, no una copia propia.
		$this->conFecha( 7004, '-3 days' );
		$GLOBALS['convoca_test_post_types'] = array( 7004 => 'actividad' );

		$resultado = Motor_Inscripcion::inscribir(
			7004,
			array(
				'nombre'   => 'QA Hermes',
				'email'    => 'hermes.qa@example.org',
				'dni'      => '00000000T',
				'telefono' => '600000000',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $resultado, 'Debería devolver un error, no crear la inscripción.' );
		$this->assertSame( 'activity_ended', $resultado->get_error_code() );
		$this->assertStringContainsString( 'ya ha finalizado', $resultado->get_error_message() );
	}
}
