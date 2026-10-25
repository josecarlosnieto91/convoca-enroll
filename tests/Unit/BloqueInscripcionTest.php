<?php
/**
 * Los bloques de inscripción: qué se registra y qué se pinta en cada uno.
 *
 * `includes/Block_Inscripcion.php` estaba al 0 % (85 statements). Es lo que ve una persona en la entrada de una
 * actividad y lo que ve quien la edita cuando se ha dejado la actividad sin elegir: aquí se prueba que cada uno
 * reciba el mensaje que le sirve, y que el contador de lista de espera no mienta.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Block_Inscripcion;
use PHPUnit\Framework\TestCase;

class BloqueInscripcionTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array(), 'results_mapa' => array() );
		$GLOBALS['_convoca_acciones'] = array();
		$GLOBALS['_wp_encolados']     = array();
		$GLOBALS['_wp_registrados']   = array();
		$GLOBALS['_wp_bloques']       = array();
		$GLOBALS['convoca_test_caps'] = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_convoca_acciones'], $GLOBALS['_wp_encolados'], $GLOBALS['_wp_registrados'], $GLOBALS['_wp_bloques'] );
	}

	/**
	 * El bloque se engancha al arranque y al editor.
	 */
	public function test_los_enganches(): void {
		new Block_Inscripcion();

		$acciones = array_merge( (array) ( $GLOBALS['_wp_stores']['acciones'] ?? array() ), (array) ( $GLOBALS['_convoca_acciones'] ?? array() ) );
		$this->assertNotEmpty( $acciones['init'] ?? array(), 'sin `init` no se registra ningún bloque' );
		$this->assertNotEmpty( $acciones['enqueue_block_editor_assets'] ?? array(), 'sin esto el editor no carga el JS' );
	}

	/**
	 * En el editor se encola el script del bloque.
	 */
	public function test_los_assets_del_editor(): void {
		( new Block_Inscripcion() )->enqueue_editor_assets();

		$this->assertContains( 'convoca-enroll-blocks', (array) ( $GLOBALS['_wp_encolados']['scripts'] ?? array() ) );
	}

	/**
	 * El registro del bloque: script con sus dependencias y versión, y su `render_callback`.
	 */
	public function test_el_registro_del_bloque(): void {
		( new Block_Inscripcion() )->register_blocks();

		$script = (array) ( $GLOBALS['_wp_registrados']['scripts']['convoca-enroll-blocks'] ?? array() );
		$this->assertStringContainsString( 'assets/js/convoca-enroll-blocks.js', (string) ( $script['src'] ?? '' ) );
		$this->assertSame( CONVOCA_ENROLL_VERSION, $script['ver'] ?? null, 'el JS se cachea con la versión del plugin' );
		$this->assertContains( 'wp-server-side-render', (array) ( $script['deps'] ?? array() ), 'el editor necesita el render de servidor' );

		$bloque = (array) ( $GLOBALS['_wp_bloques'] ?? array() );
		$this->assertNotEmpty( $bloque, 'no se registró ningún bloque' );

		$clave = array_key_first( $bloque );
		$this->assertStringContainsString( 'blocks/inscripcion', (string) $clave );
		$this->assertSame( 3, $bloque[ $clave ]['apiVersion'] ?? null );
		$render = $bloque[ $clave ]['render_callback'] ?? null;
		$this->assertIsArray( $render, 'el bloque se pinta en el servidor' );
		$this->assertInstanceOf( Block_Inscripcion::class, $render[0] );
		$this->assertSame( 'render_inscripcion', $render[1] );
	}

	/**
	 * Sin actividad elegida, quien puede arreglarlo recibe una instrucción; un visitante, un aviso normal.
	 */
	public function test_sin_actividad_elige_el_mensaje(): void {
		$bloque = new Block_Inscripcion();

		$GLOBALS['convoca_test_caps']['edit_posts'] = true;
		$para_editor = $bloque->render_inscripcion( array() );
		$this->assertStringContainsString( 'Selecciona una actividad en el editor', $para_editor );

		$GLOBALS['convoca_test_caps']['edit_posts'] = false;
		$para_visitante = $bloque->render_inscripcion( array() );
		$this->assertStringNotContainsString( 'en el editor', $para_visitante, 'a un visitante no se le dan instrucciones de editor' );
		$this->assertStringContainsString( 'ninguna actividad abierta', $para_visitante );
	}

	/**
	 * El bloque de lista de espera no pinta nada si no hay actividad.
	 */
	public function test_la_lista_de_espera_sin_actividad(): void {
		$this->assertSame( '', ( new Block_Inscripcion() )->render_lista_espera( array() ) );
	}

	/**
	 * Sin nadie esperando se dice en claro.
	 */
	public function test_la_lista_de_espera_vacia(): void {
		$bloque = new Block_Inscripcion();

		$html = $bloque->render_lista_espera( array( 'actividadId' => 77 ) );

		$this->assertStringContainsString( 'Sin lista de espera', $html );
	}

	/**
	 * Con gente esperando se cuenta, y se dice en singular o plural según toque.
	 */
	public function test_la_lista_de_espera_con_gente(): void {
		$bloque = new Block_Inscripcion();
		$fila   = static function ( int $total ): array {
			return array( (object) array( 'estado' => 'lista_espera', 'total' => $total ) );
		};

		$GLOBALS['_wp_stores']['results_mapa']['GROUP BY pm2.meta_value'] = $fila( 1 );
		$html = $bloque->render_lista_espera( array( 'actividadId' => 77 ) );
		$this->assertStringContainsString( '1 persona esperando plaza', $html, 'con una, en singular' );

		$GLOBALS['_wp_stores']['results_mapa']['GROUP BY pm2.meta_value'] = $fila( 4 );
		$html = $bloque->render_lista_espera( array( 'actividadId' => 77 ) );
		$this->assertStringContainsString( '4 personas esperando plaza', $html );
	}

	/**
	 * Los otros bloques delegan en su clase (y devuelven algo que se puede imprimir).
	 */
	public function test_los_bloques_que_delegan(): void {
		$bloque = new Block_Inscripcion();

		$this->assertIsString( $bloque->render_panel_reservas( array() ) );
		$this->assertIsString( $bloque->render_evaluacion( array() ) );
	}
}
