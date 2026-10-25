<?php
/**
 * El meta box de evaluación del editor: qué guarda y qué no.
 *
 * `admin/class-admin-evaluacion-fields.php` estaba al 0 % (76 statements). Aquí apareció un defecto real
 * (enroll#46): el formulario emitía `conv_eval_*` y el guardado buscaba `convoca_eval_*`, así que NO guardaba ni
 * la actividad ni las puntuaciones. Estas pruebas fijan las guardas (nonce, autoguardado, permiso) y que el
 * camino bueno sí persiste, con los nombres que el formulario emite de verdad.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Evaluacion_Fields;
use PHPUnit\Framework\TestCase;

class EvaluacionCamposTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array(), 'post_meta' => array(), 'posts' => array() );
		$GLOBALS['_convoca_cajas']    = array();
		$GLOBALS['_convoca_acciones'] = array();
		$GLOBALS['convoca_test_caps'] = array();
		$GLOBALS['nonce_invalido']    = false;
		$GLOBALS['convoca_test_posts'] = array();
		$_POST                        = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array() );
		$GLOBALS['convoca_test_caps'] = array();
		unset( $GLOBALS['_convoca_cajas'], $GLOBALS['_convoca_acciones'], $GLOBALS['convoca_test_posts'] );
		$GLOBALS['nonce_invalido'] = false;
		$_POST                     = array();
	}

	/**
	 * Devuelve el metadato guardado por la prueba.
	 *
	 * @param int    $post_id Evaluación.
	 * @param string $clave   Clave del metadato.
	 * @return mixed
	 */
	private function meta( int $post_id, string $clave ) {
		return $GLOBALS['_wp_stores']['post_meta'][ $post_id ][ $clave ] ?? null;
	}

	/**
	 * Pinta el meta box y devuelve su HTML.
	 *
	 * @param int $post_id Evaluación.
	 * @return string
	 */
	private function pintar( int $post_id ): string {
		ob_start();
		Admin_Evaluacion_Fields::render_meta_box( (object) array( 'ID' => $post_id ) );

		return (string) ob_get_clean();
	}

	/**
	 * El meta box se engancha a la caja del editor y al guardado de ese tipo de contenido.
	 */
	public function test_los_enganches(): void {
		Admin_Evaluacion_Fields::init();

		$acciones = array_merge( (array) ( $GLOBALS['_wp_stores']['acciones'] ?? array() ), (array) ( $GLOBALS['_convoca_acciones'] ?? array() ) );
		$this->assertNotEmpty( $acciones['add_meta_boxes'] ?? array(), 'sin esto no se pinta la caja' );
		$this->assertNotEmpty( $acciones['save_post_conv_evaluacion'] ?? array(), 'sin esto no se guarda al guardar' );
	}

	/**
	 * La caja se registra en el editor de evaluaciones.
	 */
	public function test_la_caja_registrada(): void {
		Admin_Evaluacion_Fields::add_meta_boxes();

		$cajas = (array) ( $GLOBALS['_convoca_cajas'] ?? array() );
		$this->assertNotEmpty( $cajas );

		$caja = (array) $cajas[0];
		$this->assertSame( 'convoca_evaluacion', $caja['pantalla'], 'la caja es del editor de evaluaciones' );
		$this->assertSame( 'normal', $caja['contexto'] );
	}

	/**
	 * El formulario lleva nonce, la actividad elegida y las cuatro puntuaciones.
	 */
	public function test_lo_que_pinta_el_formulario(): void {
		$GLOBALS['_wp_stores']['post_meta'][40] = array(
			'_convoca_eval_actividad_id' => 77,
			'_convoca_eval_gestion'      => 4,
		);
		$GLOBALS['convoca_test_posts'] = array( (object) array( 'ID' => 77, 'post_title' => 'Taller de setas' ) );

		$html = $this->pintar( 40 );

		$this->assertStringContainsString( 'convoca_evaluacion_fields_nonce', $html, 'sin nonce no se puede guardar' );
		$this->assertStringContainsString( 'name="conv_eval_actividad_id"', $html );
		$this->assertStringContainsString( 'Taller de setas', $html );
		$this->assertStringContainsString( "checked='checked'", $html, 'la puntuación guardada se ve marcada' );

		foreach ( array( 'gestion', 'instalaciones', 'participantes', 'comunicacion' ) as $criterio ) {
			$this->assertStringContainsString( 'name="conv_eval_' . $criteria_placeholder = $criterio . '"', $html, "falta el criterio {$criterio}" );
		}
	}

	/**
	 * Sin nonce no se guarda nada.
	 */
	public function test_sin_nonce_no_guarda(): void {
		Admin_Evaluacion_Fields::save_meta_box( 40 );

		$this->assertNull( $this->meta( 40, '_convoca_eval_gestion' ) );
	}

	/**
	 * Con el nonce caducado tampoco.
	 */
	public function test_con_nonce_caducado_no_guarda(): void {
		$_POST['convoca_evaluacion_fields_nonce'] = 'caducado';
		$_POST['conv_eval_gestion']               = '5';
		$GLOBALS['nonce_invalido']                = true;

		Admin_Evaluacion_Fields::save_meta_box( 40 );

		$this->assertNull( $this->meta( 40, '_convoca_eval_gestion' ) );
	}

	/**
	 * Sin permiso sobre esa evaluación no se guarda.
	 */
	public function test_sin_permiso_no_guarda(): void {
		$_POST['convoca_evaluacion_fields_nonce'] = 'vale';
		$_POST['conv_eval_gestion']               = '5';
		$GLOBALS['convoca_test_caps']['edit_post'] = false;

		Admin_Evaluacion_Fields::save_meta_box( 40 );

		$this->assertNull( $this->meta( 40, '_convoca_eval_gestion' ) );
	}

	/**
	 * Con el nonce bueno y permiso, se guardan los CINCO campos del formulario (el defecto de enroll#46).
	 */
	public function test_guarda_los_campos_que_emite_el_formulario(): void {
		$_POST = array(
			'convoca_evaluacion_fields_nonce' => 'vale',
			'conv_eval_actividad_id'          => '77',
			'conv_eval_gestion'               => '4',
			'conv_eval_instalaciones'         => '5',
			'conv_eval_participantes'         => '3',
			'conv_eval_comunicacion'          => '2',
		);

		Admin_Evaluacion_Fields::save_meta_box( 40 );

		$this->assertSame( '77', $this->meta( 40, '_convoca_eval_actividad_id' ), 'la actividad se guarda' );
		$this->assertSame( '4', $this->meta( 40, '_convoca_eval_gestion' ) );
		$this->assertSame( '5', $this->meta( 40, '_convoca_eval_instalaciones' ) );
		$this->assertSame( '3', $this->meta( 40, '_convoca_eval_participantes' ) );
		$this->assertSame( '2', $this->meta( 40, '_convoca_eval_comunicacion' ) );
	}

	/**
	 * Los valores que llegan se sanean antes de guardarse.
	 */
	public function test_los_valores_se_sanan(): void {
		$_POST = array(
			'convoca_evaluacion_fields_nonce' => 'vale',
			'conv_eval_actividad_id'          => ' 77<script>alert(1)</script> ',
		);

		Admin_Evaluacion_Fields::save_meta_box( 40 );

		$guardado = (string) $this->meta( 40, '_convoca_eval_actividad_id' );
		$this->assertStringNotContainsString( '<script>', $guardado );
	}

	/**
	 * La primera vez se apunta quién evalúa y cuándo, y NO se pisa lo que ya estaba.
	 */
	public function test_el_autor_y_la_fecha(): void {
		$_POST = array( 'convoca_evaluacion_fields_nonce' => 'vale' );

		Admin_Evaluacion_Fields::save_meta_box( 40 );

		$this->assertSame( '1', $this->meta( 40, '_convoca_eval_usuario_id' ), 'se apunta quién evalúa' );
		$this->assertNotEmpty( $this->meta( 40, '_convoca_eval_fecha' ), 'y cuándo' );

		// Ya estaban puestos: no se tocan.
		$GLOBALS['_wp_stores']['post_meta'][41] = array(
			'_convoca_eval_usuario_id' => 9,
			'_convoca_eval_fecha'      => '2026-01-01 00:00:00',
		);
		Admin_Evaluacion_Fields::save_meta_box( 41 );

		$this->assertSame( 9, $this->meta( 41, '_convoca_eval_usuario_id' ), 'no se cambia el autor original' );
		$this->assertSame( '2026-01-01 00:00:00', $this->meta( 41, '_convoca_eval_fecha' ) );
	}
}
