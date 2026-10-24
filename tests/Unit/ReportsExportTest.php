<?php
/**
 * Exportación de los informes a CSV.
 *
 * Los informes se sacan en CSV para llevarlos a una reunión o a una subvención. Dos cosas importan: quién puede
 * sacarlos (son datos de personas) y que el fichero lleve lo que promete su cabecera —un informe con las columnas
 * cambiadas se presenta mal y nadie lo nota hasta que es tarde—.
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
 * Exportación.
 */
final class ReportsExportTest extends TestCase {

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
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
			'results'    => array(),
			'results_mapa' => array(),
			'get_var_mapa' => array(),
			'col'        => array(),
			'col_cola'   => array(),
			'queries'    => array(),
		);
		$GLOBALS['_csv']                     = array();
		$GLOBALS['nonce_invalido']           = false;
		$GLOBALS['convoca_test_caps']        = array( 'convoca_view_reports' => true );
		$GLOBALS['convoca_test_posts']       = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$_GET                                = array();

		if ( isset( $GLOBALS['_wpdb_doble'] ) ) {
			$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
			$GLOBALS['wpdb']   = $GLOBALS['_wpdb_doble'];
		}

		$this->informes = new Admin_Reports();
	}

	/**
	 * `$wpdb` anterior.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['db_reads']                    = array();
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['_csv']                        = array();
		$GLOBALS['nonce_invalido']              = false;
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$_GET                                   = array();
		if ( null !== $this->wpdb_previo ) {
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
	}

	/**
	 * Pide una exportación.
	 *
	 * @param string $tipo Tipo.
	 * @param array  $extra Parámetros extra del GET.
	 * @return void
	 */
	private function exportar( string $tipo, array $extra = array() ): void {
		$_GET = array_merge(
			array(
				'export'   => $tipo,
				'_wpnonce' => 'nonce',
			),
			$extra
		);

		try {
			$this->informes->handle_export();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			unset( $cortado );
		}
	}

	/**
	 * El CSV que se ha generado.
	 *
	 * @return array|null
	 */
	private function csv(): ?array {
		return $GLOBALS['_csv'][0] ?? null;
	}

	/**
	 * Sin `export` no se hace nada, y sin la capacidad tampoco.
	 */
	public function test_las_guardas(): void {
		$_GET = array();
		$this->informes->handle_export();
		$this->assertSame( array(), $GLOBALS['_csv'] );

		$GLOBALS['convoca_test_caps'] = array( 'convoca_view_reports' => false );
		$this->exportar( 'ocupacion' );
		$this->assertSame( array(), $GLOBALS['_csv'], 'sin permiso no hay fichero' );
	}

	/**
	 * Nonce caducado: muere y no se genera nada.
	 */
	public function test_nonce_invalido(): void {
		// `wp_verify_nonce()` de este arnés se hace fallar con `nonce_invalido`.
		$GLOBALS['nonce_invalido'] = true;

		$this->exportar( 'ocupacion' );

		$this->assertSame( array(), $GLOBALS['_csv'] );
	}

	/**
	 * Ocupación: sus seis columnas y el nombre del fichero con el tipo.
	 */
	public function test_exporta_ocupacion(): void {
		$this->exportar( 'ocupacion' );

		$csv = $this->csv();
		$this->assertNotNull( $csv );
		$this->assertStringContainsString( 'reporte-convoca-ocupacion-', (string) $csv['fichero'] );
		$this->assertSame(
			array( 'Actividad', 'Fecha', 'Plazas Totales', 'Ocupadas', 'Disponibles', 'Asistentes' ),
			$csv['cabecera']
		);
	}

	/**
	 * Lista de espera: actividad, en espera y ratio de promoción.
	 */
	public function test_exporta_espera(): void {
		$this->exportar( 'espera' );

		$this->assertSame(
			array( 'Actividad', 'En espera', 'Ratio promocion (%)' ),
			$this->csv()['cabecera']
		);
		$this->assertStringContainsString( 'reporte-convoca-espera-', (string) $this->csv()['fichero'] );
	}

	/**
	 * Financiero: sus cuatro columnas (total, tarjeta, bizum y pendiente).
	 */
	public function test_exporta_financiero(): void {
		$this->exportar( 'financiero' );

		$this->assertSame(
			array( 'Actividad', 'Total (€)', 'Tarjeta (€)', 'Bizum (€)', 'Pendiente (€)' ),
			$this->csv()['cabecera']
		);
	}

	/**
	 * Memoria: el año se puede pedir; por omisión, el actual.
	 */
	public function test_exporta_memoria(): void {
		$this->exportar( 'memoria', array( 'year' => '2025' ) );

		$this->assertSame(
			array( 'Actividad', 'Fecha', 'Responsables', 'Participantes', 'Socios/as', 'Ubicación' ),
			$this->csv()['cabecera']
		);
		$this->assertStringContainsString( 'reporte-convoca-memoria-', (string) $this->csv()['fichero'] );
	}

	/**
	 * Evaluaciones: dieciséis columnas, en lotes, y el filtro por actividad se aplica.
	 */
	public function test_exporta_evaluaciones(): void {
		// Un lote de una evaluación y luego vacío: el `do-while` corta.
		$GLOBALS['_wp_stores']['col_cola'] = array( array( 501 ), array() );
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_eval_actividad_id' => 61,
			'_convoca_eval_usuario_id'   => 7,
			'_convoca_eval_gestion'      => '5',
		);

		$this->exportar( 'evaluaciones', array( 'filter_actividad' => '61' ) );

		$csv = $this->csv();
		$this->assertNotNull( $csv );
		$this->assertCount( 16, $csv['cabecera'], 'las columnas de la evaluación' );
		$this->assertSame( 'ID', $csv['cabecera'][0] );
		$this->assertSame( 501, $csv['filas'][0][0], 'el id de la evaluación' );
		$this->assertSame( '5', $csv['filas'][0][4], 'la puntuación de gestión' );

		$consultas = implode( ' ', (array) ( $GLOBALS['db_reads'] ?? array() ) );
		$this->assertMatchesRegularExpression( '/pm_act\.meta_value\s*=\s*\'?61\'?/', $consultas, 'el filtro por actividad viaja en el SQL' );

		// Y sin filtro, la consulta no lo lleva.
		$GLOBALS['db_reads']       = array();
		$GLOBALS['_csv']           = array();
		$this->exportar( 'evaluaciones' );
		$sin_filtro = implode( ' ', (array) $GLOBALS['db_reads'] );
		$this->assertDoesNotMatchRegularExpression( '/pm_act\.meta_value\s*=/', $sin_filtro, 'sin filtro no se restringe' );
	}
}
