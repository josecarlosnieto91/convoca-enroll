<?php
/**
 * Permisos de los monitores y columnas del listado de actividades.
 *
 * Un monitor solo puede tocar **sus** actividades: ni las de otro, ni borrar. Y el listado del admin tiene que decir
 * de un vistazo cuándo es, quién la lleva, dónde y cuánto se ha llenado (con su color cuando está a punto de llenarse).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Permisos y columnas.
 */
final class ActividadPermisosTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * CPT.
	 *
	 * @var CPT_Actividad
	 */
	private CPT_Actividad $cpt;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_usuario'] = null;
		$this->cpt                       = new CPT_Actividad();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']           = $this->previo;
		$GLOBALS['convoca_test_usuario'] = null;
		$GLOBALS['convoca_test_roles']   = array();
		$GLOBALS['convoca_test_tipos']   = array();
	}

	/**
	 * Usuario de prueba con sus roles.
	 *
	 * @param int      $id    Id.
	 * @param string[] $roles Roles.
	 * @return void
	 */
	private function usuario( int $id, array $roles ): void {
		// El arnés de este repo guarda los roles en `convoca_test_roles` y los tipos en `convoca_test_tipos`.
		$GLOBALS['convoca_test_roles'][ $id ] = $roles;
		$GLOBALS['convoca_test_tipos'][61]    = 'actividad';

		$u               = new \WP_User();
		$u->ID           = $id;
		$u->display_name = 'User ' . $id;
		$u->roles        = $roles;
		$GLOBALS['convoca_test_usuario'] = $u;
	}

	/**
	 * Un monitor asignado puede editar y leer su actividad, pero **no** borrarla.
	 */
	public function test_monitor_asignado(): void {
		$this->usuario( 7, array( 'monitor_actividad' ) );
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_responsables' => '5,7,9' );

		$args = array( 61 );

		$this->assertSame( array( 'manage_inscripciones' ), $this->cpt->map_monitor_caps( array( 'x' ), 'edit_post', 7, $args ) );
		$this->assertSame( array( 'manage_inscripciones' ), $this->cpt->map_monitor_caps( array( 'x' ), 'read_post', 7, $args ) );
		$this->assertSame( array( 'do_not_allow' ), $this->cpt->map_monitor_caps( array( 'x' ), 'delete_post', 7, $args ), 'no puede borrar' );
	}

	/**
	 * Un monitor **no** asignado a esa actividad se queda como estaba.
	 */
	public function test_monitor_no_asignado(): void {
		$this->usuario( 7, array( 'monitor_actividad' ) );
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_responsables' => '5,9' );

		$this->assertSame( array( 'original' ), $this->cpt->map_monitor_caps( array( 'original' ), 'edit_post', 7, array( 61 ) ) );
	}

	/**
	 * Un usuario que no es monitor no entra por esta vía.
	 */
	public function test_otro_rol_no_se_toca(): void {
		$this->usuario( 7, array( 'subscriber' ) );
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_responsables' => '7' );

		$this->assertSame( array( 'original' ), $this->cpt->map_monitor_caps( array( 'original' ), 'edit_post', 7, array( 61 ) ) );
	}

	/**
	 * Ni se toca lo que no es una actividad, ni una capacidad que no sea de entrada.
	 */
	public function test_fuera_de_su_alcance(): void {
		$this->usuario( 7, array( 'monitor_actividad' ) );

		$this->assertSame( array( 'original' ), $this->cpt->map_monitor_caps( array( 'original' ), 'edit_posts', 7, array( 61 ) ), 'otra capacidad' );
		$this->assertSame( array( 'original' ), $this->cpt->map_monitor_caps( array( 'original' ), 'edit_post', 7, array() ), 'sin id' );
	}

	/**
	 * Las columnas: la de fecha se sustituye por tres columnas propias (fecha, responsables, ubicación).
	 */
	public function test_las_columnas(): void {
		$columnas = $this->cpt->list_columns(
			array(
				'cb'     => '<input type="checkbox">',
				'title'  => 'Título',
				'date'   => 'Fecha',
				'author' => 'Autor',
			)
		);

		// El producto **añade** sus columnas antes de la de fecha; no la quita.
		$this->assertArrayHasKey( 'date', $columnas, 'la fecha genérica sigue' );
		$this->assertArrayHasKey( 'fecha', $columnas );
		$this->assertArrayHasKey( 'responsables', $columnas );
		$this->assertArrayHasKey( 'ubicacion', $columnas );
		$this->assertArrayHasKey( 'plazas', $columnas );
		$this->assertArrayHasKey( 'title', $columnas, 'el título sigue' );
		$this->assertSame( 'fecha_inicio', $this->cpt->list_sortable_columns( array() )['fecha'] );
	}

	/**
	 * La columna de fecha: con hora de inicio y de fin; sin fecha, un guion.
	 */
	public function test_columna_fecha(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'    => '2026-11-14 20:30:00',
		);

		ob_start();
		$this->cpt->list_custom_column( 'fecha', 61 );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '14/11/2026 18:00', $html );
		$this->assertStringContainsString( '14/11/2026 20:30', $html, 'la fin también' );

		ob_start();
		$this->cpt->list_custom_column( 'fecha', 99 );
		$this->assertSame( '—', trim( (string) ob_get_clean() ), 'sin fecha, un guion' );
	}

	/**
	 * La columna de responsables: los nombres, no los ids.
	 */
	public function test_columna_responsables(): void {
		$this->usuario( 7, array( 'monitor_actividad' ) );
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_responsables' => '7' );

		ob_start();
		$this->cpt->list_custom_column( 'responsables', 61 );
		$this->assertStringContainsString( 'User 7', (string) ob_get_clean(), 'el nombre, no el id' );

		ob_start();
		$this->cpt->list_custom_column( 'responsables', 99 );
		$this->assertSame( '—', trim( (string) ob_get_clean() ) );
	}

	/**
	 * La columna de ocupación: cuántas de cuántas y el color según lo llena que esté.
	 */
	public function test_columna_ocupacion(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_plazas_totales'     => '10',
			'_convoca_plazas_disponibles' => '10',
		);

		ob_start();
		$this->cpt->list_custom_column( 'plazas', 61 );
		$vacia = (string) ob_get_clean();
		$this->assertStringContainsString( '0/10', $vacia );
		$this->assertStringContainsString( '#2d5a27', $vacia, 'verde cuando hay sitio' );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_plazas_disponibles'] = '1';
		ob_start();
		$this->cpt->list_custom_column( 'plazas', 61 );
		$llena = (string) ob_get_clean();
		$this->assertStringContainsString( '9/10', $llena );
		$this->assertStringContainsString( '#ff500a', $llena, 'rojo cuando está a tope' );

		ob_start();
		$this->cpt->list_custom_column( 'plazas', 99 );
		$this->assertSame( '—', trim( (string) ob_get_clean() ), 'sin plazas, un guion' );
	}

	/**
	 * La columna de ubicación.
	 */
	public function test_columna_ubicacion(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_ubicacion' => 'Centro Social' );

		ob_start();
		$this->cpt->list_custom_column( 'ubicacion', 61 );
		$this->assertStringContainsString( 'Centro Social', (string) ob_get_clean() );
	}
}
