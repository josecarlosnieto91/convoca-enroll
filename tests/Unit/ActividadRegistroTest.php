<?php
/**
 * Registro del tipo actividad y su caja de datos.
 *
 * El tipo se registra con su archivo y sus etiquetas; las metas tienen que quedar registradas **con su tipo** —una
 * plaza es un número, no texto— porque de eso depende que la API y el editor no conviertan `10` en `"diez"` ni al
 * revés. La caja de la ficha es donde se rellenan plazas, precio y fechas: si falta un campo, no hay forma de crear
 * una actividad.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';

/**
 * Registro y caja.
 */
final class ActividadRegistroTest extends TestCase {

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
		);
		$GLOBALS['_convoca_tipos_registrados'] = array();
		$GLOBALS['_convoca_metas_registrados'] = array();
		$GLOBALS['_convoca_acciones']          = array();
		$GLOBALS['_convoca_cajas']             = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                 = $this->previo;
		$GLOBALS['_convoca_tipos_registrados'] = array();
		$GLOBALS['_convoca_metas_registrados'] = array();
		$GLOBALS['_convoca_acciones']          = array();
		$GLOBALS['_convoca_cajas']             = array();
	}

	/**
	 * El tipo se registra público, con archivo propio y sin editor de bloques.
	 */
	public function test_el_tipo(): void {
		CPT_Actividad::register();

		$tipo = $GLOBALS['_convoca_tipos_registrados']['actividad'] ?? null;

		$this->assertNotNull( $tipo );
		$this->assertTrue( $tipo['public'] );
		$this->assertTrue( $tipo['has_archive'] );
		$this->assertSame( 'actividades', $tipo['rewrite']['slug'], 'el archivo vive en /actividades' );
		$this->assertFalse( $tipo['show_in_rest'], 'sin editor de bloques' );
		$this->assertSame( array( 'title', 'thumbnail', 'excerpt' ), $tipo['supports'] );
	}

	/**
	 * Las plazas y los interruptores se registran como número; el texto, como texto.
	 */
	public function test_las_metas_tienen_su_tipo(): void {
		CPT_Actividad::register();

		$metas = $GLOBALS['_convoca_metas_registrados']['actividad'] ?? array();

		$this->assertArrayHasKey( '_convoca_plazas_totales', $metas );
		$this->assertSame( 'integer', $metas['_convoca_plazas_totales']['type'], 'una plaza es un número' );
		$this->assertSame( 'integer', $metas['_convoca_requiere_pago']['type'], 'un interruptor, también' );
		$this->assertSame( 'string', $metas['_convoca_descripcion']['type'] ?? 'string' );
		$this->assertTrue( $metas['_convoca_plazas_totales']['single'], 'un solo valor' );
	}

	/**
	 * Al construirse se engancha a sus hooks.
	 */
	public function test_los_hooks(): void {
		new CPT_Actividad();

		foreach ( array( 'init', 'add_meta_boxes', 'save_post_actividad' ) as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['_convoca_acciones'], 'falta: ' . $hook );
		}
	}
}
