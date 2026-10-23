<?php
/**
 * La pestaña financiera.
 *
 * Aquí el error caro: `_convoca_importe_pagado` se guarda en **céntimos** y la vista divide entre 100. Si alguien
 * suma céntimos como euros, el informe miente por un factor de cien. Y los pendientes de cobro tienen que salir
 * separados de lo cobrado.
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
 * Pestaña financiera.
 */
final class ReportsFinancieroTest extends TestCase {

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
		unset( $GLOBALS['_test_caps'] );
	}

	/**
	 * Pinta la pestaña del producto.
	 *
	 * @return string
	 */
	private function pintar(): string {
		$reflejo = new \ReflectionMethod( Admin_Reports::class, 'tab_financiero' );
		ob_start();
		try {
			$reflejo->invoke( $this->informes );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Siembra una actividad.
	 *
	 * @param int    $id     Id.
	 * @param string $titulo Título.
	 * @return void
	 */
	private function actividad( int $id, string $titulo ): void {
		$p                                     = new \WP_Post();
		$p->ID                                 = $id;
		$p->post_type                          = 'actividad';
		$p->post_status                        = 'publish';
		$p->post_title                         = $titulo;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $p;
	}

	/**
	 * Los céntimos se dividen entre 100 y se separan por método; el pendiente va aparte.
	 */
	public function test_los_importes_en_centimos(): void {
		$this->actividad( 50, 'Excursión' );
		$GLOBALS['_wp_stores']['col'] = array( '50' );
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'meta_value = 50' => array(
				(object) array(
					'method' => 'tarjeta',
					'total'  => '3000.00',
				),
				(object) array(
					'method' => 'bizum',
					'total'  => '1550.00',
				),
			),
		);
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'pagado' => '1250.00',
		);

		$datos = $this->privado( 'get_financial_data' );

		$this->assertCount( 1, $datos );
		$this->assertSame( 30.0, $datos[0]['tarjeta'] );
		$this->assertSame( 15.5, $datos[0]['bizum'] );
		$this->assertSame( 45.5, $datos[0]['total'], '30 + 15,50' );
		$this->assertSame( 12.5, $datos[0]['pendiente'], '1250 céntimos son 12,50 €' );
	}

	/**
	 * La tabla: dos decimales con coma y el total general sumado.
	 */
	public function test_la_tabla_y_el_total_general(): void {
		$this->actividad( 50, 'Excursión' );
		// Dos actividades: con una sola, el «total general» sería igual que la fila y no se podría afirmar que suma.
		$this->actividad( 51, 'Taller' );
		$GLOBALS['_wp_stores']['col'] = array( '50', '51' );
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'meta_value = 50' => array(
				(object) array(
					'method' => 'tarjeta',
					'total'  => '4550.00',
				),
			),
			'meta_value = 51' => array(
				(object) array(
					'method' => 'bizum',
					'total'  => '2000.00',
				),
			),
		);
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'pagado' => '0' );

		$html = $this->pintar();

		$this->assertStringContainsString( 'Excursión', $html );
		$this->assertStringContainsString( '45,50 €', $html, 'formato español' );
		$this->assertStringContainsString( 'TOTAL GENERAL', $html );
		$this->assertStringContainsString( '65,50 €', $html, '45,50 + 20,00' );
		$this->assertStringContainsString( '0,00 €', $html, 'sin pendientes' );
	}

	/**
	 * Una actividad sin ingresos no aparece: el informe es de dinero cobrado.
	 */
	public function test_sin_ingresos(): void {
		$this->actividad( 51, 'Sin cobros' );
		$GLOBALS['_wp_stores']['col'] = array( '51' );

		$html = $this->pintar();

		$this->assertStringNotContainsString( 'Sin cobros', $html );
		$this->assertStringContainsString( 'No hay datos financieros', $html );
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
}
