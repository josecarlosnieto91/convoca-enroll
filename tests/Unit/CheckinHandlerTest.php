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

	/**
	 * Siembra la inscripción con su token.
	 *
	 * @param string $token      Token guardado.
	 * @param string $estado     Estado.
	 * @param string $asistencia Asistencia.
	 * @return void
	 */
	private function inscripcion( string $token = 'tok-bueno', string $estado = 'confirmada', string $asistencia = 'no_registrada' ): void {
		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;

		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_checkin_token' => $token,
			'_convoca_estado'        => $estado,
			'_convoca_asistencia'    => $asistencia,
		);
		$GLOBALS['_wp_stores']['get_var_mapa']['_convoca_checkin_token'] = '70';
	}

	/**
	 * Marcar sin token encontrado: error y `ROLLBACK`.
	 */
	public function test_marcar_token_que_no_existe(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array();

		$r = $this->checkin->mark_as_attended_by_token( 'tok-malo' );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'invalid_token', $r->get_error_code() );
	}

	/**
	 * La fila aparece pero el token guardado no coincide (carrera): tampoco vale.
	 */
	public function test_marcar_token_que_no_coincide(): void {
		$this->inscripcion( 'otro-token' );

		$r = $this->checkin->mark_as_attended_by_token( 'tok-malo' );

		$this->assertSame( 'invalid_token', $r->get_error_code() );
	}

	/**
	 * Una inscripción sin confirmar no se da por presente.
	 */
	public function test_marcar_sin_confirmar(): void {
		$this->inscripcion( 'tok-bueno', 'pendiente' );

		$r = $this->checkin->mark_as_attended_by_token( 'tok-bueno' );

		$this->assertSame( 'not_confirmed', $r->get_error_code() );
	}

	/**
	 * Escanear dos veces no cuenta dos: la segunda no toca nada y contesta que sí.
	 */
	public function test_marcar_es_idempotente(): void {
		$this->inscripcion( 'tok-bueno', 'confirmada', 'si' );
		// La hora del primer escaneo: si el segundo la reescribe, el rastro de la puerta miente.
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_hora'] = '2026-11-14 18:03:00';

		$this->assertTrue( $this->checkin->mark_as_attended_by_token( 'tok-bueno' ) );

		$this->assertSame(
			'2026-11-14 18:03:00',
			$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_hora'],
			'el segundo escaneo no reescribe la hora'
		);
	}

	/**
	 * Con todo en orden, marca la asistencia y deja la hora.
	 */
	public function test_marcar_deja_la_hora(): void {
		$this->inscripcion();

		$this->assertTrue( $this->checkin->mark_as_attended_by_token( 'tok-bueno' ) );

		$meta = $GLOBALS['_wp_stores']['post_meta'][70];
		$this->assertSame( 'si', $meta['_convoca_asistencia'] );
		$this->assertNotEmpty( $meta['_convoca_checkin_hora'], 'sin hora, el check-in no deja rastro' );
	}

	/**
	 * La pantalla del escáner se pinta entera.
	 */
	public function test_la_pantalla_del_escaner(): void {
		$metodo = new \ReflectionMethod( Checkin_Handler::class, 'render_scanner_page' );
		ob_start();
		try {
			$metodo->invoke( $this->checkin );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( 'Check-in Convoca', $html );
		$this->assertGreaterThan( 5000, strlen( $html ), 'es la pantalla completa, no un aviso' );
	}
}
