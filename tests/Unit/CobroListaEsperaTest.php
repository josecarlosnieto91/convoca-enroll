<?php
/**
 * Cobro condicionado al estado real de la inscripción (issue #96) y confirmación de
 * lista de espera en actividad de pago (issue #97).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Form_Inscripcion;
use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

final class CobroListaEsperaTest extends TestCase {

	private array $previo = array();

	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'queries'    => array(),
		);
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Una lista de espera no abre pasarela aunque la actividad sea de pago.
	 */
	public function test_lista_espera_no_cobra(): void {
		$this->assertFalse( Form_Inscripcion::debe_cobrar( 'lista_espera', 4900 ) );
	}

	/**
	 * Pendiente de pago con importe sí cobra.
	 */
	public function test_pendiente_pago_con_importe_cobra(): void {
		$this->assertTrue( Form_Inscripcion::debe_cobrar( 'pendiente_pago', 4900 ) );
	}

	/**
	 * Sin importe no hay pasarela, aunque el estado lo pida.
	 */
	public function test_pendiente_pago_sin_importe_no_cobra(): void {
		$this->assertFalse( Form_Inscripcion::debe_cobrar( 'pendiente_pago', 0 ) );
	}

	/**
	 * Confirmar una lista de espera de pago sin cobro no da la plaza.
	 */
	public function test_lista_espera_de_pago_no_se_confirma_sin_cobro(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_requiere_pago' => '1' );
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id'  => 61,
			'_convoca_estado'        => 'lista_espera',
			'_convoca_checkin_token' => '',
		);

		$resultado = Motor_Inscripcion::confirmar( 70 );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'payment_required', $resultado->get_error_code() );
		$this->assertSame( 'lista_espera', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
	}

	/**
	 * Con el cobro verificado, la misma lista de espera sí se confirma.
	 */
	public function test_lista_espera_de_pago_se_confirma_con_cobro(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_requiere_pago' => '1' );
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id'  => 61,
			'_convoca_estado'        => 'lista_espera',
			'_convoca_checkin_token' => '',
		);

		$this->assertTrue( Motor_Inscripcion::confirmar( 70, true ) );
		$this->assertSame( 'confirmada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
	}
}
