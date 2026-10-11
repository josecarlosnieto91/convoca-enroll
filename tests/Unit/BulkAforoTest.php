<?php
/**
 * ¿Cabe otra inscripción? — la comprobación de aforo de la acción masiva
 * «confirmar inscripciones por actividad».
 *
 * DEFECTO QUE FIJA ESTA PRUEBA (medido en la demo):
 *
 *     $aforo_actual = (int) get_post_meta( $actividad_id, '_convoca_plazas_ocupadas', true );
 *     $aforo_max    = (int) get_post_meta( $actividad_id, '_convoca_aforo', true );
 *     if ( $aforo_actual >= $aforo_max ) { saltar }
 *
 *  - `_convoca_plazas_ocupadas` no lo escribe NADIE en todo el ecosistema: solo se
 *    leía ahí, así que valía siempre 0.
 *  - `_convoca_aforo` no existe en ninguna instalación: en la base de la demo hay
 *    **0 filas** con esa clave (el CPT usa `_convoca_plazas_totales`).
 *
 * Con los dos ceros la condición daba `0 >= 0` → TRUE → la acción saltaba TODAS las
 * inscripciones por «falta de aforo» y no confirmaba ninguna.
 *
 * @package       Convoca\Enroll\Tests
 * @coversDefaultClass \Convoca\Enroll\Admin_Page
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

class BulkAforoTest extends TestCase {

	/** @var object Conserva el stub de $wpdb del bootstrap para restaurarlo. */
	private $wpdb_original;

	protected function setUp(): void {
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$this->wpdb_original                = $GLOBALS['wpdb'];
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->wpdb_original;
	}

	/**
	 * Stub de $wpdb que dice cuántas inscripciones confirmadas hay.
	 */
	private function wpdb_con_confirmadas( int $confirmadas ): void {
		$GLOBALS['wpdb'] = new class( $confirmadas ) {
			public $prefix = 'wp_';
			public $posts = 'wp_posts';
			public $postmeta = 'wp_postmeta';
			private int $confirmadas;
			public function __construct( int $c ) {
                $this->confirmadas = $c; }
			public function prepare( $q, ...$a ) {
				return $q; }
			public function get_results( $q = null, $o = 'OBJECT' ) {
				return $this->confirmadas > 0
					? array( (object) array( 'estado' => 'confirmada', 'total' => (string) $this->confirmadas ) )
					: array(); }
		};
	}

	public function test_sin_aforo_configurado_si_cabe(): void {
		// El defecto: aquí daba 0>=0 y saltaba la inscripción. Una actividad sin
		// aforo no tiene tope, así que debe poder confirmarse.
		$GLOBALS['_wp_stores']['post_meta'][900] = array();
		$this->wpdb_con_confirmadas( 0 );
		$this->assertTrue(
			Admin_Page::hay_aforo( 900 ),
			'Sin aforo configurado no hay tope: no se puede saltar por capacidad'
		);
	}

	public function test_con_plazas_totales_y_hueco_si_cabe(): void {
		// La clave que usa de verdad el CPT de actividades.
		$GLOBALS['_wp_stores']['post_meta'][901] = array( '_convoca_plazas_totales' => '25' );
		$this->wpdb_con_confirmadas( 0 );
		$this->assertTrue( Admin_Page::hay_aforo( 901 ) );
	}

	public function test_con_el_aforo_lleno_no_cabe(): void {
		$GLOBALS['_wp_stores']['post_meta'][902] = array( '_convoca_plazas_totales' => '25' );
		$this->wpdb_con_confirmadas( 25 );
		$this->assertFalse( Admin_Page::hay_aforo( 902 ), 'Con 25 de 25 no cabe otra' );
	}

	public function test_con_una_plaza_libre_si_cabe(): void {
		$GLOBALS['_wp_stores']['post_meta'][903] = array( '_convoca_plazas_totales' => '25' );
		$this->wpdb_con_confirmadas( 24 );
		$this->assertTrue( Admin_Page::hay_aforo( 903 ), 'Queda una plaza' );
	}

	public function test_la_ocupacion_se_cuenta_no_se_lee_del_meta_muerto(): void {
		// `_convoca_plazas_ocupadas` a 0 (como está en la demo) NO debe hacer creer
		// que la actividad está vacía si de verdad hay 25 confirmadas.
		$GLOBALS['_wp_stores']['post_meta'][904] = array(
			'_convoca_plazas_totales'  => '25',
			'_convoca_plazas_ocupadas' => '0',
		);
		$this->wpdb_con_confirmadas( 25 );
		$this->assertFalse( Admin_Page::hay_aforo( 904 ) );
	}

	public function test_convoca_aforo_sigue_valiendo_como_compatibilidad(): void {
		$GLOBALS['_wp_stores']['post_meta'][905] = array( '_convoca_aforo' => '10' );
		$this->wpdb_con_confirmadas( 3 );
		$this->assertTrue( Admin_Page::hay_aforo( 905 ) );
	}

	/* ── El otro defecto de la misma acción: los IDs seleccionados ────────────── */

	public function test_lee_los_ids_que_emite_la_tabla(): void {
		// `Inscriptions_List::column_cb` emite name="ids[]". El manejador leía
		// $_GET['inscripcion'], así que nunca llegaba nada y la acción no hacía nada.
		$this->assertSame(
			array( 1760, 1759 ),
			Admin_Page::ids_de_la_peticion( array( 'ids' => array( '1760', '1759' ) ) )
		);
	}

	public function test_sigue_aceptando_el_nombre_antiguo(): void {
		$this->assertSame(
			array( 42 ),
			Admin_Page::ids_de_la_peticion( array( 'inscripcion' => array( '42' ) ) )
		);
	}

	public function test_sin_seleccion_no_hay_ids(): void {
		$this->assertSame( array(), Admin_Page::ids_de_la_peticion( array() ) );
		$this->assertSame( array(), Admin_Page::ids_de_la_peticion( array( 'action' => 'confirmar_plazas' ) ) );
	}

	public function test_descarta_valores_no_numericos_ceros_y_duplicados(): void {
		$this->assertSame(
			array( 3 ),
			Admin_Page::ids_de_la_peticion( array( 'ids' => array( '0', 'abc', '3', '3', '-1' ) ) )
		);
	}
}
