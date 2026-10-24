<?php
/**
 * Check-in: quién puede entrar y qué se responde.
 *
 * El QR del check-in lo lleva el monitor en el móvil y también lo recibe la persona inscrita en su correo. La decisión
 * de quién puede marcar y qué se contesta (no existe / no es tuyo / adelante) está aquí, y equivocarla deja a alguien
 * fuera en la puerta o —peor— permite marcar la asistencia de otro.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Checkin_Handler;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Checkin_Link.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Checkin_Handler.php';

/**
 * Check-in.
 */
final class CheckinHandlerTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Manejador.
	 *
	 * @var Checkin_Handler
	 */
	private Checkin_Handler $checkin;

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
		// El arnés concede por omisión: para probar «no es personal» hay que negarlo explícitamente.
		$GLOBALS['convoca_test_caps']  = array( 'manage_inscripciones' => false );
		$GLOBALS['convoca_test_roles'] = array();
		$GLOBALS['_wp_query_vars']     = array();
		$_GET                          = array();

		$this->checkin = new Checkin_Handler();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['convoca_test_caps']  = array( 'manage_inscripciones' => false );
		$GLOBALS['convoca_test_roles'] = array();
		$GLOBALS['_wp_query_vars']     = array();
		$_GET                          = array();
	}

	/**
	 * Ejecuta la página de check-in.
	 *
	 * @return array Mensaje y código con que se cortó (si se cortó).
	 */
	private function pagina(): array {
		try {
			$this->checkin->handle_checkin_page();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			return is_array( $cortado->respuesta ) ? $cortado->respuesta : array( 'cortado' => $cortado->respuesta );
		}

		return array();
	}

	/**
	 * El personal (por capacidad o por ser voluntario aprobado) entra; el resto no.
	 */
	public function test_quien_es_personal(): void {
		$this->assertFalse( $this->checkin->es_personal(), 'sin sesión no es personal' );

		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => true );
		$this->assertTrue( $this->checkin->es_personal(), 'con la capacidad, sí' );

		$GLOBALS['convoca_test_caps']  = array( 'manage_inscripciones' => false );
		$GLOBALS['convoca_test_roles'] = array( 'voluntario_aprobado' );
		$this->assertTrue( $this->checkin->es_personal(), 'un voluntario aprobado también' );
	}

	/**
	 * Qué se responde: 404 si el enlace no existe, 200 si es personal o la firma vale, 403 si no.
	 */
	public function test_la_respuesta_del_checkin_directo(): void {
		$this->assertSame( 404, $this->checkin->respuesta_checkin_directo( 0, 'firma' ), 'enlace sin inscripción' );

		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => true );
		$this->assertSame( 200, $this->checkin->respuesta_checkin_directo( 410, 'lo-que-sea' ), 'el personal puede con cualquier enlace' );

		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => false );
		$this->assertSame( 403, $this->checkin->respuesta_checkin_directo( 410, 'firma-inventada' ), 'sin firma válida, no' );
		$this->assertSame( 403, $this->checkin->respuesta_checkin_directo( 410, '' ), 'sin firma, tampoco' );
	}

	/**
	 * La firma buena da paso a quien no es personal.
	 */
	public function test_firma_valida_deja_pasar(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => false );

		$this->assertTrue(
			$this->checkin->puede_checkin_directo( 410, \Convoca\Enroll\Checkin_Link::hmac( 410 ) ),
			'la firma de su inscripción le deja entrar'
		);
		$this->assertFalse(
			$this->checkin->puede_checkin_directo( 411, \Convoca\Enroll\Checkin_Link::hmac( 410 ) ),
			'la firma de otra persona, no'
		);
	}

	/**
	 * El código HTTP de cada error: el enlace malo no es un 500.
	 */
	public function test_los_codigos_de_error(): void {
		$this->assertSame( 404, $this->checkin->status_para_error( new \WP_Error( 'invalid_token' ) ) );
		$this->assertSame( 409, $this->checkin->status_para_error( new \WP_Error( 'not_confirmed' ) ), 'no confirmada: conflicto' );
		$this->assertSame( 400, $this->checkin->status_para_error( new \WP_Error( 'otro' ) ) );
	}

	/**
	 * Sin token ni escáner, la página no hace nada (no es su petición).
	 */
	public function test_fuera_de_su_pagina(): void {
		$this->assertSame( array(), $this->pagina(), 'sin token ni escáner no se corta' );
	}

	/**
	 * El escáner es herramienta de trabajo: sin ser personal, se deniega.
	 */
	public function test_escaner_sin_permiso(): void {
		$GLOBALS['_wp_query_vars']['convoca_enroll_checkin_page'] = true;

		$respuesta = $this->pagina();

		$this->assertStringContainsString( 'permisos', (string) ( $respuesta['die'] ?? '' ) );
	}

	/**
	 * Un token que no corresponde a ninguna inscripción: «no válido», no «acceso denegado».
	 */
	public function test_token_inexistente(): void {
		$GLOBALS['_wp_query_vars']['convoca_enroll_checkin'] = 'token-que-no-existe';

		$respuesta = $this->pagina();

		$this->assertStringContainsString( 'no corresponde a ninguna inscripción', (string) ( $respuesta['die'] ?? '' ) );
	}
}
