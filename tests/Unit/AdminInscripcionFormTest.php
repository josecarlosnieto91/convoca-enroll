<?php
/**
 * Formulario de alta de inscripción desde el panel.
 *
 * Es la vía por la que el equipo apunta a alguien que llama por teléfono o se presenta en la puerta. Se mide que el
 * formulario se pinte con las actividades que esa persona puede tocar y, sobre todo, el envío: quién puede usarlo, qué
 * datos son obligatorios y que un error del motor se enseñe tal cual en vez de un «error» pelado.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Inscripcion_Form;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-inscripcion-form.php';

/**
 * Formulario.
 */
final class AdminInscripcionFormTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Formulario.
	 *
	 * @var Admin_Inscripcion_Form
	 */
	private Admin_Inscripcion_Form $form;

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
			'queries'    => array(),
		);
		$GLOBALS['convoca_test_caps']     = array(
			'manage_inscripciones' => true,
			'manage_options'       => true,
		);
		$GLOBALS['convoca_test_posts']    = array();
		$GLOBALS['convoca_test_dns']      = array( 'example.com' );
		$GLOBALS['_wp_json']              = array();
		$GLOBALS['nonces_invalidos']      = array();
		$GLOBALS['convoca_test_user_can'] = array();
		$GLOBALS['db_inserts']            = array();
		$GLOBALS['_convoca_acciones']     = array();
		$_POST                            = array();

		$this->form = new Admin_Inscripcion_Form();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['convoca_test_dns']   = null;
		$GLOBALS['_wp_json']           = array();
		$GLOBALS['nonces_invalidos']   = array();
		$GLOBALS['db_inserts']         = array();
		$_POST                         = array();
	}

	/**
	 * Siembra una actividad abierta.
	 *
	 * @return void
	 */
	private function actividad(): void {
		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => gmdate( 'Y-m-d H:i:s', time() + 604800 ),
			'_convoca_plazas_disponibles' => 10,
		);
	}

	/**
	 * Lanza el envío y devuelve la respuesta.
	 *
	 * @return array
	 */
	private function enviar(): array {
		try {
			$this->form->handle_submit();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			return (array) end( $GLOBALS['_wp_json'] );
		}

		return (array) end( $GLOBALS['_wp_json'] );
	}

	/**
	 * Se engancha al ajax del panel.
	 */
	public function test_el_hook(): void {
		$this->assertArrayHasKey( 'wp_ajax_convoca_admin_inscribir', $GLOBALS['_convoca_acciones'] );
	}

	/**
	 * El formulario se pinta con la actividad y sus campos.
	 */
	public function test_el_formulario(): void {
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] ?? null );
		$this->actividad();
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] );

		ob_start();
		Admin_Inscripcion_Form::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Ruta de otoño', $html, 'la actividad aparece en el selector' );
		$this->assertStringContainsString( 'name="nombre"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'nonce', $html );
	}

	/**
	 * Sin permiso no se crea nada.
	 */
	public function test_sin_permiso(): void {
		$GLOBALS['convoca_test_caps']['manage_inscripciones'] = false;
		$_POST = array( 'actividad_id' => 61, 'nombre' => 'Ana', 'email' => 'ana@example.com' );

		$r = $this->enviar();

		$this->assertFalse( $r['ok'] );
		$this->assertSame( array(), $GLOBALS['db_inserts'] ?? array(), 'no se ha escrito nada' );
	}

	/**
	 * Un monitor solo puede apuntar en sus actividades.
	 */
	public function test_monitor_de_otra_actividad(): void {
		$this->actividad();
		$GLOBALS['convoca_test_caps']['manage_options'] = false;
		// `user_can()` del arnés concede por defecto (deuda conocida): aquí hay que negarlo explícitamente.
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$_POST = array( 'actividad_id' => 61, 'nombre' => 'Ana', 'email' => 'ana@example.com', 'rgpd' => '1' );

		$r = $this->enviar();

		$this->assertFalse( $r['ok'] );
		$this->assertSame( 'No eres responsable de esta actividad.', $r['data'] );
	}

	/**
	 * Los cuatro campos obligatorios se dicen todos a la vez, no de uno en uno.
	 */
	public function test_los_datos_obligatorios(): void {
		$_POST = array( 'actividad_id' => 0, 'nombre' => '', 'email' => 'no-es-correo', 'estado' => 'inventado' );

		$r = $this->enviar();

		$this->assertFalse( $r['ok'] );
		$errores = $r['data']['errors'];
		$this->assertCount( 5, $errores, 'se listan los cinco' );
		$this->assertStringContainsString( 'actividad', $errores[0] );
	}

	/**
	 * Un correo que ya tiene inscripción activa en esa actividad no se duplica.
	 */
	public function test_ya_inscrito(): void {
		$this->actividad();
		$existente                     = new \WP_Post();
		$existente->ID                 = 70;
		$existente->post_type          = 'inscripcion';
		$GLOBALS['convoca_test_posts'] = array( $existente );

		$_POST = array( 'actividad_id' => 61, 'nombre' => 'Ana', 'email' => 'ana@example.com', 'rgpd' => '1' );

		$r = $this->enviar();

		$this->assertFalse( $r['ok'] );
		$this->assertStringContainsString( 'ya tiene una inscripción activa', $r['data']['errors'][0] );
	}

	/**
	 * Alta correcta: se crea la inscripción y se devuelve el enlace a su ficha.
	 */
	public function test_alta_correcta(): void {
		$this->actividad();
		$_POST = array(
			'actividad_id' => 61,
			'nombre'       => 'Ana',
			'email'        => 'ana@example.com',
			'telefono'     => '600000000',
			'estado'       => 'confirmada',
			'rgpd'         => '1',
		);

		$r = $this->enviar();

		$this->assertTrue( $r['ok'] );
		$this->assertNotEmpty( $r['data']['inscripcion_id'] );
		$this->assertStringContainsString( 'inscripcion_id=', $r['data']['detail_url'] );
	}

	/**
	 * Si se marca pagado, se guarda.
	 */
	public function test_marcar_pagado(): void {
		$this->actividad();
		$_POST = array(
			'actividad_id' => 61,
			'nombre'       => 'Ana',
			'email'        => 'ana@example.com',
			'estado'       => 'confirmada',
			'pagado'       => '1',
			'rgpd'         => '1',
		);

		$r = $this->enviar();
		$id = $r['data']['inscripcion_id'];

		$this->assertSame( '1', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_pagado'] );
	}

	/**
	 * Sin consentimiento no hay alta: el motor lo exige y el formulario lo pide.
	 */
	public function test_sin_consentimiento(): void {
		$this->actividad();
		$_POST = array(
			'actividad_id' => 61,
			'nombre'       => 'Ana',
			'email'        => 'ana@example.com',
			'estado'       => 'confirmada',
		);

		$r = $this->enviar();

		$this->assertFalse( $r['ok'] );
		$this->assertStringContainsString( 'consentimiento', implode( ' ', (array) $r['data']['errors'] ) );
	}

	/**
	 * Un error del motor se enseña con su mensaje, no como «error» genérico.
	 */
	public function test_el_error_del_motor_se_explica(): void {
		$this->actividad();
		$_POST = array(
			'actividad_id' => 61,
			'nombre'       => 'Ana',
			'email'        => 'ana@example.test',
			'estado'       => 'confirmada',
			'rgpd'         => '1',
		);

		$r = $this->enviar();

		$this->assertFalse( $r['ok'] );
		$this->assertStringContainsString( 'dominio del email', $r['data'] );
	}
}
