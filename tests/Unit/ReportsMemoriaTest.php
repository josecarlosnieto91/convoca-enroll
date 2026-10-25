<?php
/**
 * Informe de memoria anual.
 *
 * Es el documento que se lleva a una subvención o a la asamblea: por cada actividad del año, qué día fue, quién la
 * llevó, cuánta gente participó y cuántos eran socios. Un cero donde había gente, o el nombre de un responsable
 * cambiado por su id, estropea el documento.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Reports;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-reports.php';

/**
 * Memoria.
 */
final class ReportsMemoriaTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Informes.
	 *
	 * @var Admin_Reports
	 */
	private Admin_Reports $informes;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'results'      => array(),
			'results_mapa' => array(),
			'get_var_mapa' => array(),
			'get_row_mapa' => array(),
			'col'          => array(),
			'col_cola'     => array(),
			'queries'      => array(),
		);
		$GLOBALS['convoca_test_caps']           = array( 'convoca_view_reports' => true );
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_roles']          = array();
		$_GET                                   = array();

		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;

		$this->informes = new Admin_Reports();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_roles']          = array();
		$_GET                                   = array();
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, ...$args ) {
		$reflejo = new \ReflectionMethod( Admin_Reports::class, $metodo );
		return $reflejo->invoke( $this->informes, ...$args );
	}

	/**
	 * Sin actividades en el año, la memoria sale vacía (no con una fila en blanco).
	 */
	public function test_sin_actividades(): void {
		$this->assertSame( array(), $this->llamar( 'get_memoria_data', 2025 ) );
	}

	/**
	 * La consulta pide el año entero y solo actividades publicadas.
	 */
	public function test_la_consulta_del_año(): void {
		$this->llamar( 'get_memoria_data', 2025 );

		$args = end( $GLOBALS['convoca_test_get_posts_args'] );
		$this->assertSame( 'actividad', $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame(
			array( '2025-01-01 00:00:00', '2025-12-31 23:59:59' ),
			$args['meta_query'][0]['value'],
			'el año pedido, de enero a diciembre'
		);
		$this->assertSame( 'ASC', $args['order'], 'en orden de fecha' );
	}

	/**
	 * Cada fila: título, fecha, responsables por su nombre, participantes, socios y ubicación.
	 */
	public function test_una_fila_completa(): void {
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] );
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => '2025-11-14 18:00:00',
			'_convoca_responsables' => '7,9',
			'_convoca_ubicacion'    => 'Centro Social',
		);
		$GLOBALS['convoca_test_roles'][7] = array( 'monitor_actividad' );
		$GLOBALS['convoca_test_roles'][9] = array( 'monitor_actividad' );

		$stats          = new \stdClass();
		$stats->total   = '12';
		$stats->socios  = '5';
		$GLOBALS['_wp_stores']['get_row_mapa']['SUM(CASE'] = $stats;

		$filas = $this->llamar( 'get_memoria_data', 2025 );

		$this->assertCount( 1, $filas );
		$fila = $filas[0];

		$this->assertSame( 'Ruta de otoño', $fila['title'] );
		$this->assertSame( '2025-11-14 18:00:00', $fila['fecha'] );
		$this->assertStringContainsString( 'User 7', $fila['responsables'], 'el nombre, no el id' );
		$this->assertStringContainsString( 'User 9', $fila['responsables'] );
		$this->assertSame( '12', $fila['participantes'] );
		$this->assertSame( '5', $fila['socios'] );
		$this->assertSame( 'Centro Social', $fila['ubicacion'] );
	}

	/**
	 * Si la consulta de participantes no devuelve nada, la fila queda a cero (no revienta ni miente).
	 */
	public function test_sin_datos_de_participantes(): void {
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] );
		// La fecha de la actividad, que es por lo que filtra el informe del año (enroll#44).
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_fecha_inicio' => '2025-11-14 18:00:00' );
		$GLOBALS['_wp_stores']['get_row_mapa'] = array();

		$filas = $this->llamar( 'get_memoria_data', 2025 );

		$this->assertCount( 1, $filas );
		$this->assertSame( 0, $filas[0]['participantes'] );
		$this->assertSame( 0, $filas[0]['socios'] );
		$this->assertSame( '', $filas[0]['responsables'], 'sin responsables, vacío (no una coma suelta)' );
	}

	/**
	 * La pestaña pinta el año y su tabla.
	 */
	public function test_la_pestana(): void {
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] );
		// La actividad es de 2025 y la pestaña mira el año pedido: sin `year` usa el actual y la fila no sale
		// (enroll#44).
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_fecha_inicio' => '2025-11-14 18:00:00' );
		$_GET['year'] = 2025;

		ob_start();
		try {
			$this->llamar( 'tab_memoria' );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'Ruta de otoño', $html );
		$this->assertStringContainsString( '2025', $html, 'el año pedido en el selector' );
	}
}
