<?php
/**
 * Los datos de los informes.
 *
 * Un informe de gestión se usa para decidir: plazas, tendencia de inscripciones, actividades más pedidas. Si cuenta
 * mal, la decisión es mala. Aquí se prueba el cálculo de cada serie, no el HTML.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Reports;
use Convoca\Enroll\CPT_Inscripcion;
use PHPUnit\Framework\TestCase;

// El informe consulta si hay licencia PRO: la clase vive en el núcleo hermano.
$convoca_licencia = dirname( __DIR__, 3 ) . '/convoca-core/includes/License_Manager.php';
if ( file_exists( $convoca_licencia ) ) {
	require_once $convoca_licencia;
}
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-reports.php';

/**
 * Datos de informes.
 */
final class ReportsDataTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * `$wpdb` que había antes de esta prueba: se restaura al terminar.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

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
		$this->previo           = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters'] = array();
		$GLOBALS['_wp_stores']  = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'queries'      => array(),
			'results'      => array(),
			'results_mapa' => array(),
			'get_var_mapa' => array(),
			'col'          => array(),
			'col_cola'     => array(),
		);
		// Otra prueba puede haber dejado un `$wpdb` mudo: se restaura el del arnés (con mapas de respuesta).
		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		if ( isset( $GLOBALS['_wpdb_doble'] ) ) {
			$GLOBALS['wpdb'] = $GLOBALS['_wpdb_doble'];
		}
		$GLOBALS['_test_caps'] = array( 'manage_options' => true );
		$this->informes        = new Admin_Reports();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		if ( null !== $this->wpdb_previo ) {
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
		unset( $GLOBALS['_test_caps'] );
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, ...$args ) {
		$reflejo = new \ReflectionMethod( Admin_Reports::class, $metodo );

		return $reflejo->invoke( $this->informes, ...$args );
	}

	/**
	 * El reparto por estados: todos los estados conocidos salen (también a cero) y uno inventado no se cuela.
	 */
	public function test_el_reparto_por_estados(): void {
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'GROUP BY meta_value' => array(
				(object) array(
					'estado' => 'confirmada',
					'total'  => '12',
				),
				(object) array(
					'estado' => 'pendiente',
					'total'  => '3',
				),
				(object) array(
					'estado' => 'inventado',
					'total'  => '99',
				),
			),
		);

		$reparto = $this->privado( 'get_states_distribution' );

		$this->assertSame( CPT_Inscripcion::STATES, array_keys( $reparto ), 'todos, en su orden' );
		$this->assertSame( 12, $reparto['confirmada'] );
		$this->assertSame( 3, $reparto['pendiente'] );
		$this->assertArrayNotHasKey( 'inventado', $reparto );
		$this->assertArrayHasKey( 'cancelada', $reparto, 'los que no aparecen están a cero, no ausentes' );
		$this->assertSame( 0, $reparto['cancelada'] );
	}

	/**
	 * La tendencia: seis meses siempre, con cero donde no hubo nada.
	 */
	public function test_la_tendencia_de_seis_meses(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'post_date LIKE' => '7' );

		$serie = $this->privado( 'get_trends_data' );

		$this->assertCount( 6, $serie['labels'] );
		$this->assertCount( 6, $serie['values'] );
		$this->assertSame( array( 7, 7, 7, 7, 7, 7 ), $serie['values'] );

		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'post_date LIKE' => '0' );
		$this->assertSame( array( 0, 0, 0, 0, 0, 0 ), $this->privado( 'get_trends_data' )['values'], 'no se salta ningún mes' );
	}

	/**
	 * El ranking de actividades: por número de confirmadas, con su título.
	 */
	public function test_el_ranking_de_actividades(): void {
		$a                                      = new \WP_Post();
		$a->ID                                  = 5;
		$a->post_type                           = 'actividad';
		$a->post_title                          = 'Senderismo';
		$b                                      = new \WP_Post();
		$b->ID                                  = 6;
		$b->post_type                           = 'actividad';
		$b->post_title                          = 'Yoga';
		$GLOBALS['_wp_stores']['posts'][5]      = $a;
		$GLOBALS['_wp_stores']['posts'][6]      = $b;

		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'_convoca_actividad_id' => array(
				(object) array(
					'activity_id' => '5',
					'total'       => '20',
				),
				(object) array(
					'activity_id' => '6',
					'total'       => '9',
				),
			),
		);

		$ranking = $this->privado( 'get_top_demanded_activities' );

		$this->assertSame( array( 'Senderismo', 'Yoga' ), $ranking['labels'] );
		$this->assertSame( array( 20, 9 ), $ranking['values'], 'en el orden del SQL (de más a menos)' );
	}

	/**
	 * Sin datos no revienta: series vacías o a cero.
	 */
	public function test_sin_datos(): void {
		$this->assertSame( array(), $this->privado( 'get_top_demanded_activities' )['labels'] );
		$reparto = $this->privado( 'get_states_distribution' );
		$this->assertSame( 0, array_sum( $reparto ) );
		$this->assertSame( array( 0, 0, 0, 0, 0, 0 ), $this->privado( 'get_trends_data' )['values'] );
	}
}
