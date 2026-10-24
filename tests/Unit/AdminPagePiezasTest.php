<?php
/**
 * Piezas sueltas de la pantalla de inscripciones.
 *
 * Tres cosas que se usan desde varios sitios y que, si fallan, rompen el trabajo del día:
 * `ids_de_la_peticion()` (una acción masiva sobre lo que el usuario marcó, y solo eso), `hay_aforo()` (si cabe alguien
 * más, contando —no leyendo un contador que nadie escribe) y los avisos de la pantalla de actividad.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';

/**
 * Piezas sueltas.
 */
final class AdminPagePiezasTest extends TestCase {

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
		$GLOBALS['_wp_notices'] = array();
		$_GET                   = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']  = $this->previo;
		$GLOBALS['_wp_notices'] = array();
		$_GET                   = array();
	}

	/**
	 * Los ids de la petición: los dos nombres que usa WordPress, sin ceros, sin negativos y sin repetidos.
	 */
	public function test_ids_de_la_peticion(): void {
		$this->assertSame( array( 5, 7 ), Admin_Page::ids_de_la_peticion( array( 'ids' => array( '5', 7, '7' ) ) ) );
		$this->assertSame( array( 9 ), Admin_Page::ids_de_la_peticion( array( 'inscripcion' => '9' ) ), 'el nombre singular' );
		$this->assertSame( array( 3 ), Admin_Page::ids_de_la_peticion( array( 'ids' => array( 0, -4, '3', 'basura' ) ) ), 'fuera lo que no es un id' );
		$this->assertSame( array(), Admin_Page::ids_de_la_peticion( array() ), 'sin nada marcado, nada' );
	}

	/**
	 * Aforo: sin tope configurado siempre cabe alguien; con tope, manda el recuento de confirmadas.
	 */
	public function test_hay_aforo(): void {
		// Sin aforo configurado: no hay tope.
		$this->assertTrue( Admin_Page::hay_aforo( 61 ) );

		// Aforo 2 y una confirmada: cabe otra.
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_plazas_totales' => '2' );
		$fila = new \stdClass();
		$fila->estado = 'confirmada';
		$fila->total  = '1';
		$GLOBALS['_wp_stores']['results_mapa']['COUNT(*) AS total'] = array( $fila );
		$this->assertTrue( Admin_Page::hay_aforo( 61 ) );

		// Aforo 2 y dos confirmadas: no cabe.
		$fila->total = '2';
		$this->assertFalse( Admin_Page::hay_aforo( 61 ), 'lleno' );

		// Instalaciones antiguas: `_convoca_aforo` cuando no hay `plazas_totales`.
		$GLOBALS['_wp_stores']['post_meta'][62] = array( '_convoca_aforo' => '1' );
		$this->assertFalse( Admin_Page::hay_aforo( 62 ), 'usa el aforo antiguo y ya está lleno' );
	}

	/**
	 * Los avisos de la pantalla de actividad: solo el suyo y solo si el mensaje existe.
	 */
	public function test_los_avisos(): void {
		$GLOBALS['_wp_screen'] = 'actividad';
		$_GET['message']       = 'convoca_enroll_plazas_adjusted';

		ob_start();
		( new Admin_Page() )->notices();
		ob_end_clean();

		$this->assertCount( 1, $GLOBALS['_wp_notices'], 'sale el aviso' );
		$this->assertStringContainsString( 'plazas disponibles', (string) $GLOBALS['_wp_notices'][0]['mensaje'] );
		$this->assertSame( 'warning', $GLOBALS['_wp_notices'][0]['tipo'] );

		// En otra pantalla no se pinta.
		$GLOBALS['_wp_notices'] = array();
		$GLOBALS['_wp_screen']  = 'otra-pantalla';
		ob_start();
		( new Admin_Page() )->notices();
		ob_end_clean();
		$this->assertSame( array(), $GLOBALS['_wp_notices'] );

		// Un mensaje inventado tampoco.
		$GLOBALS['_wp_screen']  = 'actividad';
		$_GET['message']        = 'inventado';
		ob_start();
		( new Admin_Page() )->notices();
		ob_end_clean();
		$this->assertSame( array(), $GLOBALS['_wp_notices'] );
	}
}
