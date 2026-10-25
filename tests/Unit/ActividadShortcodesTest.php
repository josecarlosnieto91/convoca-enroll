<?php
/**
 * Shortcodes y consultas de actividades (código vivo del día a día).
 *
 * `[convoca_actividad_meta field="..."]` es lo que usan las plantillas para pintar un dato suelto de la actividad
 * (precio, plazas, fecha) sin montar un bloque; `get_upcoming()` alimenta los listados de «próximas actividades».
 * Un precio en formato inglés o una fecha vacía se ven en la web pública.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Shortcodes.
 */
final class ActividadShortcodesTest extends TestCase {

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
		$GLOBALS['convoca_test_tipos'][61]  = 'actividad';
		$GLOBALS['_wp_current_id']          = 61;
		$GLOBALS['convoca_test_query']['es_singular'] = true;
		$GLOBALS['convoca_test_query']['queried_id']  = 61;
		$GLOBALS['convoca_test_get_posts_args']       = array();

		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_ubicacion'        => 'Centro Social',
			'_convoca_precio_socio'     => '60.5',
			'_convoca_plazas_totales'   => '30',
			'_convoca_plazas_disponibles' => '8',
			'_convoca_fecha_inicio'     => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'        => '2026-11-14 20:30:00',
			'_convoca_funciones'        => 'Abrir <b>y</b> cerrar',
		);

		$this->cpt = new CPT_Actividad();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_tipos']          = array();
		$GLOBALS['_wp_current_id']              = 0;
		$GLOBALS['convoca_test_query']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_caps']           = array();
		unset( $GLOBALS['convoca_test_posts'] );
	}

	/**
	 * Los campos de precio salen formateados.
	 *
	 * Antes solo se formateaba `precio`, que **no es ninguna meta** del plugin: las de verdad son `precio_socio`,
	 * `precio_socio_dia` y `precio_general`. Así que la plantilla pedía `field="precio_socio"` y recibía `60.5`
	 * crudo, sin coma ni euro (enroll#37).
	 */
	public function test_los_precios_salen_formateados(): void {
		$this->assertSame( '60,50 €', $this->cpt->shortcode_actividad_meta( array( 'field' => 'precio_socio' ) ) );

	}

	/**
	 * El campo que sí formatea (`precio`) no existe en la base: no hay nada que formatear.
	 */
	public function test_precio_inexistente_vacio(): void {
		$this->assertSame( '', $this->cpt->shortcode_actividad_meta( array( 'field' => 'precio' ) ) );
	}

	/**
	 * Plazas: disponibles sobre el total.
	 */
	public function test_plazas(): void {
		$this->assertSame( '8 / 30', $this->cpt->shortcode_actividad_meta( array( 'field' => 'plazas_disponibles' ) ) );
	}

	/**
	 * Fecha: en formato legible, no la cadena de la base.
	 */
	public function test_fecha(): void {
		$fecha = $this->cpt->shortcode_actividad_meta( array( 'field' => 'fecha_inicio' ) );

		$this->assertStringContainsString( '2026', $fecha );
		$this->assertStringNotContainsString( '18:00', $fecha, 'sin la hora' );
	}

	/**
	 * Un campo de texto se escapa (no se cuela HTML de la ficha).
	 */
	public function test_texto_escapado(): void {
		$this->assertStringNotContainsString( '<b>', $this->cpt->shortcode_actividad_meta( array( 'field' => 'funciones' ) ) );
	}

	/**
	 * Sin campo, la ubicación; y si no hay valor, cadena vacía (no «Array» ni un aviso).
	 */
	public function test_por_omision_ubicacion(): void {
		$this->assertSame( 'Centro Social', $this->cpt->shortcode_actividad_meta( array() ) );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_ubicacion'] = '';
		$this->assertSame( '', $this->cpt->shortcode_actividad_meta( array() ) );
	}

	/**
	 * Fuera de una actividad no inventa nada.
	 */
	public function test_fuera_de_una_actividad(): void {
		$GLOBALS['convoca_test_tipos'][61] = 'post';
		$this->assertSame( '', $this->cpt->shortcode_actividad_meta( array() ) );

		$GLOBALS['convoca_test_tipos'][61] = 'actividad';
		$GLOBALS['_wp_current_id']         = 0;
		$GLOBALS['convoca_test_query']['queried_id'] = 0;
		$this->assertSame( '', $this->cpt->shortcode_actividad_meta( array() ) );
	}

	/**
	 * Las próximas actividades: publicadas, con fecha, en orden y solo las que aún no han pasado.
	 */
	public function test_las_proximas(): void {
		$proxima             = new \WP_Post();
		$proxima->ID         = 61;
		$proxima->post_type  = 'actividad';
		$GLOBALS['convoca_test_posts'] = array( $proxima );
		// `get_upcoming()` filtra por `_convoca_fecha_inicio >= ahora`: sin la meta, la actividad no está en la
		// lista para el doble estricto (enroll#44).
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_fecha_inicio'] = wp_date( 'Y-m-d H:i', strtotime( '+3 days' ) );

		$lista = CPT_Actividad::get_upcoming( 5 );

		$this->assertCount( 1, $lista );
		$args = end( $GLOBALS['convoca_test_get_posts_args'] );
		$this->assertSame( 'actividad', $args['post_type'] );
		$this->assertSame( 5, $args['posts_per_page'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( 'ASC', $args['order'], 'de la más próxima a la más lejana' );
		$this->assertSame( '>=', $args['meta_query'][0]['compare'], 'solo las que no han pasado' );
	}

	/**
	 * El shortcode de inscripción, fuera de una actividad: al admin se le explica; al visitante, nada.
	 */
	public function test_inscripcion_fuera_de_actividad(): void {
		$GLOBALS['convoca_test_query']['es_singular'] = false;

		$GLOBALS['convoca_test_caps'] = array( 'manage_options' => true );
		$this->assertStringContainsString( 'debe usarse en la página de una actividad', $this->cpt->shortcode_inscripcion_actual() );

		$GLOBALS['convoca_test_caps'] = array( 'manage_options' => false );
		$this->assertSame( '', $this->cpt->shortcode_inscripcion_actual() );
	}
}
