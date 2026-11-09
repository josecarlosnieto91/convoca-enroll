<?php
/**
 * Quién puede hacer un check-in directo y con qué código HTTP se responde.
 *
 * La ruta directa `/checkin/<token>/` era del asistente: bastaba la firma `h` del enlace del
 * correo para marcarse la asistencia sin sesión y semanas antes (enroll#76). Ahora es una
 * herramienta del personal: la firma del enlace ya no autoriza nada.
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
		$GLOBALS['convoca_test_caps']  = array( 'manage_inscripciones' => false, 'convoca_manage_checkin' => false );
		$GLOBALS['convoca_test_roles'] = array();
	}

	public function test_el_personal_entra_con_su_capacidad(): void
	{
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => true );

		$this->assertTrue( $this->handler->es_personal() );
		$this->assertTrue( $this->handler->puede_checkin_directo( 77 ) );
	}

	public function test_la_capacidad_del_modulo_tambien_vale(): void
	{
		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_checkin' => true );

		$this->assertTrue( $this->handler->es_personal() );
	}

	public function test_el_voluntario_aprobado_cuenta_como_personal(): void
	{
		$this->sinPermisos();
		$GLOBALS['convoca_test_roles'] = array( 'voluntario_aprobado' );

		$this->assertTrue( $this->handler->es_personal() );
	}

	/**
	 * Quien recibe el QR ya NO puede marcar su asistencia: el enlace no es una credencial.
	 */
	public function test_la_firma_del_enlace_ya_no_autoriza(): void
	{
		$this->sinPermisos();

		$this->assertFalse( $this->handler->es_personal(), 'Un socio no debe ser personal.' );
		$this->assertFalse(
			$this->handler->puede_checkin_directo( 77 ),
			'la firma h del correo ya no sirve para marcar'
		);
		// El HMAC sigue existiendo para identificar la inscripción del QR, no para autorizar.
		$this->assertNotSame( '', Checkin_Link::hmac( 77 ) );
	}

	public function test_los_codigos_http_dicen_lo_que_pasa(): void
	{
		$this->assertSame( 404, $this->handler->status_para_error( new WP_Error( 'invalid_token', 'Token de check-in no válido.' ) ) );
		$this->assertSame( 409, $this->handler->status_para_error( new WP_Error( 'not_confirmed', 'La inscripción no está confirmada.' ) ) );
		$this->assertSame( 409, $this->handler->status_para_error( new WP_Error( 'checkin_temprano', 'Todavía no es la hora.' ) ) );
		$this->assertSame( 409, $this->handler->status_para_error( new WP_Error( 'checkin_cerrado', 'Ya terminó.' ) ) );
		$this->assertSame( 403, $this->handler->status_para_error( new WP_Error( 'no_user', 'Sin usuario.' ) ) );
		$this->assertSame( 400, $this->handler->status_para_error( new WP_Error( 'checkin_error', 'Error al procesar el check-in.' ) ) );
	}

	public function test_un_enlace_que_no_existe_responde_404_no_403(): void
	{
		$this->sinPermisos();

		// No es un problema de permisos: es que ese enlace no es de nadie.
		$this->assertSame( 404, $this->handler->respuesta_checkin_directo( 0 ) );
		$this->assertSame( 403, $this->handler->respuesta_checkin_directo( 77 ) );
	}

	public function test_el_personal_entra_aunque_el_token_no_le_pertenezca(): void
	{
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => true );

		$this->assertSame( 200, $this->handler->respuesta_checkin_directo( 88 ) );
		// Pero un enlace que no corresponde a ninguna inscripción sigue siendo 404.
		$this->assertSame( 404, $this->handler->respuesta_checkin_directo( 0 ) );
	}
}
