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
	 * Los mensajes que el producto escribe de verdad se cuentan.
	 *
	 * Antes esta prueba fijaba el defecto —`<td>0</td>`—, porque el informe buscaba `Confirmad` con mayúscula y el
	 * producto no escribe esa palabra en ningún sitio: el CRM registra `Acción "confirmar" realizada por monitor…`
	 * (enroll#43).
	 */
	public function test_los_mensajes_reales_se_cuentan(): void {
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'convoca_logs' => array(
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Acción "confirmar" realizada por monitor sobre inscripción #5.' ),
			),
		);
		$_GET['tab'] = 'monitores';
		$html        = $this->pagina();

		// La columna de confirmaciones sale con 1, no con 0.
		$this->assertMatchesRegularExpression( '/<strong>Marta<\/strong>.*?<td>1<\/td>/s', $html, 'sí se cuenta' );
	}

	/**
	 * Las tres acciones se clasifican por separado, con los textos reales de cada sitio del producto.
	 */
	public function test_las_tres_acciones_se_clasifican(): void {
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'convoca_logs' => array(
				// `class-admin-monitor-crm.php:83`
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Acción "confirmar" realizada por monitor sobre inscripción #5.' ),
				// `class-admin-monitor-crm.php:200`
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Acción "cancelar" realizada por monitor sobre inscripción #6.' ),
				// `Rest_API.php` / `Motor_Inscripcion.php`
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Check-in exitoso vía QR por usuario #7' ),
				// Ruido: no debe contar como nada.
				(object) array( 'user_id' => 7, 'actividad_id' => 61, 'monitor' => 'Marta', 'actividad' => 'Ruta', 'message' => 'Exportación CSV desde monitor realizada para actividad #61.' ),
			),
		);
		$_GET['tab'] = 'monitores';
		$html        = $this->pagina();

		// confirmadas=1, canceladas=1, checkins=1
		$this->assertStringContainsString( '<td>1</td>', $html );
		$this->assertSame( 3, substr_count( $html, '<td>1</td>' ), 'las tres columnas valen 1 y el ruido no suma' );
	}
}
