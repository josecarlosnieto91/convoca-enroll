<?php
/**
 * Informe de evaluaciones.
 *
 * Resume, por actividad, cuánta gente la evaluó y qué medias saca en gestión, instalaciones, participantes y
 * comunicación. Es lo que se mira para decidir qué mejorar el año siguiente; una media mal calculada o dos
 * actividades mezcladas estropean esa decisión.
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
 * Evaluaciones.
 */
final class ReportsEvaluacionesTest extends TestCase {

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
		$GLOBALS['db_reads']                    = array();
		$GLOBALS['convoca_test_caps']           = array( 'convoca_view_reports' => true );
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_roles']          = array();
		$_GET                                   = array();

		$this->informes = new Admin_Reports();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['db_reads']                    = array();
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
	 * Una fila del agregado, como la devuelve la base con `ARRAY_A`.
	 *
	 * @param string $id        Actividad.
	 * @param string $titulo    Título.
	 * @param int    $cuantas   Nº de evaluaciones.
	 * @param float  $gestion   Media de gestión.
	 * @return array
	 */
	private function fila( string $id, string $titulo, int $cuantas, float $gestion ): array {
		return array(
			'actividad_id'  => $id,
			'post_title'    => $titulo,
			'count'         => (string) $cuantas,
			'gestion'       => (string) $gestion,
			'instalaciones' => '3.50',
			'participantes' => '4.00',
			'comunicacion'  => '2.50',
		);
	}

	/**
	 * Sin evaluaciones, la lista sale vacía.
	 */
	public function test_sin_evaluaciones(): void {
		$this->assertSame( array(), $this->llamar( 'get_evaluaciones_data', 0 ) );
	}

	/**
	 * Cada actividad va agrupada con su recuento y sus medias.
	 */
	public function test_agrupa_por_actividad(): void {
		$GLOBALS['_wp_stores']['results_mapa']['GROUP BY pm_act.meta_value'] = array(
			$this->fila( '61', 'Ruta de otoño', 8, 4.25 ),
			$this->fila( '62', 'Taller de setas', 3, 3.0 ),
		);

		$datos = $this->llamar( 'get_evaluaciones_data', 0 );

		$this->assertCount( 2, $datos, 'una fila por actividad' );
		$this->assertSame( 'Ruta de otoño', $datos[0]['title'], 'primero la que más evaluaciones tiene' );
		$this->assertSame( 8, $datos[0]['count'] );
		$this->assertSame( 4.25, $datos[0]['gestion'], 'la media del SQL, sin dividirla otra vez por el número de evaluaciones' );
		$this->assertSame( 3.5, $datos[0]['instalaciones'] );
		$this->assertSame( ( 4.25 + 3.5 + 4.0 + 2.5 ) / 4, $datos[0]['media_global'], 'la media de las cuatro' );
		$this->assertSame( 3, $datos[1]['count'] );
	}

	/**
	 * El filtro por actividad viaja en el SQL; sin filtro, no restringe.
	 */
	public function test_el_filtro(): void {
		$this->llamar( 'get_evaluaciones_data', 61 );

		$con_filtro = implode( ' ', (array) $GLOBALS['db_reads'] );
		$this->assertMatchesRegularExpression( '/pm_act\.meta_value\s*=\s*\'?61\'?/', $con_filtro );

		$GLOBALS['db_reads'] = array();
		$this->llamar( 'get_evaluaciones_data', 0 );

		$sin_filtro = implode( ' ', (array) $GLOBALS['db_reads'] );
		$this->assertDoesNotMatchRegularExpression( '/pm_act\.meta_value\s*=/', $sin_filtro );
	}

	/**
	 * La pestaña: sus columnas, el selector de actividad y el enlace de exportación.
	 */
	public function test_la_pestana(): void {
		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_title              = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['convoca_test_posts']      = array( 61 );
		$GLOBALS['_wp_stores']['results_mapa']['GROUP BY pm_act.meta_value'] = array( $this->fila( '61', 'Ruta de otoño', 8, 4.25 ) );

		ob_start();
		try {
			$this->llamar( 'tab_evaluaciones' );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'Media Global', $html );
		$this->assertStringContainsString( 'Nº Evals', $html );
		$this->assertStringContainsString( '4.3', $html, 'la media con un decimal' );
		$this->assertStringContainsString( 'Ruta de otoño', $html );
		$this->assertStringContainsString( 'export=evaluaciones', $html, 'el enlace de exportación' );
	}

	/**
	 * Sin evaluaciones, la tabla lo dice en vez de quedarse en blanco.
	 */
	public function test_la_pestana_vacia(): void {
		ob_start();
		try {
			$this->llamar( 'tab_evaluaciones' );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'No hay evaluaciones registradas', $html );
	}
}
