<?php
/**
 * El publicador antiguo de Google y el enlace a la ficha de socio (issues #112 y #117).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_Publisher;
use PHPUnit\Framework\TestCase;

final class PublicadorGoogleYFichaTest extends TestCase {

	private function fuente( string $relativo ): string {
		$ruta = dirname( __DIR__, 2 ) . '/' . ltrim( $relativo, '/' );
		$this->assertFileExists( $ruta );
		return (string) file_get_contents( $ruta );
	}

	public function test_el_publicador_usa_la_api_de_local_posts(): void {
		$src = $this->fuente( 'social/class-social-publisher.php' );

		$this->assertStringContainsString( 'mybusiness.googleapis.com/v4/', $src );
		$this->assertStringNotContainsString( 'businessprofileperformance', $src, 'la API de métricas no publica' );
	}

	public function test_sin_ubicacion_no_publica(): void {
		$GLOBALS['_wp_http_posts'] = array();
		$metodo = new \ReflectionMethod( Social_Publisher::class, 'publish_google' );
		$res    = $metodo->invoke( null, array( 'access_token' => 'T', 'account_id' => '123' ), 'hola', '', '' );

		$this->assertFalse( $res['success'] );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
	}

	public function test_el_checkin_lee_la_clave_canonica_de_socio(): void {
		$src = $this->fuente( 'admin/class-admin-checkin.php' );

		$this->assertStringContainsString( "'_convoca_member_id'", $src );
		$this->assertStringNotContainsString( "'_convoca_miembro_id'", $src );
	}
}
