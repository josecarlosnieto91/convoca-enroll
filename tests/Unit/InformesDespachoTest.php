<?php
/**
 * Informes: el despacho de pestañas y el informe de monitores.
 *
 * Una pestaña que no existe no puede dejar la pantalla en blanco ni enseñar el informe de otra; y la memoria PRO, sin
 * licencia, no debe aparecer. El informe de monitores cuenta **por persona y actividad** a partir de los mensajes del
 * registro, que es donde es fácil contar de más.
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
 * Informes.
 */
final class InformesDespachoTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * GET previo.
	 *
	 * @var array
	 */
	private array $get_previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$this->get_previo      = $_GET;
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_pro']   = array();
		$GLOBALS['convoca_test_caps']  = array( 'manage_options' => true, 'convoca_view_reports' => true );
		$GLOBALS['convoca_test_posts'] = array();
		$_GET                          = array();
	}

	/**
	 * Devuelve el estado a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']        = $this->previo;
		$GLOBALS['convoca_test_pro']  = array();
		$GLOBALS['convoca_test_caps'] = array();
		$GLOBALS['convoca_test_posts'] = array();
		$_GET                         = $this->get_previo;
	}

	/**
	 * Pinta la página de informes.
	 *
	 * @return string
	 */
	private function pagina(): string {
		ob_start();
		try {
			Admin_Reports::render();
		} finally {
			$salida = (string) ob_get_clean();
		}

		return $salida;
	}

	/**
	 * Sin pestaña en la URL se abre ocupación, y el formulario de fechas está ahí.
	 */
	public function test_por_defecto_ocupacion(): void {
		$html = $this->pagina();

		$this->assertStringContainsString( 'Informes de Inscripciones', $html );
		$this->assertStringContainsString( '>Desde<', $html );
		$this->assertStringContainsString( '>Hasta<', $html );
	}

	/**
	 * Las seis pestañas se pintan y la activa es la que toca.
	 */
	public function test_las_pestanas(): void {
		$_GET['tab'] = 'financiero';
		$html        = $this->pagina();

		foreach ( array( 'Ocupación', 'Gráficos', 'Lista de espera', 'Financiero', 'Actividad Monitores', 'Evaluaciones' ) as $etiqueta ) {
			$this->assertStringContainsString( $etiqueta, $html, 'falta la pestaña: ' . $etiqueta );
		}
		$this->assertSame( 1, substr_count( $html, 'nav-tab-active' ) );
		$this->assertMatchesRegularExpression( '/nav-tab nav-tab-active">\s*Financiero/', $html );
	}

	/**
	 * Una pestaña inventada no deja la pantalla vacía: se abre la de por defecto.
	 */
	public function test_pestana_inventada(): void {
		$_GET['tab'] = 'lo-que-sea';
		$html        = $this->pagina();

		$this->assertStringContainsString( '>Desde<', $html, 'cae en ocupación' );
		$this->assertStringNotContainsString( 'Ingresos Totales', $html );
	}

	/**
	 * Cada pestaña trae lo suyo.
	 */
	public function test_cada_pestana_su_contenido(): void {
		$_GET['tab'] = 'espera';
		$this->assertStringContainsString( 'Personas en espera', $this->pagina() );

		$_GET['tab'] = 'financiero';
		$this->assertStringContainsString( 'Ingresos Totales', $this->pagina() );

		$_GET['tab'] = 'monitores';
		$this->assertStringContainsString( 'Confirmaciones', $this->pagina() );
	}

	/**
	 * Sin licencia, la memoria ni aparece en las pestañas ni se puede abrir por la URL.
	 */
	public function test_memoria_sin_licencia(): void {
		$_GET['tab'] = 'memoria';
		$html        = $this->pagina();

		$this->assertStringNotContainsString( 'Memoria Actividades', $html, 'no se ofrece' );
		$this->assertStringContainsString( '>Desde<', $html, 'y la URL no la abre' );
	}

	/**
	 * Con licencia, aparece y se abre.
	 */
	public function test_memoria_con_licencia(): void {
		$GLOBALS['convoca_test_pro']['pdf_memories'] = true;
		$_GET['tab']                                 = 'memoria';
		$html                                        = $this->pagina();

		$this->assertStringContainsString( 'Memoria Actividades', $html );
	}

	/**
	 * Monitores sin registros: lo dice, no pinta una tabla vacía sin explicación.
	 */
	public function test_monitores_sin_datos(): void {
		$GLOBALS['_wp_stores']['results_mapa'] = array( 'convoca_logs' => array() );
		$_GET['tab']                           = 'monitores';

		$this->assertStringContainsString( 'No se han registrado acciones de monitores', $this->pagina() );
	}

	/**
	 * Monitores con registros: se agrupa por persona y actividad y se cuenta cada tipo de acción.
	 */
	public function test_monitores_cuenta_por_persona_y_actividad(): void {
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'convoca_logs' => array(
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Confirmada por el monitor' ),
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Confirmada por el monitor' ),
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Cancelada por el monitor' ),
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Check-in realizado' ),
				(object) array( 'user_id' => 9, 'actividad_id' => 62, 'monitor' => null, 'actividad' => null, 'message' => 'Check-in' ),
				(object) array( 'user_id' => 0, 'actividad_id' => 62, 'monitor' => 'Sistema', 'actividad' => 'Taller', 'message' => 'Confirmada' ),
			),
		);
		$_GET['tab'] = 'monitores';
		$html        = $this->pagina();

		$this->assertStringContainsString( '<strong>Marta</strong>', $html );
		// Marta: 2 confirmadas, 1 cancelada, 1 check-in.
		$this->assertMatchesRegularExpression( '/<strong>Marta<\/strong>.*?<td>2<\/td>\s*<td>1<\/td>\s*<td>1<\/td>/s', $html );
		$this->assertStringContainsString( 'Unknown', $html, 'sin monitor se marca, no se deja en blanco' );
		$this->assertStringNotContainsString( '<strong>Sistema</strong>', $html, 'sin usuario no es una acción de nadie' );
	}

	/**
	 * **Comportamiento actual, y defecto**: la comparación es sensible a mayúsculas (`Confirmad`), pero el producto
	 * escribe «confirmada» en minúscula. Con los mensajes reales el informe cuenta 0 confirmaciones.
	 */
	public function test_los_mensajes_reales_no_se_cuentan(): void {
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'convoca_logs' => array(
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Inscripción confirmada' ),
			),
		);
		$_GET['tab'] = 'monitores';
		$html        = $this->pagina();

		$this->assertMatchesRegularExpression( '/<strong>Marta<\/strong>.*?<td>0<\/td>/s', $html, 'no se cuenta' );
	}
}
