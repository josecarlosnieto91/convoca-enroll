<?php
/**
 * La pestaña de ocupación, pintada por el producto.
 *
 * Dos cosas que se ven en una reunión: cuánto se ha llenado cada actividad y cuáles están a punto. El color de la
 * fila (verde ≥90 %, aviso <30 %) es lo primero que mira quien gestiona. Y una actividad sin plazas no puede dividir
 * por cero.
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
 * Pestaña de ocupación.
 */
final class ReportsOcupacionTest extends TestCase {

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
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['_test_caps']         = array( 'manage_options' => true );
		unset( $_GET['start_date'], $_GET['end_date'], $_GET['status'] );
		$this->informes        = new Admin_Reports();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['_test_caps'], $_GET['start_date'], $_GET['end_date'], $_GET['status'] );
	}

	/**
	 * Pinta la pestaña **del producto**.
	 *
	 * @return string
	 */
	private function pintar(): string {
		$reflejo = new \ReflectionMethod( Admin_Reports::class, 'tab_ocupacion' );
		ob_start();
		try {
			$reflejo->invoke( $this->informes );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Siembra una actividad con sus plazas.
	 *
	 * @param int    $id     Id.
	 * @param string $titulo Título.
	 * @param int    $total  Plazas totales.
	 * @param int    $libres Plazas disponibles.
	 * @return void
	 */
	private function actividad( int $id, string $titulo, int $total, int $libres ): void {
		$p                                     = new \WP_Post();
		$p->ID                                 = $id;
		$p->post_type                          = 'actividad';
		$p->post_status                        = 'publish';
		$p->post_title                         = $titulo;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $p;
		$GLOBALS['convoca_test_posts'][]      = $p;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_fecha_inicio'       => '2026-11-07 10:00:00',
			'_convoca_plazas_totales'     => (string) $total,
			'_convoca_plazas_disponibles' => (string) $libres,
		);
	}

	/**
	 * El conteo de confirmadas viene de `CPT_Inscripcion::count_by_activity()`: se le da la fila que devolvería.
	 *
	 * @param array<int, int> $confirmadas Confirmadas por id.
	 * @return void
	 */
	private function confirmadas( array $confirmadas ): void {
		$mapa = array( 'asistencia' => '0' );
		foreach ( $confirmadas as $id => $total ) {
			// El SQL de `count_by_activity()` lleva `pm1.meta_value = <id>` tras `prepare()`.
			$mapa[ 'meta_value = ' . $id ] = array(
				(object) array(
					'estado' => 'confirmada',
					'total'  => (string) $total,
				),
			);
		}
		$GLOBALS['_wp_stores']['results_mapa'] = $mapa;
	}

	/**
	 * Sin actividades en el rango se dice, en vez de una tabla vacía.
	 */
	public function test_sin_actividades(): void {
		$html = $this->pintar();

		$this->assertStringContainsString( 'No se encontraron actividades', $html );
		$this->assertStringContainsString( 'Exportar CSV', $html );
		$this->assertStringContainsString( 'name="start_date"', $html, 'los filtros están' );
	}

	/**
	 * Los umbrales del color y el porcentaje calculado, con la tabla del producto.
	 */
	public function test_los_umbrales_y_el_porcentaje(): void {
		$this->actividad( 1, 'Llena', 10, 0 );
		$this->actividad( 2, 'Vacía', 10, 9 );
		$this->actividad( 3, 'Media', 10, 5 );
		$this->confirmadas(
			array(
				1 => 10,
				2 => 1,
				3 => 5,
			)
		);

		$html = $this->pintar();

		$this->assertStringContainsString( 'table-success', $html, '100 % se ve en verde' );
		$this->assertStringContainsString( 'table-warning', $html, '10 % se ve como aviso' );
		$this->assertStringContainsString( '100%', $html );
		$this->assertStringContainsString( '10%', $html );
		$this->assertStringContainsString( '50%', $html );
		$this->assertStringContainsString( 'Llena', $html );
	}

	/**
	 * Una actividad sin plazas vale 0 % y no rompe: la división está protegida.
	 */
	public function test_sin_plazas_no_divide_por_cero(): void {
		$this->actividad( 4, 'Sin plazas', 0, 0 );
		$this->confirmadas( array( 4 => 0 ) );

		$html = $this->pintar();

		$this->assertStringContainsString( 'Sin plazas', $html );
		$this->assertStringContainsString( '0%', $html );
		$this->assertStringContainsString( 'table-warning', $html, '0 % también es aviso' );
	}

	/**
	 * El título se enlaza a la edición **si** hay enlace; si el post no es editable, sale texto y no un `<a href="">`.
	 */
	public function test_el_enlace_de_edicion_es_opcional(): void {
		$this->actividad( 5, 'Con enlace', 10, 5 );
		$this->actividad( 6, 'Sin enlace', 10, 5 );
		$this->confirmadas(
			array(
				5 => 5,
				6 => 5,
			)
		);
		$GLOBALS['_wp_stores']['edit_links'][5] = 'https://example.test/wp-admin/post.php?post=5';

		$html = $this->pintar();

		$this->assertStringContainsString( 'href="https://example.test/wp-admin/post.php?post=5"', $html );
		// La que no tiene enlace sale como texto, **no** dentro de un `<a href="">` (que además daba un
		// `PHP Deprecated` al pasarle `null` a `esc_url`).
		$this->assertStringContainsString( '<strong>Sin enlace</strong>', $html );
		$this->assertStringNotContainsString( 'href=""', $html );
		$this->assertSame( 1, substr_count( $html, 'post.php?post=5' ) );
	}

	/**
	 * Los filtros que llegan por `$_GET` se usan y se escapan.
	 */
	public function test_los_filtros_de_la_url(): void {
		$_GET['start_date'] = '2026-11-01';
		$_GET['end_date']   = '2026-11-30';
		$_GET['status']     = 'futuras';

		$html = $this->pintar();

		$this->assertStringContainsString( 'value="2026-11-01"', $html );
		$this->assertStringContainsString( 'value="2026-11-30"', $html );
		$this->assertSame( 1, substr_count( $html, "selected='selected'" ), 'sólo una opción marcada' );
		$this->assertMatchesRegularExpression( "/value=\"futuras\"\\s+selected='selected'/", $html, 'y es «futuras»' );
	}
}
