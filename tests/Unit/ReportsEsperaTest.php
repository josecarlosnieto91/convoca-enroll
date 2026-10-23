<?php
/**
 * La pestaña de lista de espera.
 *
 * El dato que se mira aquí es el **ratio de promoción**: de todas las personas que pasaron por la espera, cuántas
 * acabaron dentro. Si se cuenta mal, se decide abrir o cerrar plazas con una cifra falsa.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Reports;
use PHPUnit\Framework\TestCase;

$convoca_licencia = dirname( __DIR__, 3 ) . '/convoca-core/includes/License_Manager.php';
if ( file_exists( $convoca_licencia ) ) {
	require_once $convoca_licencia;
}
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-reports.php';

/**
 * Pestaña de espera.
 */
final class ReportsEsperaTest extends TestCase {

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
			'edit_links'   => array(),
			'col'          => array(),
			'col_cola'     => array(),
		);
		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		if ( isset( $GLOBALS['_wpdb_doble'] ) ) {
			$GLOBALS['wpdb'] = $GLOBALS['_wpdb_doble'];
		}
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['_test_caps']         = array( 'manage_options' => true );
		$this->informes        = new Admin_Reports();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		// La lista que lee `get_posts()` es global: si se queda, la siguiente prueba ve actividades ajenas.
		unset( $GLOBALS['convoca_test_posts'] );
		if ( null !== $this->wpdb_previo ) {
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
		unset( $GLOBALS['_test_caps'], $GLOBALS['convoca_test_posts'] );
	}

	/**
	 * Pinta la pestaña del producto.
	 *
	 * @return string
	 */
	private function pintar(): string {
		$reflejo = new \ReflectionMethod( Admin_Reports::class, 'tab_espera' );
		ob_start();
		try {
			$reflejo->invoke( $this->informes );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Una actividad con 4 en espera y 1 promovida: 20 % de promoción.
	 */
	public function test_el_ratio_de_promocion(): void {
		$p               = new \WP_Post();
		$p->ID           = 40;
		$p->post_type    = 'actividad';
		$p->post_status  = 'publish';
		$p->post_title   = 'Ruta';
		$GLOBALS['_wp_stores']['posts'][40] = $p;

		$GLOBALS['_wp_stores']['col'] = array( '40' );
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'meta_value = 40' => array(
				(object) array(
					'estado' => 'lista_espera',
					'total'  => '4',
				),
			),
		);
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'promovido_espera' => '1',
		);

		$html = $this->pintar();

		$this->assertStringContainsString( 'Ruta', $html );
		$this->assertStringContainsString( '<td>4</td>', $html, 'cuatro en espera' );
		$this->assertStringContainsString( '20%', $html, '1 de 5 pasaron: 20 %' );
		// La vista castea a `(float)`: 'N/A' acaba pintado como «0», que se lee como «0 días». Se fija el
		// comportamiento actual y se deja anotado como defecto a decidir (no se cambia producto desde una prueba).
		$this->assertStringContainsString( '<td>0</td>', $html, 'el tiempo medio sale como 0, no como N/A' );
	}

	/**
	 * Una actividad sin nadie en espera no aparece: la tabla es de espera, no de todas.
	 */
	public function test_una_actividad_sin_espera_no_sale(): void {
		$p               = new \WP_Post();
		$p->ID           = 41;
		$p->post_type    = 'actividad';
		$p->post_status  = 'publish';
		$p->post_title   = 'Sin cola';
		$GLOBALS['_wp_stores']['posts'][41] = $p;

		$GLOBALS['_wp_stores']['col'] = array( '41' );
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'meta_value = 41' => array(
				(object) array(
					'estado' => 'confirmada',
					'total'  => '9',
				),
			),
		);

		$html = $this->pintar();

		$this->assertStringNotContainsString( 'Sin cola', $html );
		$this->assertStringContainsString( 'No hay actividades con lista de espera', $html );
	}

	/**
	 * Sin ninguna inscripción, la pestaña se pinta y lo dice.
	 */
	public function test_sin_datos(): void {
		$html = $this->pintar();

		$this->assertStringContainsString( 'No hay actividades con lista de espera', $html );
		$this->assertStringContainsString( 'Exportar CSV', $html );
		$this->assertStringContainsString( 'ratio de promoción', $html, 'con su explicación' );
	}
}
