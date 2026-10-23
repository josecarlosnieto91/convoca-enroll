<?php
/**
 * El panel de monitores (CRM de check-in).
 *
 * Es la pantalla que se usa **de pie, con el móvil**, el día de la actividad: lista de actividades asignadas, tabla de
 * inscritos y botones para marcar asistencia. Lo que se prueba aquí: que sin actividades asignadas se diga (y no una
 * pantalla en blanco), que cada actividad salga con su fecha, y que la pantalla traiga sus estilos (sin ellos la tabla
 * es ilegible y los botones no se ven).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Monitor_CRM;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-monitor-crm.php';

/**
 * Panel de monitores.
 */
final class MonitorCrmRenderTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Panel.
	 *
	 * @var Admin_Monitor_CRM
	 */
	private Admin_Monitor_CRM $panel;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo                  = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_filters']        = array();
		$GLOBALS['_wp_stores']         = array(
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
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['_test_caps']         = array( 'manage_options' => true, 'edit_posts' => true );
		// `user_can()` en este arnés lee `convoca_test_user_can` (no `_test_caps`).
		$GLOBALS['convoca_test_user_can'] = array();
		unset( $_GET['actividad_id'] );
		if ( isset( $GLOBALS['_wpdb_doble'] ) ) {
			$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
			$GLOBALS['wpdb']   = $GLOBALS['_wpdb_doble'];
		}
		$this->panel = new Admin_Monitor_CRM();
	}

	/**
	 * `$wpdb` anterior, para restaurarlo.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['convoca_test_posts'], $GLOBALS['_test_caps'], $GLOBALS['convoca_test_user_can'], $_GET['actividad_id'] );
		if ( null !== $this->wpdb_previo ) {
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
	}

	/**
	 * Pinta la página.
	 *
	 * @return string
	 */
	private function pintar(): string {
		ob_start();
		try {
			$this->panel->render_page();
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
	 * @param string $fecha  Fecha de inicio.
	 * @return void
	 */
	private function actividad( int $id, string $titulo, string $fecha = '+7 days' ): void {
		$p                                     = new \WP_Post();
		$p->ID                                 = $id;
		$p->post_type                          = 'actividad';
		$p->post_status                        = 'publish';
		$p->post_title                         = $titulo;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $p;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_fecha_inicio' => ( new \DateTimeImmutable( $fecha ) )->format( 'Y-m-d H:i:s' ),
		);
		$GLOBALS['convoca_test_posts'][] = $p;
	}

	/**
	 * La pantalla trae sus estilos: sin ellos la tabla es ilegible y los botones no se ven.
	 */
	public function test_la_pantalla_trae_sus_estilos(): void {
		$html = $this->pintar();

		$this->assertStringContainsString( '<style>', $html );
		$this->assertStringContainsString( '--conv-primary', $html, 'sus variables' );
		$this->assertStringContainsString( '.conv-crm-wrap', $html, 'el contenedor que usa la propia página' );
		$this->assertStringContainsString( 'conv-crm-wrap', $html );
	}

	/**
	 * Sin actividades asignadas se dice, en vez de una pantalla en blanco.
	 */
	public function test_sin_actividades_asignadas(): void {
		$html = $this->pintar();

		$this->assertStringContainsString( 'Panel de Control - Monitores', $html );
		$this->assertStringContainsString( 'No tienes actividades asignadas', $html );
		$this->assertStringContainsString( 'Escáner QR Móvil', $html, 'el acceso al escáner está siempre' );
	}

	/**
	 * Con actividades: cada una con su título y su enlace al panel.
	 */
	public function test_la_lista_de_actividades(): void {
		$this->actividad( 61, 'Ruta de otoño', '+10 days' );

		$html = $this->pintar();

		$this->assertStringContainsString( 'Ruta de otoño', $html );
		$this->assertStringContainsString( 'actividad_id=61', $html, 'enlace a su panel' );
		$this->assertStringNotContainsString( 'No tienes actividades asignadas', $html );
	}

	/**
	 * La tabla de inscritos: nombre completo, contacto, estado y check-in; en lista de espera, la posición.
	 */
	public function test_la_tabla_de_inscritos(): void {
		$items = array(
			$this->inscrito( 70, 'Ana', 'López', 'García', 'confirmada', 'si' ),
			$this->inscrito( 72, 'Marta', 'Ruiz', '', 'confirmada', '' ),
			$this->inscrito( 71, 'Luis', '', '', 'pendiente', '' ),
		);

		$html = $this->render( 'render_table', $items, 61, false );

		$this->assertStringContainsString( 'Ana López García', $html, 'nombre completo' );
		$this->assertStringContainsString( 'ana70@example.test', $html );
		$this->assertStringContainsString( 'status-confirmada', $html, 'el estado con su clase' );
		$this->assertStringContainsString( 'Check-in', $html );
		$this->assertSame( 1, substr_count( $html, 'mark-attendance active' ), 'de dos confirmadas, solo la que dijo «sí» sale marcada' );
		$this->assertStringContainsString( 'data-status="si"', $html, 'y a la otra se le puede marcar' );
		$this->assertStringNotContainsString( 'Posición', $html, 'la posición es de la lista de espera' );

		$espera = $this->render( 'render_table', $items, 61, true );
		$this->assertStringContainsString( 'Posición', $espera );
		$this->assertStringNotContainsString( 'Check-in', $espera, 'en espera no se hace check-in' );
	}

	/**
	 * Una tabla sin nadie lo dice, en vez de una cabecera sin filas.
	 */
	public function test_la_tabla_vacia(): void {
		$this->assertStringContainsString( 'No hay registros en esta sección', $this->render( 'render_table', array(), 61, false ) );
	}

	/**
	 * El resumen: confirmadas, asistieron, faltaron y pendientes. Y el que no respondió, por diferencia.
	 */
	public function test_el_resumen(): void {
		$items = array(
			$this->inscrito( 80, 'A', '', '', 'confirmada', 'si' ),
			$this->inscrito( 81, 'B', '', '', 'confirmada', 'si' ),
			$this->inscrito( 82, 'C', '', '', 'confirmada', 'no' ),
			$this->inscrito( 83, 'D', '', '', 'confirmada', '' ),
			$this->inscrito( 84, 'E', '', '', 'pendiente', '' ),
			$this->inscrito( 85, 'F', '', '', 'pendiente_pago', '' ),
		);

		$html = $this->render( 'render_summary', $items );

		$this->assertStringContainsString( '6 total', $html );
		$this->assertStringContainsString( '2 asistieron', $html );
		$this->assertStringContainsString( '1 faltaron', $html );
		$this->assertStringContainsString( '1 sin registrar', $html, 'la confirmada que no ha dicho nada' );
		$this->assertStringContainsString( '2 pendientes', $html, 'pendiente y pendiente de pago' );
		$this->assertStringContainsString( 'conv-summary-bar', $html );
	}

	/**
	 * El panel de una actividad: si no existe, se dice; y sin ser responsable, no se enseña nada de ella.
	 */
	public function test_el_panel_de_una_actividad(): void {
		$no_existe = $this->render( 'render_activity_panel', 999 );
		$this->assertStringContainsString( 'Actividad no encontrada', $no_existe );

		$this->actividad( 61, 'Ruta de otoño' );
		$GLOBALS['_test_caps']            = array( 'manage_options' => false, 'edit_posts' => false );
		$GLOBALS['convoca_test_user_can'] = array( 'manage_options' => false );

		$sin_permiso = $this->render( 'render_activity_panel', 61 );
		$this->assertStringContainsString( 'No tienes permiso para ver esta actividad', $sin_permiso );
		$this->assertStringNotContainsString( 'Ruta de otoño', $sin_permiso, 'ni siquiera el título' );

		$GLOBALS['_test_caps']            = array( 'manage_options' => true );
		$GLOBALS['convoca_test_user_can'] = array();
		$con                              = $this->render( 'render_activity_panel', 61 );
		$this->assertStringContainsString( 'Ruta de otoño', $con );
		$this->assertStringContainsString( 'Modo Check-in Rápido', $con );
		$this->assertStringContainsString( 'Volver', $con );
	}

	/**
	 * Llama a un método privado y devuelve su salida.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return string
	 */
	private function render( string $metodo, ...$args ): string {
		$reflejo = new \ReflectionMethod( Admin_Monitor_CRM::class, $metodo );
		ob_start();
		try {
			$reflejo->invoke( $this->panel, ...$args );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Siembra una inscripción con sus metas.
	 *
	 * @param int    $id         Id.
	 * @param string $nombre     Nombre.
	 * @param string $ape1       Primer apellido.
	 * @param string $ape2       Segundo apellido.
	 * @param string $estado     Estado.
	 * @param string $asistencia Asistencia.
	 * @return \WP_Post
	 */
	private function inscrito( int $id, string $nombre, string $ape1, string $ape2, string $estado, string $asistencia ): \WP_Post {
		$p                                     = new \WP_Post();
		$p->ID                                 = $id;
		$p->post_type                          = 'inscripcion';
		$p->post_status                        = 'publish';
		$p->post_title                         = trim( $nombre . ' ' . $ape1 );
		$GLOBALS['_wp_stores']['posts'][ $id ] = $p;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_nombre'    => $nombre,
			'_convoca_apellido1' => $ape1,
			'_convoca_apellido2' => $ape2,
			'_convoca_email'     => 'ana' . $id . '@example.test',
			'_convoca_telefono'  => '600000000',
			'_convoca_estado'    => $estado,
			'_convoca_asistencia' => $asistencia,
		);

		return $p;
	}
}
