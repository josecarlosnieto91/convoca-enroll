<?php
/**
 * Detalle de una inscripción.
 *
 * Es la ficha que se abre para atender a una persona: sus datos, la actividad, su consentimiento, sus notas y los
 * botones para cambiar el estado, marcar el pago o reenviar el correo. Se prueba que se pinte lo que hay (y un guion
 * donde falta) y que el estado actual salga **seleccionado**: guardar desde aquí sin verlo primero cambia la
 * inscripción de la persona.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';

/**
 * Detalle.
 */
final class AdminPageDetalleTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Page
	 */
	private Admin_Page $pagina;

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
		$GLOBALS['convoca_test_tipos'] = array();
		$_GET                          = array();

		$this->pagina = new Admin_Page();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['convoca_test_tipos'] = array();
		$_GET                          = array();
	}

	/**
	 * Pinta el detalle de una inscripción.
	 *
	 * @param int $id Id.
	 * @return string
	 */
	private function detalle( int $id ): string {
		$metodo = new \ReflectionMethod( Admin_Page::class, 'render_detail' );
		ob_start();
		try {
			$metodo->invoke( $this->pagina, $id );
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Siembra la actividad.
	 *
	 * @return void
	 */
	private function actividad(): void {
		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['convoca_test_tipos'][61]  = 'actividad';
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => '2026-11-14 18:00:00',
			'_convoca_ubicacion'    => 'Centro Social',
		);
	}

	/**
	 * Siembra la inscripción.
	 *
	 * @param array $meta Metas extra (sin prefijo).
	 * @return void
	 */
	private function inscripcion( array $meta = array() ): void {
		$post                        = new \WP_Post();
		$post->ID                    = 70;
		$post->post_type             = 'inscripcion';
		$post->post_title            = 'Ana López';
		$post->post_date             = '2026-11-01 09:30:00';
		$GLOBALS['_wp_stores']['posts'][70] = $post;
		$GLOBALS['convoca_test_tipos'][70]  = 'inscripcion';

		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_nombre'    => 'Ana',
			'_convoca_email'     => 'ana@example.test',
			'_convoca_telefono'  => '600000000',
			'_convoca_es_socio'  => '1',
			'_convoca_estado'    => 'confirmada',
			'_convoca_actividad_id' => 61,
			'_convoca_consentimiento_version'   => '1.2',
			'_convoca_consentimiento_timestamp' => '2026-11-01 09:30:00',
			'_convoca_notas'     => 'Recoge su madre',
		);
		foreach ( $meta as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][70][ '_convoca_' . $clave ] = $valor;
		}
	}

	/**
	 * Una inscripción que no existe (o que no es una inscripción) lo dice.
	 */
	public function test_inscripcion_inexistente(): void {
		$this->assertStringContainsString( 'no encontrada', $this->detalle( 999 ) );

		$this->actividad();
		$this->assertStringContainsString( 'no encontrada', $this->detalle( 61 ), 'una actividad tampoco' );
	}

	/**
	 * La ficha: datos de la persona, actividad, consentimiento y notas.
	 */
	public function test_la_ficha(): void {
		$this->actividad();
		$this->inscripcion();

		$html = $this->detalle( 70 );

		$this->assertStringContainsString( 'Ana', $html );
		$this->assertStringContainsString( 'ana@example.test', $html );
		$this->assertStringContainsString( '600000000', $html );
		$this->assertStringContainsString( 'Sí', $html, 'es socio' );
		$this->assertStringContainsString( 'Ruta de otoño', $html );
		$this->assertStringContainsString( 'Centro Social', $html );
		$this->assertStringContainsString( '1.2', $html, 'la versión del consentimiento' );
		$this->assertStringContainsString( 'Recoge su madre', $html, 'su nota' );
	}

	/**
	 * El estado actual sale **seleccionado**: si no, guardar la ficha cambia el estado de la persona.
	 */
	public function test_el_estado_actual(): void {
		$this->actividad();
		$this->inscripcion();

		$html = $this->detalle( 70 );

		$this->assertMatchesRegularExpression(
			'/<option value="confirmada"\s+selected/',
			$html,
			'el estado actual va seleccionado'
		);
	}

	/**
	 * El pago: marcado cuando lo está, y sin marcar cuando no.
	 */
	public function test_el_pago(): void {
		$this->actividad();
		$this->inscripcion();

		$this->assertStringNotContainsString( 'checked', $this->detalle( 70 ), 'sin pagar' );

		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_pagado'] = '1';
		$this->assertStringContainsString( 'checked', $this->detalle( 70 ), 'pagado' );
	}

	/**
	 * Sin actividad asociada, un guion en vez de una celda vacía.
	 */
	public function test_sin_actividad(): void {
		$this->inscripcion( array( 'actividad_id' => 0 ) );
		$GLOBALS['convoca_test_tipos'][70] = 'inscripcion';

		$this->assertStringContainsString( '—', $this->detalle( 70 ) );
	}

	/**
	 * No socio se dice «No», no se deja en blanco.
	 */
	public function test_no_socio(): void {
		$this->actividad();
		$this->inscripcion( array( 'es_socio' => '0' ) );

		$this->assertStringContainsString( 'No', $this->detalle( 70 ) );
	}
}
