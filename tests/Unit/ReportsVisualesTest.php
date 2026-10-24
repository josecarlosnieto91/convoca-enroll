<?php
/**
 * Informe de visuales: tendencia, estados y actividades más demandadas.
 *
 * Es la pantalla que se mira para decidir el calendario del trimestre: cuánta gente se apunta mes a mes, en qué estado
 * acaban las inscripciones y qué actividades tiran más. Un gráfico con los meses desordenados o con los estados
 * cambiados lleva a decidir lo contrario de lo que dicen los datos.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Reports;
use Convoca\Enroll\CPT_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-reports.php';

/**
 * Visuales.
 */
final class ReportsVisualesTest extends TestCase {

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
			'col'          => array(),
			'col_cola'     => array(),
			'queries'      => array(),
		);
		$GLOBALS['convoca_test_caps'] = array( 'convoca_view_reports' => true );
		$_GET                         = array();
		$_POST                        = array();

		$this->informes = new Admin_Reports();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']        = $this->previo;
		$GLOBALS['convoca_test_caps'] = array();
		$_GET                         = array();
		$_POST                        = array();
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
	 * Pinta una pestaña y devuelve su HTML.
	 *
	 * @param string $metodo Método.
	 * @return string
	 */
	private function pintar( string $metodo ): string {
		ob_start();
		try {
			$this->llamar( $metodo );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * La tendencia son seis meses, del más antiguo al más reciente, con su recuento.
	 */
	public function test_la_tendencia(): void {
		$GLOBALS['_wp_stores']['get_var_mapa']['post_date LIKE'] = '4';

		$datos = $this->llamar( 'get_trends_data' );

		$this->assertCount( 6, $datos['labels'], 'seis meses' );
		$this->assertCount( 6, $datos['values'] );
		$this->assertSame( array( 4, 4, 4, 4, 4, 4 ), $datos['values'] );

		// El último es el mes actual y el primero, cinco meses antes.
		$this->assertSame( date_i18n( 'F', strtotime( wp_date( 'Y-m' ) . '-01' ) ), $datos['labels'][5] );
		$this->assertSame( date_i18n( 'F', strtotime( wp_date( 'Y-m' ) . '-01 -5 months' ) ), $datos['labels'][0] );
	}

	/**
	 * Los estados: los que existen en el catálogo, todos, y con el recuento de la base.
	 */
	public function test_los_estados(): void {
		$fila            = new \stdClass();
		$fila->estado    = 'confirmada';
		$fila->total     = '7';
		$otra            = new \stdClass();
		$otra->estado    = 'pendiente';
		$otra->total     = '3';
		$inventado       = new \stdClass();
		$inventado->estado = 'inventado'; // No está en el catálogo: se ignora.
		$inventado->total  = '99';

		$GLOBALS['_wp_stores']['results_mapa']['GROUP BY meta_value'] = array( $fila, $otra, $inventado );

		$estados = $this->llamar( 'get_states_distribution' );

		foreach ( CPT_Inscripcion::STATES as $estado ) {
			$this->assertArrayHasKey( $estado, $estados, 'falta el estado: ' . $estado );
		}

		$this->assertSame( 7, $estados['confirmada'] );
		$this->assertSame( 3, $estados['pendiente'] );
		$this->assertArrayNotHasKey( 'inventado', $estados, 'un estado que no existe no entra' );
	}

	/**
	 * Las más demandadas: nombre de la actividad (no su id) y su recuento.
	 */
	public function test_las_mas_demandadas(): void {
		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;

		$fila              = new \stdClass();
		$fila->activity_id = 61;
		$fila->total       = '12';

		$GLOBALS['_wp_stores']['results_mapa']['ORDER BY total DESC'] = array( $fila );

		$datos = $this->llamar( 'get_top_demanded_activities' );

		$this->assertSame( array( 'Ruta de otoño' ), $datos['labels'] );
		$this->assertSame( array( 12 ), $datos['values'] );
	}

	/**
	 * La pestaña pinta los tres bloques y avisa cuando no hay datos.
	 */
	public function test_la_pestana(): void {
		$GLOBALS['_wp_stores']['get_var_mapa']['post_date LIKE'] = '0';

		$html = $this->pintar( 'tab_visuales' );

		$this->assertStringContainsString( 'chart', $html, 'los gráficos' );
		$this->assertNotEmpty( $html );
	}
}
