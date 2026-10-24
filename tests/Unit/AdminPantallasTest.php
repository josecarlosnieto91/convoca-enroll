<?php
/**
 * Pantallas del panel: resumen, listados y delegados.
 *
 * Se mide lo que ve quien gestiona: los contadores del escritorio con su número real, el listado de actividades con
 * **plazas ocupadas** (no las disponibles, que es el error fácil de confundir), el botón de PDF que solo aparece con la
 * función contratada, y que las asignaciones de delegados se guardan y se vuelven a pintar marcadas.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

if ( ! defined( 'CONVOCA_ENROLL_DIR' ) ) {
	define( 'CONVOCA_ENROLL_DIR', dirname( __DIR__, 2 ) . '/' );
}
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';

/**
 * Pantallas.
 */
final class AdminPantallasTest extends TestCase {

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
	 * POST previo.
	 *
	 * @var array
	 */
	private array $post_previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Page
	 */
	private Admin_Page $pantalla;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$this->get_previo      = $_GET;
		$this->post_previo     = $_POST;
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_caps']      = array( 'convoca_view_reports' => true );
		$GLOBALS['convoca_test_tipos']     = array( 61 => 'actividad' );
		$GLOBALS['convoca_test_pro']       = array();
		$GLOBALS['convoca_test_posts']     = array();
		$GLOBALS['convoca_test_count_posts'] = array();
		// Tipos que existen en una instalación real: el escritorio depende de que `inscripcion` esté entre ellos.
		$GLOBALS['convoca_test_post_types']  = array( 'post', 'page', 'actividad', 'miembro', 'inscripcion' );
		$GLOBALS['_wp_die_lanza']          = true;
		$_GET                              = array();
		$_POST                             = array();
		$this->pantalla                    = new Admin_Page();
	}

	/**
	 * Devuelve el estado a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']               = $this->previo;
		$GLOBALS['convoca_test_caps']        = array();
		$GLOBALS['convoca_test_tipos']       = array();
		$GLOBALS['convoca_test_pro']         = array();
		$GLOBALS['convoca_test_posts']       = array();
		$GLOBALS['convoca_test_count_posts'] = array();
		$GLOBALS['convoca_test_post_types']  = array();
		$GLOBALS['_wp_die_lanza']            = false;
		$_GET                                = $this->get_previo;
		$_POST                               = $this->post_previo;
	}

	/**
	 * Captura lo que pinta una pantalla.
	 *
	 * @param callable $pintar Función que pinta.
	 * @return string
	 */
	private function pintar( callable $pintar ): string {
		ob_start();
		try {
			$pintar();
		} finally {
			$salida = (string) ob_get_clean();
		}

		return $salida;
	}

	/**
	 * Sin el sistema de inscripciones activo, el escritorio lo dice en vez de dar ceros.
	 */
	public function test_el_widget_sin_inscripciones(): void {
		$GLOBALS['convoca_test_post_types'] = array( 'post', 'page', 'actividad' );

		$html = $this->pintar( fn() => $this->pantalla->render_widget() );

		$this->assertStringContainsString( 'no está activo', $html );
		$this->assertStringNotContainsString( 'conv-stat-num', $html );
	}

	/**
	 * Con el sistema activo: total, confirmadas, en espera y próximas, cada uno con su número.
	 */
	public function test_el_widget_con_numeros(): void {

		$GLOBALS['convoca_test_count_posts']['inscripcion'] = array( 'publish' => 34 );
		$GLOBALS['_wp_stores']['get_var_mapa'] = array(
			'confirmada'   => '21',
			'lista_espera' => '5',
		);
		// Cuatro actividades próximas.
		$GLOBALS['convoca_test_posts'] = array(
			$this->actividad( 61, 'A' ),
			$this->actividad( 62, 'B' ),
			$this->actividad( 63, 'C' ),
			$this->actividad( 64, 'D' ),
		);

		$html = $this->pintar( fn() => $this->pantalla->render_widget() );

		$this->assertStringContainsString( '34', $html, 'total publicadas' );
		$this->assertStringContainsString( '21', $html, 'confirmadas' );
		$this->assertStringContainsString( '5', $html, 'en espera' );
		$this->assertStringContainsString( '4', $html, 'próximas' );
		$this->assertStringContainsString( 'Actividades próximas', $html );
	}

	/**
	 * Sembrar una actividad con fechas y plazas.
	 *
	 * @param int    $id    Id.
	 * @param string $título Título.
	 * @param int    $total Plazas.
	 * @param int    $disp  Disponibles.
	 * @return \WP_Post
	 */
	private function actividad( int $id, string $título, int $total = 20, int $disp = 20 ): \WP_Post {
		$post                  = new \WP_Post();
		$post->ID              = $id;
		$post->post_type       = 'actividad';
		$post->post_title      = $título;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_fecha_inicio'       => '2026-11-20 10:00:00',
			'_convoca_plazas_totales'     => (string) $total,
			'_convoca_plazas_disponibles' => (string) $disp,
			'_convoca_ubicacion'          => 'Centro Social',
		);

		return $post;
	}

	/**
	 * El listado de actividades enseña **ocupadas** de total, no disponibles.
	 */
	public function test_el_listado_de_actividades(): void {
		$GLOBALS['convoca_test_posts'] = array( $this->actividad( 61, 'Ruta de otoño', 20, 15 ) );

		$html = $this->pintar( fn() => $this->pantalla->render_actividades() );

		$this->assertStringContainsString( 'Ruta de otoño', $html );
		$this->assertStringContainsString( '5 / 20', $html, '5 ocupadas de 20' );
		$this->assertStringNotContainsString( '15 / 20', $html, 'las disponibles no son las ocupadas' );
		$this->assertStringContainsString( 'Centro Social', $html );
		$this->assertStringContainsString( '2026-11-20 10:00:00', $html );
	}

	/**
	 * Sin actividades, la tabla sale vacía pero la pantalla no se rompe.
	 */
	public function test_sin_actividades(): void {
		$html = $this->pintar( fn() => $this->pantalla->render_actividades() );

		$this->assertStringContainsString( 'Gestión de Actividades', $html );
		$this->assertStringContainsString( '<tbody></tbody>', $html, 'la tabla va vacía' );
	}

	/**
	 * La pantalla de inscripciones ofrece alta, CSV y las columnas.
	 */
	public function test_el_listado_de_inscripciones(): void {
		$html = $this->pintar( fn() => $this->pantalla->render_inscripciones() );

		$this->assertStringContainsString( 'Nueva inscripción', $html );
		$this->assertStringContainsString( 'convoca_enroll_export_csv', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html, 'el nonce del lote, con el nombre que valida el manejador' );
	}

	/**
	 * Sin la función contratada, el PDF no se ofrece: se ofrece contratarlo.
	 */
	public function test_el_pdf_sin_pro(): void {
		$html = $this->pintar( fn() => $this->pantalla->render_inscripciones() );

		$this->assertStringContainsString( 'Exportar PDF (PRO)', $html );
		$this->assertStringNotContainsString( 'action=convoca_enroll_export_inscripciones_pdf', $html );
	}

	/**
	 * Con la función contratada, sí.
	 */
	public function test_el_pdf_con_pro(): void {
		$GLOBALS['convoca_test_pro']['pdf_memories'] = true;

		$html = $this->pintar( fn() => $this->pantalla->render_inscripciones() );

		$this->assertStringContainsString( 'convoca_enroll_export_inscripciones_pdf', $html );
		$this->assertStringNotContainsString( 'Exportar PDF (PRO)', $html );
	}

	/**
	 * Con un id en la URL se abre la ficha de esa inscripción, no el listado.
	 */
	public function test_con_id_se_abre_la_ficha(): void {
		$_GET['inscripcion_id'] = '70';
		$inscripcion                  = new \WP_Post();
		$inscripcion->ID              = 70;
		$inscripcion->post_type       = 'inscripcion';
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;
		$GLOBALS['_wp_stores']['post_meta'][70] = array( '_convoca_nombre' => 'Ana' );

		$html = $this->pintar( fn() => $this->pantalla->render_inscripciones() );

		$this->assertStringContainsString( 'Ana', $html );
		$this->assertStringNotContainsString( 'Nueva inscripción', $html, 'no es el listado' );
	}

	/**
	 * Delegados exige permiso: sin él se corta.
	 */
	public function test_delegados_sin_permiso(): void {
		$GLOBALS['convoca_test_caps']['convoca_view_reports'] = false;

		$this->expectException( \Convoca_Json_Cortado::class );
		$this->pantalla->render_delegados();
	}

	/**
	 * Con permiso, cada persona con su desplegable de actividades, y las ya asignadas marcadas.
	 */
	public function test_delegados_pinta_asignaciones(): void {
		$GLOBALS['convoca_test_users_lista'] = array(
			(object) array( 'ID' => 7, 'display_name' => 'Marta', 'user_email' => 'marta@example.test' ),
			(object) array( 'ID' => 9, 'display_name' => 'Luis', 'user_email' => 'luis@example.test' ),
		);
		$GLOBALS['convoca_test_posts'] = array( $this->actividad( 61, 'Ruta de otoño' ), $this->actividad( 62, 'Taller' ) );
		$GLOBALS['_wp_stores']['options']['convoca_enroll_delegados_actividades'] = array(
			7 => array( '62' ),
		);

		$html = $this->pintar( fn() => $this->pantalla->render_delegados() );

		$this->assertStringContainsString( '<strong>Marta</strong> (marta@example.test)', $html );
		$this->assertStringContainsString( '<strong>Luis</strong> (luis@example.test)', $html );
		$this->assertMatchesRegularExpression( '/value="62"\s+selected/', $html, 'la asignada va marcada' );
		$this->assertStringContainsString( 'Guardar Asignaciones', $html );
	}

	/**
	 * Guardar asignaciones: se guarda lo enviado y se confirma en pantalla.
	 */
	public function test_guardar_delegados(): void {
		$GLOBALS['convoca_test_users_lista'] = array(
			(object) array( 'ID' => 7, 'display_name' => 'Marta', 'user_email' => 'marta@example.test' ),
		);
		$_POST = array(
			'convoca_enroll_save_delegados' => '1',
			'_wpnonce'                       => 'bueno',
			'delegados'                      => array( '7' => array( '61', '62' ) ),
		);

		$html = $this->pintar( fn() => $this->pantalla->render_delegados() );

		$this->assertSame( array( '7' => array( '61', '62' ) ), $GLOBALS['_wp_stores']['options']['convoca_enroll_delegados_actividades'] );
		$this->assertStringContainsString( 'Asignaciones guardadas', $html );
	}
}
