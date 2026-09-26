<?php
/**
 * El enlace de check-in: un solo sitio lo construye y lo valida.
 *
 * Defecto real del 2026-09-26: el correo escribía `/checkin/?token=…` y el handler
 * esperaba `/checkin/<token>/`, así que el QR del correo no hacía nada. La firma `h` se
 * generaba con una sal y no se verificaba en ningún sitio: ahora es la autorización del
 * autoservicio, y por eso tiene que cumplir dos cosas a la vez — servirse al socio y no
 * concederle permisos de administración.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Checkin_Link;
use PHPUnit\Framework\TestCase;

/**
 * Tests del enlace de check-in.
 */
class CheckinLinkTest extends TestCase
{
	private const META = '_convoca_checkin_token';

	protected function setUp(): void
	{
		parent::setUp();
		$GLOBALS['_wp_stores']['post_meta'] = array();
		$GLOBALS['convoca_test_salt']       = 'sal-de-prueba';
	}

	/**
	 * Deja una inscripción con token.
	 */
	private function inscripcion( int $id, string $token ): void
	{
		$GLOBALS['_wp_stores']['post_meta'][ $id ][ self::META ] = $token;
	}

	public function test_la_url_es_la_que_espera_el_handler(): void
	{
		$this->inscripcion( 77, 'abc123' );

		$url = Checkin_Link::url( 77 );

		// La ruta del diseño: /checkin/<token>/ — no `?token=`, que no atendía nadie.
		$this->assertStringContainsString( '/checkin/abc123/', $url );
		$this->assertStringNotContainsString( '?token=', $url );
		$this->assertStringContainsString( 'h=' . Checkin_Link::hmac( 77 ), $url );
	}

	public function test_no_se_le_pide_la_imagen_a_terceros(): void
	{
		$this->inscripcion( 77, 'abc123' );

		// El token es la credencial de check-in del socio: no puede salir de casa.
		$this->assertStringNotContainsString( 'quickchart', Checkin_Link::url( 77 ) );
	}

	public function test_sin_token_no_hay_enlace(): void
	{
		$this->assertSame( '', Checkin_Link::url( 99 ) );
	}

	public function test_la_firma_valida_solo_con_su_sal(): void
	{
		$this->inscripcion( 77, 'abc123' );
		$firma = Checkin_Link::hmac( 77 );

		$this->assertTrue( Checkin_Link::verify( 77, $firma ) );

		// Con otra sal, la misma inscripción da otra firma: no vale la de otro sitio.
		$GLOBALS['convoca_test_salt'] = 'otra-sal';
		$this->assertFalse( Checkin_Link::verify( 77, $firma ) );

		// Y la firma de OTRA inscripción tampoco vale para esta.
		$GLOBALS['convoca_test_salt'] = 'sal-de-prueba';
		$this->assertFalse( Checkin_Link::verify( 77, Checkin_Link::hmac( 78 ) ) );
	}

	public function test_una_firma_vacia_o_una_inscripcion_inexistente_no_valen(): void
	{
		$this->assertFalse( Checkin_Link::verify( 77, '' ) );
		$this->assertFalse( Checkin_Link::verify( 0, Checkin_Link::hmac( 0 ) ) );
	}

	public function test_la_firma_es_estable_entre_peticiones(): void
	{
		// Un QR enviado ayer tiene que seguir valiendo hoy: la firma no depende del tiempo.
		$this->assertSame( Checkin_Link::hmac( 77 ), Checkin_Link::hmac( 77 ) );
	}
}
