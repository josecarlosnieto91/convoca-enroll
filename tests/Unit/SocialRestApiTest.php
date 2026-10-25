<?php
/**
 * La API REST de redes sociales: rutas, permisos y gestión de cuentas.
 *
 * `social/class-social-rest-api.php` estaba al 0 % (186 statements). Aquí lo que se mide es lo que decide quién
 * puede tocar las cuentas del centro: las seis rutas con su método y su permiso, y las dos operaciones que
 * devuelven datos (listar y dar de baja).
 *
 * EXCEPCIÓN ANOTADA: los cuatro recorridos de OAuth (`auth_meta_start`, `callback_meta`, `auth_google_start`,
 * `callback_google`) terminan en `wp_redirect(...); exit;` — es lo correcto en producción y en una prueba de
 * PHPUnit el `exit` se lleva por delante la suite, así que su tramo posterior no se mide en proceso. Su
 * comportamiento en vivo lo cubre el recorrido E2E (`user-journey-social.spec.js`, 17/17 en verde: «arrancar el
 * OAuth de Meta redirige, no rompe» y «el callback del proveedor sin código no rompe»).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_Rest_API;
use PHPUnit\Framework\TestCase;

class SocialRestApiTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']          = array( 'options' => array(), 'transients' => array(), 'results_mapa' => array() );
		$GLOBALS['_enr_routes']         = array();
		$GLOBALS['convoca_test_caps']   = array();
		$GLOBALS['db_deletes']          = array();
		$GLOBALS['_convoca_acciones']   = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_enr_routes'], $GLOBALS['convoca_test_caps'], $GLOBALS['db_deletes'], $GLOBALS['_convoca_acciones'] );
	}

	/**
	 * Registra las rutas como lo hace el arranque (el constructor engancha `rest_api_init`).
	 *
	 * @return array<int, array<string, mixed>> Rutas registradas.
	 */
	private function rutas(): array {
		$api = new Social_Rest_API();
		$api->register_routes();

		return (array) $GLOBALS['_enr_routes'];
	}

	/**
	 * El constructor engancha el registro de rutas en `rest_api_init`.
	 */
	public function test_el_constructor_engancha_las_rutas(): void {
		new Social_Rest_API();

		$acciones = (array) ( $GLOBALS['_wp_stores']['acciones']['rest_api_init'] ?? ( $GLOBALS['_convoca_acciones']['rest_api_init'] ?? array() ) );

		$this->assertNotEmpty( $acciones, 'sin esto no existe ni un endpoint' );
	}

	/**
	 * Están las seis rutas, cada una con su método.
	 */
	public function test_las_seis_rutas_y_sus_metodos(): void {
		$rutas = array();
		foreach ( $this->rutas() as $ruta ) {
			$rutas[ (string) $ruta['route'] ] = $ruta;
		}

		$esperadas = array(
			'/social/auth/meta'              => 'GET',
			'/social/auth/google'            => 'GET',
			'/social/callback/meta'          => 'GET',
			'/social/callback/google'        => 'GET',
			'/social/accounts'               => 'GET',
			'/social/accounts/(?P<id>\d+)'   => 'DELETE',
		);

		foreach ( $esperadas as $ruta => $metodo ) {
			$this->assertArrayHasKey( $ruta, $rutas, "falta la ruta {$ruta}" );
			$this->assertSame( $metodo, $rutas[ $ruta ]['methods'], "la ruta {$ruta} no acepta {$metodo}" );
			$this->assertSame( 'convoca/v1', $rutas[ $ruta ]['namespace'] );
		}

		$this->assertCount( 6, $rutas );
	}

	/**
	 * Lo que toca las cuentas exige permiso; el callback del proveedor no (viene de fuera).
	 */
	public function test_los_permisos_de_cada_ruta(): void {
		foreach ( $this->rutas() as $ruta ) {
			$permiso = $ruta['permission_callback'];

			if ( in_array( $ruta['route'], array( '/social/callback/meta', '/social/callback/google' ), true ) ) {
				$this->assertSame( '__return_true', $permiso, 'el callback de OAuth lo llama el proveedor, no una persona' );
				continue;
			}

			$this->assertIsArray( $permiso, "la ruta {$ruta['route']} no puede quedar sin guarda" );
			$this->assertSame( 'can_manage_social', $permiso[1] );
		}
	}

	/**
	 * Gestionar las cuentas vale con el permiso propio o con el de administración, y no vale sin ninguno.
	 */
	public function test_quien_puede_gestionar_las_cuentas(): void {
		$api = new Social_Rest_API();

		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_social' => true, 'manage_options' => false );
		$this->assertTrue( $api->can_manage_social(), 'la persona que lleva las redes puede' );

		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_social' => false, 'manage_options' => true );
		$this->assertTrue( $api->can_manage_social(), 'quien administra el sitio también' );

		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_social' => false, 'manage_options' => false );
		$this->assertFalse( $api->can_manage_social(), 'y nadie más' );
	}

	/**
	 * Listar devuelve las cuentas que hay, con un 200.
	 */
	public function test_listar_las_cuentas(): void {
		$GLOBALS['_wp_stores']['results_mapa']['convoca_social_accounts'] = array(
			array( 'id' => 3, 'network' => 'meta', 'account_name' => 'Página del centro' ),
		);

		$respuesta = ( new Social_Rest_API() )->list_accounts();

		$this->assertSame( 200, $respuesta->get_status() );
		$datos = $respuesta->get_data();
		$this->assertCount( 1, $datos );
		$this->assertSame( 'Página del centro', $datos[0]['account_name'] );
	}

	/**
	 * Dar de baja una cuenta borra POR ID en la tabla de cuentas sociales.
	 */
	public function test_dar_de_baja_una_cuenta(): void {
		$peticion = new \WP_REST_Request( array( 'id' => '17' ) );

		$respuesta = ( new Social_Rest_API() )->delete_account( $peticion );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertTrue( $respuesta->get_data()['success'] );

		$this->assertCount( 1, (array) $GLOBALS['db_deletes'], 'se borra una fila' );
		$borrado = $GLOBALS['db_deletes'][0];
		$this->assertStringContainsString( 'convoca_social_accounts', (string) $borrado['tabla'] );
		$this->assertSame( array( 'id' => 17 ), $borrado['donde'], 'y se borra la que se pide, casteada a entero' );
	}
}
