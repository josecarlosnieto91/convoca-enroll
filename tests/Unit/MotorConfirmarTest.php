<?php
/**
 * Confirmar una inscripción: la plaza se descuenta **una vez**, no dos.
 *
 * `pendiente` y `pendiente_pago` ya comieron su plaza al inscribirse; `lista_espera` no. Si al confirmar se descontara
 * siempre, confirmar a alguien que ya tenía plaza se la comería por segunda vez y el aforo se vaciaría solo. Y si no se
 * descontara nunca, confirmar desde la espera regalaría una butaca.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';

/**
 * Confirmación.
 */
final class MotorConfirmarTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'queries'    => array(),
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Siembra la inscripción.
	 *
	 * @param string $estado Estado.
	 * @param array  $extra  Metas extra.
	 * @return void
	 */
	private function inscripcion( string $estado, array $extra = array() ): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id'  => 61,
			'_convoca_estado'        => $estado,
			'_convoca_checkin_token' => '',
		);
		foreach ( $extra as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][70][ '_convoca_' . $clave ] = $valor;
		}
	}

	/**
	 * Devuelve las consultas lanzadas.
	 *
	 * @return string
	 */
	private function consultas(): string {
		return implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
	}

	/**
	 * Si ya está confirmada, no hay nada que hacer.
	 */
	public function test_ya_confirmada(): void {
		$this->inscripcion( 'confirmada' );

		$this->assertTrue( Motor_Inscripcion::confirmar( 70 ) );
		$this->assertStringNotContainsString( 'START TRANSACTION', $this->consultas(), 'no se abre transacción para nada' );
	}

	/**
	 * Una cancelada no se confirma por esta puerta.
	 */
	public function test_estado_invalido(): void {
		$this->inscripcion( 'cancelada' );

		$error = Motor_Inscripcion::confirmar( 70 );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_state', $error->get_error_code() );
	}

	/**
	 * Desde `pendiente` **no** se vuelve a descontar: la plaza ya estaba ocupada.
	 */
	public function test_pendiente_no_descuenta(): void {
		$this->inscripcion( 'pendiente' );

		$this->assertTrue( Motor_Inscripcion::confirmar( 70 ) );

		$this->assertStringNotContainsString( 'CAST(meta_value AS SIGNED) - 1', $this->consultas(), 'la plaza ya era suya' );
		$this->assertSame( 'confirmada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
	}

	/**
	 * Desde `pendiente_pago` tampoco.
	 */
	public function test_pendiente_pago_no_descuenta(): void {
		$this->inscripcion( 'pendiente_pago' );

		$this->assertTrue( Motor_Inscripcion::confirmar( 70 ) );
		$this->assertStringNotContainsString( 'CAST(meta_value AS SIGNED) - 1', $this->consultas() );
	}

	/**
	 * Desde la lista de espera **sí**: esa plaza no se había descontado.
	 */
	public function test_lista_espera_descuenta(): void {
		$this->inscripcion( 'lista_espera' );

		$this->assertTrue( Motor_Inscripcion::confirmar( 70 ) );

		$this->assertStringContainsString( 'CAST(meta_value AS SIGNED) - 1', $this->consultas() );
		$this->assertSame( 'confirmada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
	}

	/**
	 * Sin token de check-in se genera uno; con token, se respeta el que tenía.
	 */
	public function test_el_token(): void {
		$this->inscripcion( 'pendiente' );
		Motor_Inscripcion::confirmar( 70 );
		$this->assertNotEmpty( $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_token'], 'se genera' );

		$GLOBALS['_wp_stores']['queries'] = array();
		$this->inscripcion( 'pendiente', array( 'checkin_token' => 'el-de-siempre' ) );
		Motor_Inscripcion::confirmar( 70 );
		$this->assertSame( 'el-de-siempre', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_token'], 'no se pisa' );
	}
}
