<?php
/**
 * Quién puede hacer un check-in directo y con qué código HTTP se responde.
 *
 * El check-in directo lo puede hacer el personal (siempre) y quien recibe el QR (con su
 * firma `h`). Lo que NO puede pasar es que la firma conceda permisos de administración:
 * sirve para esa inscripción y para ninguna otra cosa.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Checkin_Handler;
use Convoca\Enroll\Checkin_Link;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Tests de la decisión de acceso al check-in.
 */
class CheckinPermisosTest extends TestCase
{
	private Checkin_Handler $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->handler                       = new Checkin_Handler();
		$GLOBALS['_wp_stores']['post_meta']  = array();
		$GLOBALS['convoca_test_salt']        = 'sal-de-prueba';
		$GLOBALS['convoca_test_caps']        = array();
		$GLOBALS['convoca_test_roles']       = array();
		$GLOBALS['_wp_stores']['post_meta'][77]['_convoca_checkin_token'] = 'abc123';
	}

	/**
	 * Deja al usuario SIN ningún permiso de personal.
	 */
	private function sinPermisos(): void
	{
		$GLOBALS['convoca_test_caps']  = array( 'manage_inscripciones' => false );
		$GLOBALS['convoca_test_roles'] = array();
	}

	/**
	 * El socio que recibe el QR: sin permisos, solo con su firma.
	 */
	private function socio(): void
	{
		$this->sinPermisos();
	}

	public function test_el_personal_entra_sin_necesitar_firma(): void
	{
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => true );

		$this->assertTrue( $this->handler->es_personal() );
		$this->assertTrue( $this->handler->puede_checkin_directo( 77, '' ) );
	}

	public function test_el_voluntario_aprobado_cuenta_como_personal(): void
	{
		$this->sinPermisos();
		$GLOBALS['convoca_test_roles'] = array( 'voluntario_aprobado' );

		$this->assertTrue( $this->handler->es_personal() );
	}

	public function test_quien_recibe_el_qr_entra_con_su_firma_y_sin_permisos(): void
	{
		$this->socio();

		$this->assertFalse( $this->handler->es_personal(), 'Un socio no debe ser personal.' );
		$this->assertTrue( $this->handler->puede_checkin_directo( 77, Checkin_Link::hmac( 77 ) ) );
	}

	public function test_sin_permisos_y_con_firma_que_no_corresponde_no_se_entra(): void
	{
		$this->socio();

		$this->assertFalse( $this->handler->puede_checkin_directo( 77, 'firma-inventada' ) );
		$this->assertFalse( $this->handler->puede_checkin_directo( 77, Checkin_Link::hmac( 78 ) ) );
		$this->assertFalse( $this->handler->puede_checkin_directo( 0, '' ) );
	}

	public function test_la_firma_de_una_no_sirve_para_otra_inscripcion(): void
	{
		$this->socio();

		// Es la comprobación que impide que un socio marque la asistencia de otro.
		$this->assertTrue( $this->handler->puede_checkin_directo( 77, Checkin_Link::hmac( 77 ) ) );
		$this->assertFalse( $this->handler->puede_checkin_directo( 88, Checkin_Link::hmac( 77 ) ) );
	}

	public function test_los_codigos_http_dicen_lo_que_pasa(): void
	{
		// Antes cualquier error salía como 500: «el servidor se rompió» cuando lo que
		// pasaba era que el enlace no valía.
		$this->assertSame( 404, $this->handler->status_para_error( new WP_Error( 'invalid_token', 'Token de check-in no válido.' ) ) );
		$this->assertSame( 409, $this->handler->status_para_error( new WP_Error( 'not_confirmed', 'La inscripción no está confirmada.' ) ) );
		$this->assertSame( 400, $this->handler->status_para_error( new WP_Error( 'checkin_error', 'Error al procesar el check-in.' ) ) );
	}
}
