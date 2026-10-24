<?php
/**
 * Duplicar una actividad.
 *
 * Se usa cada trimestre: se duplica la del año pasado y se cambian las fechas. Lo que se prueba aquí es lo que no debe
 * heredarse tal cual —las fechas, que se desplazan una semana— y lo que sí (plazas, precio, lugar, responsables), y
 * que la copia nazca en **borrador** para que no aparezca publicada sin querer.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Actividades;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-actividades.php';

/**
 * Duplicado.
 */
final class AdminActividadesDuplicateTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Actividades
	 */
	private Admin_Actividades $pantalla;

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
		$GLOBALS['_convoca_redirects'] = array();
		$GLOBALS['nonce_invalido']     = false;
		$GLOBALS['convoca_test_caps']  = array( 'edit_posts' => true );
		$GLOBALS['convoca_test_tipos'] = array();
		$GLOBALS['_wp_posts_creados']  = array();
		$GLOBALS['_wp_logs']           = array();
		$_GET                          = array();

		$this->pantalla = new Admin_Actividades();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['_convoca_redirects'] = array();
		$GLOBALS['nonce_invalido']     = false;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['convoca_test_tipos'] = array();
		$GLOBALS['_wp_posts_creados']  = array();
		$GLOBALS['_wp_logs']           = array();
		$_GET                          = array();
	}

	/**
	 * Ejecuta el duplicado y devuelve con qué terminó.
	 *
	 * @return string
	 */
	private function duplicar(): string {
		try {
			$this->pantalla->handle_duplicate();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$r = $cortado->respuesta;

			return is_array( $r ) ? (string) ( $r['redirect'] ?? $r['die'] ?? '' ) : (string) $r;
		}

		return '';
	}

	/**
	 * Siembra la actividad original.
	 *
	 * @return void
	 */
	private function original(): void {
		$post                          = new \WP_Post();
		$post->ID                      = 61;
		$post->post_type               = 'actividad';
		$post->post_title              = 'Ruta de otoño';
		$post->post_content            = '<p>Salida al monte</p>';
		$post->post_excerpt            = 'Salida corta';
		$GLOBALS['_wp_stores']['posts'][61] = $post;
		$GLOBALS['convoca_test_tipos'][61]  = 'actividad';

		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'   => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'      => '2026-11-14 20:30:00',
			'_convoca_plazas_totales' => '30',
			'_convoca_precio_socio'   => '60,50',
			'_convoca_ubicacion'      => 'Centro Social',
			'_convoca_responsables'   => '7,9',
		);
	}

	/**
	 * La copia: título con «(copia)», en borrador, con las metas y las fechas una semana después.
	 */
	public function test_duplica_la_actividad(): void {
		$this->original();
		$_GET = array(
			'id'       => '61',
			'_wpnonce' => 'x',
		);

		$destino = $this->duplicar();

		$this->assertCount( 1, $GLOBALS['_wp_posts_creados'], 'se crea la copia' );
		$nuevo = $GLOBALS['_wp_posts_creados'][0];

		// Nace en borrador: duplicar no debe publicar la copia sin querer.
		$this->assertSame( 'draft', $GLOBALS['_wp_insert_args'][0]['post_status'] );
		$this->assertStringContainsString( '(copia)', $GLOBALS['_wp_insert_args'][0]['post_title'] );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $nuevo ];
		$this->assertSame( '30', $meta['_convoca_plazas_totales'], 'las plazas se copian' );
		$this->assertSame( '60,50', $meta['_convoca_precio_socio'] );
		$this->assertSame( 'Centro Social', $meta['_convoca_ubicacion'] );
		$this->assertSame( '7,9', $meta['_convoca_responsables'] );

		$this->assertStringContainsString( '2026-11-21', $meta['_convoca_fecha_inicio'], 'una semana después' );
		$this->assertStringContainsString( '2026-11-21', $meta['_convoca_fecha_fin'] );

		$this->assertStringContainsString( 'id=' . $nuevo, $destino, 'lleva al editor de la copia' );
		$this->assertNotEmpty( $GLOBALS['_wp_logs'], 'queda registrado' );
	}

	/**
	 * Sin id o con nonce caducado no se duplica nada.
	 */
	public function test_sin_nonce(): void {
		$_GET = array( '_wpnonce' => 'x' );

		$this->assertStringContainsString( 'Nonce inválido', $this->duplicar() );
		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'] );

		$GLOBALS['nonce_invalido'] = true;
		$_GET                      = array(
			'id'       => '61',
			'_wpnonce' => 'x',
		);
		$this->duplicar();
		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'] );
	}

	/**
	 * Sin permiso para editar entradas, tampoco.
	 */
	public function test_sin_permiso(): void {
		$GLOBALS['convoca_test_caps'] = array( 'edit_posts' => false );
		$_GET                         = array(
			'id'       => '61',
			'_wpnonce' => 'x',
		);

		$this->assertStringContainsString( 'No tienes permisos', $this->duplicar() );
		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'] );
	}

	/**
	 * Una entrada que no es una actividad no se duplica (aunque exista).
	 */
	public function test_no_duplica_otra_cosa(): void {
		$GLOBALS['convoca_test_tipos'][61] = 'post';
		$GLOBALS['_wp_stores']['posts'][61] = new \WP_Post();
		$GLOBALS['_wp_stores']['posts'][61]->ID = 61;
		$GLOBALS['_wp_stores']['posts'][61]->post_type = 'post';
		$_GET = array(
			'id'       => '61',
			'_wpnonce' => 'x',
		);

		$this->assertStringContainsString( 'no encontrada', $this->duplicar() );
		$this->assertSame( array(), $GLOBALS['_wp_posts_creados'] );
	}
}
