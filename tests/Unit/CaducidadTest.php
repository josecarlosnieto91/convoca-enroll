<?php
/**
 * Las inscripciones pendientes caducan y liberan la plaza (#74).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Caducidad;
use Convoca\Enroll\Estados;
use PHPUnit\Framework\TestCase;

class CaducidadTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
		);
		$GLOBALS['convoca_test_posts'] = array();
	}

	/**
	 * Una pendiente ya no ocupa plaza; una pendiente de pago sí.
	 */
	public function test_lo_que_ocupa_plaza(): void {
		$ocupan = Estados::ocupan_plaza();

		$this->assertNotContains( 'pendiente', $ocupan, 'una pendiente sin confirmar no reserva' );
		$this->assertContains( 'confirmada', $ocupan );
		$this->assertContains( 'pendiente_pago', $ocupan, 'mientras el cobro está en curso sí' );
	}

	/**
	 * El plazo por defecto son 48 h, se puede cambiar por actividad y 0 lo desactiva.
	 */
	public function test_el_plazo(): void {
		$this->assertSame( 48, Caducidad::horas( 10 ) );

		$GLOBALS['_wp_stores']['post_meta'][10] = array( '_convoca_caducidad_horas' => '12' );
		$this->assertSame( 12, Caducidad::horas( 10 ) );

		$GLOBALS['_wp_stores']['post_meta'][10]['_convoca_caducidad_horas'] = '0';
		$this->assertSame( 0, Caducidad::horas( 10 ), '0 = no caduca' );

		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'caducidad_pendiente_horas' => 72 );
		unset( $GLOBALS['_wp_stores']['post_meta'][10] );
		$this->assertSame( 72, Caducidad::horas( 10 ) );
	}

	/**
	 * Una pendiente vieja pasa a expirada; una recién creada se queda.
	 */
	public function test_expirar_solo_las_viejas(): void {
		$ahora = 1700000000;

		$GLOBALS['_wp_stores']['posts'][1] = (object) array(
			'ID'        => 1,
			'post_type' => 'inscripcion',
			'post_date' => gmdate( 'Y-m-d H:i:s', $ahora - ( 72 * HOUR_IN_SECONDS ) ),
		);
		$GLOBALS['_wp_stores']['posts'][2] = (object) array(
			'ID'        => 2,
			'post_type' => 'inscripcion',
			'post_date' => gmdate( 'Y-m-d H:i:s', $ahora - ( 2 * HOUR_IN_SECONDS ) ),
		);
		$GLOBALS['_wp_stores']['post_meta'][1] = array( '_convoca_estado' => 'pendiente', '_convoca_actividad_id' => 50, '_convoca_email' => 'a@example.test' );
		$GLOBALS['_wp_stores']['post_meta'][2] = array( '_convoca_estado' => 'pendiente', '_convoca_actividad_id' => 50, '_convoca_email' => 'b@example.test' );
		$GLOBALS['convoca_test_posts']         = array( 1, 2 );
		$GLOBALS['_wp_stores']['post_meta'][50] = array( '_convoca_plazas_ocupadas' => '3' );

		$cerradas = Caducidad::expirar( $ahora );

		$this->assertSame( 1, $cerradas );
		$this->assertSame( 'expirada', (string) get_post_meta( 1, '_convoca_estado', true ) );
		$this->assertSame( 'pendiente', (string) get_post_meta( 2, '_convoca_estado', true ), 'la reciente sigue viva' );
		$this->assertSame( '2', (string) get_post_meta( 50, '_convoca_plazas_ocupadas', true ), 'la plaza vuelve' );
	}

	/**
	 * Con el plazo a 0 no se cierra nada.
	 */
	public function test_con_plazo_cero_no_caduca(): void {
		$ahora = 1700000000;

		$GLOBALS['_wp_stores']['posts'][3] = (object) array(
			'ID'        => 3,
			'post_type' => 'inscripcion',
			'post_date' => gmdate( 'Y-m-d H:i:s', $ahora - ( 500 * HOUR_IN_SECONDS ) ),
		);
		$GLOBALS['_wp_stores']['post_meta'][3] = array( '_convoca_estado' => 'pendiente', '_convoca_actividad_id' => 50 );
		$GLOBALS['_wp_stores']['post_meta'][50] = array( '_convoca_caducidad_horas' => '0' );
		$GLOBALS['convoca_test_posts']          = array( 3 );

		$this->assertSame( 0, Caducidad::expirar( $ahora ) );
		$this->assertSame( 'pendiente', (string) get_post_meta( 3, '_convoca_estado', true ) );
	}
}
