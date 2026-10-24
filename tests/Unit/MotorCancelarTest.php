<?php
/**
 * Cancelar una inscripción: devolver la plaza y promover al siguiente.
 *
 * Dos reglas que se ven en el mostrador: la ventana de cancelación (avisar con menos de 24 h tiene consecuencias) y la
 * **promoción desde la lista de espera**, con prioridad de socio (D8). Si al cancelar se devolviera plaza *además* de
 * promover a alguien, el aforo crecería solo con cada cancelación.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Inscripcion;
use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';

/**
 * Baja.
 */
final class MotorCancelarTest extends TestCase {

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
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_caps']           = array(
			'manage_options'        => false,
			'convoca_manage_enroll' => false,
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_caps']           = array();
	}

	/**
	 * Siembra la actividad y la inscripción.
	 *
	 * @param array  $meta_inscripcion Metas de la inscripción.
	 * @param string $fecha_inicio     Inicio de la actividad.
	 * @return void
	 */
	private function escenario( array $meta_inscripcion = array(), string $fecha_inicio = '+7 days' ): void {
		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => gmdate( 'Y-m-d H:i:s', strtotime( $fecha_inicio ) ),
			'_convoca_plazas_disponibles' => 3,
		);

		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$inscripcion->post_title            = 'Ana López';
		$inscripcion->post_date             = '2026-10-01 10:00:00';
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;

		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => 'confirmada',
			'_convoca_checkin_token' => 'token-vivo',
		);
		foreach ( $meta_inscripcion as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][70][ '_convoca_' . $clave ] = $valor;
		}
	}

	/**
	 * Un id que no es una inscripción no se cancela.
	 */
	public function test_no_encontrada(): void {
		$error = Motor_Inscripcion::cancelar( 999 );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'not_found', $error->get_error_code() );
	}

	/**
	 * Cancelar dos veces no rompe: la segunda no hace nada y contesta que sí.
	 */
	public function test_ya_cancelada(): void {
		$this->escenario( array( 'estado' => 'cancelada' ) );

		$this->assertTrue( Motor_Inscripcion::cancelar( 70 ) );
		$this->assertSame( 'cancelada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
	}

	/**
	 * Cancelar con la actividad encima y sin permiso de gestión: no se permite.
	 */
	public function test_fuera_de_plazo(): void {
		$this->escenario( array(), '+2 hours' );

		$error = Motor_Inscripcion::cancelar( 70 );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'cancel_window_closed', $error->get_error_code() );
		$this->assertSame( 'confirmada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'], 'no se ha tocado nada' );
	}

	/**
	 * Quien gestiona puede cancelar fuera de plazo.
	 */
	public function test_fuera_de_plazo_con_permiso(): void {
		$this->escenario( array(), '+2 hours' );
		$GLOBALS['convoca_test_caps'] = array( 'manage_options' => true );

		$this->assertTrue( Motor_Inscripcion::cancelar( 70 ) );
		$this->assertSame( 'cancelada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
	}

	/**
	 * En plazo y sin nadie esperando: la plaza vuelve al aforo, se invalida el QR y se guarda la nota.
	 */
	public function test_devuelve_la_plaza(): void {
		$this->escenario();

		$this->assertTrue( Motor_Inscripcion::cancelar( 70, 'Avisa por teléfono' ) );

		$consultas = implode( "\n", (array) $GLOBALS['_wp_stores']['queries'] );
		$this->assertStringContainsString( 'CAST(meta_value AS SIGNED) + 1', $consultas, 'la plaza se devuelve' );

		$meta = $GLOBALS['_wp_stores']['post_meta'][70];
		$this->assertSame( 'cancelada', $meta['_convoca_estado'] );
		$this->assertSame( '', $meta['_convoca_checkin_token'], 'el QR deja de valer' );
		$this->assertSame( 'Avisa por teléfono', $meta['_convoca_notas'] );
	}

	/**
	 * Con alguien en la lista de espera, la plaza **la ocupa esa persona**: no se suma capacidad.
	 */
	public function test_promueve_y_no_devuelve_plaza(): void {
		$this->escenario();

		$espera                        = new \WP_Post();
		$espera->ID                    = 71;
		$espera->post_type             = 'inscripcion';
		$espera->post_date             = '2026-10-02 10:00:00';
		$GLOBALS['convoca_test_posts'] = array( $espera );
		$GLOBALS['_wp_stores']['post_meta'][71] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => 'lista_espera',
			'_convoca_es_socio'     => '0',
		);

		$this->assertTrue( Motor_Inscripcion::cancelar( 70 ) );

		$consultas = implode( "\n", (array) $GLOBALS['_wp_stores']['queries'] );
		$this->assertStringNotContainsString( 'CAST(meta_value AS SIGNED) + 1', $consultas, 'la plaza la ocupa el promovido' );
		$this->assertStringContainsString( "meta_value = 'pendiente'", $consultas, 'se promueve al de la espera' );
	}

	/**
	 * D8: en la lista de espera manda el socio, aunque se apuntara después.
	 */
	public function test_prioridad_del_socio(): void {
		$this->escenario();

		$no_socio                        = new \WP_Post();
		$no_socio->ID                    = 71;
		$no_socio->post_type             = 'inscripcion';
		$no_socio->post_date             = '2026-10-01 09:00:00';
		$socio                           = new \WP_Post();
		$socio->ID                       = 72;
		$socio->post_type                = 'inscripcion';
		$socio->post_date                = '2026-10-05 09:00:00';

		// En el orden en que llegaron: primero el que no es socio.
		$GLOBALS['convoca_test_posts'] = array( $no_socio, $socio );
		$GLOBALS['_wp_stores']['post_meta'][71] = array( '_convoca_es_socio' => '0', '_convoca_estado' => 'lista_espera' );
		$GLOBALS['_wp_stores']['post_meta'][72] = array( '_convoca_es_socio' => '1', '_convoca_estado' => 'lista_espera' );

		$this->assertTrue( Motor_Inscripcion::cancelar( 70 ) );

		$consultas = implode( "\n", (array) $GLOBALS['_wp_stores']['queries'] );
		$this->assertStringContainsString( 'post_id = 72', $consultas, 'sube el socio, no el que llegó antes' );
		$this->assertStringNotContainsString( 'post_id = 71', $consultas );
		$this->assertEquals( 0, $GLOBALS['_wp_stores']['post_meta'][72]['_convoca_pagado'], 'se le resetea el pago' );
	}
}
