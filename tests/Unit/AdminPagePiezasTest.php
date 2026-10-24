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

	/**
	 * El menú: el principal y los ocho submenús, cada uno con su permiso.
	 *
	 * La pantalla de Delegados es la que reparte actividades entre monitores: estuvo escrita y sin registrar (código
	 * muerto), así que se comprueba que está.
	 */
	public function test_el_menu(): void {
		$GLOBALS['_convoca_menu_prueba']    = array();
		$GLOBALS['_convoca_submenu_prueba'] = array();

		( new Admin_Page() )->add_menu();

		$menu = $GLOBALS['_convoca_menu_prueba'];
		$this->assertCount( 1, $menu, 'un menú principal' );
		$this->assertSame( 'Actividades', $menu[0][1] );
		$this->assertSame( 'gestionar_miembros', $menu[0][2] );
		$this->assertSame( 'convoca-enroll', $menu[0][3] );

		$submenus = array();
		foreach ( $GLOBALS['_convoca_submenu_prueba'] as $sub ) {
			$submenus[ $sub[4] ] = $sub;
		}

		foreach ( array( 'convoca-enroll', 'convoca-enroll-delegados', 'conv-nueva-inscripcion', 'conv-checkin', 'conv-informes', 'conv-ajustes', 'convoca_enroll-logs', 'conv-email-queue' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $submenus, 'submenú: ' . $slug );
		}

		$this->assertSame( 'convoca_view_reports', $submenus['convoca-enroll-delegados'][3], 'los delegados ven informes' );
		$this->assertSame( 'manage_options', $submenus['conv-ajustes'][3], 'los ajustes son de administración' );
		$this->assertSame( 'common_view_logs', $submenus['convoca_enroll-logs'][3] );
	}

	/**
	 * Los assets: solo en las pantallas del plugin, y cada una con lo suyo.
	 */
	public function test_los_assets(): void {
		define( 'CONVOCA_ENROLL_URL', 'https://example.test/plugin/' );
		define( 'CONVOCA_ENROLL_VERSION', '2.6.0' );
		$page                              = new Admin_Page();
		$GLOBALS['_wp_encolados']          = array();

		// Otra pantalla: no se encola nada.
		$page->assets( 'edit.php' );
		$this->assertSame( array(), $GLOBALS['_wp_encolados'] );

		// La pantalla del plugin: su CSS, su JS y el nonce del ajax.
		$page->assets( 'toplevel_page_convoca-enroll' );
		$this->assertContains( 'conv-admin', $GLOBALS['_wp_encolados']['scripts'] );
		$this->assertArrayHasKey( 'bdeAdmin', $GLOBALS['_wp_encolados']['localizados'] );
		$this->assertSame( 'nonce-convoca_enroll_admin_nonce', $GLOBALS['_wp_encolados']['localizados']['bdeAdmin']['nonce'] );
		$this->assertEmpty( $GLOBALS['_wp_encolados']['media'] ?? null, 'los ajustes aún no' );

		// Ajustes: además, el selector de medios.
		$page->assets( 'convoca-enroll_page_conv-ajustes' );
		$this->assertTrue( $GLOBALS['_wp_encolados']['media'] ?? false );

		// Informes: Chart.js.
		$_GET['page'] = 'conv-informes';
		$page->assets( 'convoca-enroll_page_conv-informes' );
		$this->assertContains( 'chart-js', $GLOBALS['_wp_encolados']['scripts'] );

		// El CRM: su JS y su nonce de asistencia (el hook es `actividades_page_`, en plural).
		$page->assets( 'actividades_page_conv-monitor-crm' );
		$this->assertContains( 'conv-crm', $GLOBALS['_wp_encolados']['scripts'] );
		$this->assertSame( 'nonce-convoca_enroll_attendance_nonce', $GLOBALS['_wp_encolados']['localizados']['bdeCrm']['nonce'] );
	}

	/**
	 * El widget del escritorio se registra con su callback.
	 */
	public function test_el_widget(): void {
		$GLOBALS['_wp_widgets'] = array();

		( new Admin_Page() )->dashboard_widget();

		$this->assertCount( 1, $GLOBALS['_wp_widgets'] );
		$this->assertSame( 'convoca_enroll_stats', $GLOBALS['_wp_widgets'][0]['id'] );
	}
}
