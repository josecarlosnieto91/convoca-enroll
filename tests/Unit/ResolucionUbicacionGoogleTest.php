<?php
/**
 * Resolución de la ubicación de Google Business Profile al conectar (issue #92).
 *
 * El `account_id` de Google no es una ubicación: la API de Local Posts necesita el `name`
 * completo. Se resuelve una vez, al conectar, y se guarda en la cuenta.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_OAuth;
use PHPUnit\Framework\TestCase;

class ResolucionUbicacionGoogleTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_wp_http_gets']             = array();
		$GLOBALS['convoca_test_http_get_queue'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_wp_http_gets'], $GLOBALS['convoca_test_http_get_queue'] );
	}

	/**
	 * Con cuenta y ficha, devuelve el `name` completo y el título.
	 */
	public function test_resuelve_la_primera_ubicacion(): void {
		$GLOBALS['convoca_test_http_get_queue'] = array(
			array( 'code' => 200, 'body' => '{"accounts":[{"name":"accounts/1"}]}' ),
			array( 'code' => 200, 'body' => '{"locations":[{"name":"locations/2","title":"Centro Social"}]}' ),
		);

		$ubicacion = Social_OAuth::resolver_ubicacion_google( 'TOKEN' );

		$this->assertSame( 'accounts/1/locations/2', $ubicacion['name'] );
		$this->assertSame( 'Centro Social', $ubicacion['title'] );
		$this->assertSame( '', $ubicacion['error'] );
		$this->assertCount( 2, $GLOBALS['_wp_http_gets'] );
		$this->assertStringContainsString( 'mybusinessaccountmanagement.googleapis.com/v1/accounts', $GLOBALS['_wp_http_gets'][0]['url'] );
		$this->assertStringContainsString( 'accounts/1/locations', $GLOBALS['_wp_http_gets'][1]['url'] );
		$this->assertSame( 'Bearer TOKEN', $GLOBALS['_wp_http_gets'][0]['args']['headers']['Authorization'] );
	}

	/**
	 * Sin cuentas de Business Profile no se inventa una ubicación.
	 */
	/**
	 * Si Google ya devuelve el nombre completo, no se duplica la cuenta.
	 */
	public function test_acepta_el_nombre_completo(): void {
		$GLOBALS['convoca_test_http_get_queue'] = array(
			array( 'code' => 200, 'body' => '{"accounts":[{"name":"accounts/1"}]}' ),
			array( 'code' => 200, 'body' => '{"locations":[{"name":"accounts/1/locations/2","title":"Centro"}]}' ),
		);

		$ubicacion = Social_OAuth::resolver_ubicacion_google( 'TOKEN' );

		$this->assertSame( 'accounts/1/locations/2', $ubicacion['name'] );
	}

	/**
	 * Con dos cuentas y dos fichas no se elige una en silencio (enroll#107).
	 */
	public function test_dos_fichas_piden_elegir(): void {
		$GLOBALS['convoca_test_http_get_queue'] = array(
			array( 'code' => 200, 'body' => '{"accounts":[{"name":"accounts/1"},{"name":"accounts/2"}]}' ),
			array( 'code' => 200, 'body' => '{"locations":[{"name":"locations/2","title":"Centro"}]}' ),
			array( 'code' => 200, 'body' => '{"locations":[{"name":"locations/9","title":"Tienda"}]}' ),
		);

		$ubicacion = Social_OAuth::resolver_ubicacion_google( 'TOKEN' );

		$this->assertSame( 'elegir_ubicacion', $ubicacion['error'] );
		$this->assertSame( '', $ubicacion['name'] );
	}

	/**
	 * La ficha se selecciona por configuración explícita (enroll#107).
	 */
	public function test_la_ficha_se_elige_por_filtro(): void {
		$GLOBALS['_wp_filters']['convoca_enroll_gbp_location'] = array( 10 => array( static fn(): string => 'Tienda' ) );
		$GLOBALS['convoca_test_http_get_queue'] = array(
			array( 'code' => 200, 'body' => '{"accounts":[{"name":"accounts/1"},{"name":"accounts/2"}]}' ),
			array( 'code' => 200, 'body' => '{"locations":[{"name":"locations/2","title":"Centro"}]}' ),
			array( 'code' => 200, 'body' => '{"locations":[{"name":"locations/9","title":"Tienda"}]}' ),
		);

		try {
			$ubicacion = Social_OAuth::resolver_ubicacion_google( 'TOKEN' );
		} finally {
			unset( $GLOBALS['_wp_filters']['convoca_enroll_gbp_location'] );
		}

		$this->assertSame( 'accounts/2/locations/9', $ubicacion['name'] );
		$this->assertSame( 'Tienda', $ubicacion['title'] );
	}

	public function test_sin_cuentas_devuelve_error(): void {
		$GLOBALS['convoca_test_http_get_queue'] = array(
			array( 'code' => 200, 'body' => '{"accounts":[]}' ),
		);

		$ubicacion = Social_OAuth::resolver_ubicacion_google( 'TOKEN' );

		$this->assertSame( '', $ubicacion['name'] );
		$this->assertSame( 'sin_cuenta', $ubicacion['error'] );
	}

	/**
	 * Una respuesta con un `name` que no es una ubicación tampoco vale.
	 */
	public function test_una_ubicacion_mal_formada_no_vale(): void {
		$GLOBALS['convoca_test_http_get_queue'] = array(
			array( 'code' => 200, 'body' => '{"accounts":[{"name":"accounts/1"}]}' ),
			array( 'code' => 200, 'body' => '{"locations":[{"name":"1234567890","title":"Ficha"}]}' ),
		);

		$ubicacion = Social_OAuth::resolver_ubicacion_google( 'TOKEN' );

		$this->assertSame( '', $ubicacion['name'] );
		$this->assertSame( 'sin_ubicacion', $ubicacion['error'] );
	}
}
