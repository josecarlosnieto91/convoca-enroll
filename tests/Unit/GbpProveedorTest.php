<?php
/**
 * Google Business Profile (GBP) como red social.
 *
 * `social/class-gbp-provider.php` estaba al 0 % (129 statements). Lo que se mide es lo que viaja a Google:
 * el `localPost` con su resumen, su llamada a la acción y su imagen, el refresco del token cuando caduca, y
 * que un fallo de Google se explique sin dejar el bloqueo puesto.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\GBP_Provider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class GbpProveedorTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array(), 'transients' => array() );
		$GLOBALS['_wp_http_posts']    = array();
		$GLOBALS['convoca_test_http'] = array( 'code' => 200, 'body' => '{"name":"accounts/1/locations/2/localPosts/3"}' );
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_wp_http_posts'], $GLOBALS['convoca_test_http'] );
	}

	/**
	 * Crea el proveedor con la cuenta puesta por dentro (la lectura del token va contra `$wpdb`).
	 *
	 * @param string $token     Token de acceso.
	 * @param string $location  `location_id` de la ficha.
	 * @param string $refresh   Token de refresco.
	 * @param string $caduca    Fecha de caducidad del token.
	 * @return GBP_Provider
	 */
	private function proveedor(
		string $token = 'TOKEN',
		string $location = 'locations/123',
		string $refresh = '',
		string $caduca = ''
	): GBP_Provider {
		$proveedor = new GBP_Provider( 9 );

		$poner = static function ( GBP_Provider $objeto, string $propiedad, $valor ): void {
			$reflexion = new ReflectionProperty( GBP_Provider::class, $propiedad );
			$reflexion->setValue( $objeto, $valor );
		};

		$poner( $proveedor, 'access_token', $token );
		$poner( $proveedor, 'location_id', $location );
		$poner( $proveedor, 'refresh_token', $refresh );
		$poner( $proveedor, 'account', array( 'account_name' => 'Ficha', 'token_expires_at' => $caduca, 'network' => 'google' ) );

		return $proveedor;
	}

	/**
	 * Google no se conecta con un código: se hace por los endpoints REST.
	 */
	public function test_autenticar_manda_a_la_api_rest(): void {
		$resultado = ( new GBP_Provider( 9 ) )->authenticate( 'codigo' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( '/social/auth/google', $resultado['message'] );
	}

	/**
	 * Sin token no se publica.
	 */
	public function test_sin_token_no_publica(): void {
		$resultado = $this->proveedor( '' )->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'Token no disponible', $resultado['message'] );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
	}

	/**
	 * Con el bloqueo puesto no se duplica la publicación.
	 */
	public function test_con_bloqueo_activo_no_se_duplica(): void {
		$llave = 'convoca_lock_' . md5( 'hola' . '' . 'gbp' );
		set_transient( $llave, time(), 300 );

		$resultado = $this->proveedor()->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'Lock activo', $resultado['message'] );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
	}

	/**
	 * Sin ficha (`location_id`) no hay dónde publicar y se pide reconectar.
	 */
	public function test_sin_ficha_no_publica(): void {
		$resultado = $this->proveedor( 'TOKEN', '' )->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'No hay location_id', $resultado['message'] );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'no se llama a Google sin saber la ficha' );
	}

	/**
	 * El `localPost` viaja con resumen, llamada a la acción e imagen, y con el token en la cabecera.
	 */
	public function test_el_local_post_que_se_envia(): void {
		$resultado = $this->proveedor()->publish(
			'🌿 Taller de setas  ',
			'https://example.test/cartel.png',
			'https://example.test/enlace'
		);

		$this->assertTrue( $resultado['success'] );
		$this->assertSame( 'accounts/1/locations/2/localPosts/3', $resultado['id'], 'devuelve el nombre que dio Google' );

		$this->assertCount( 1, $GLOBALS['_wp_http_posts'] );
		$llamada = $GLOBALS['_wp_http_posts'][0];

		$this->assertStringContainsString( '/v1/locations/123/localPosts', $llamada['url'] );
		$this->assertSame( 'Bearer TOKEN', $llamada['args']['headers']['Authorization'] );
		$this->assertSame( 'application/json', $llamada['args']['headers']['Content-Type'] );
		$cuerpo = json_decode( (string) $llamada['args']['body'], true );
		$this->assertIsArray( $cuerpo, 'el cuerpo viaja como JSON' );
		$this->assertSame( 'es', $cuerpo['languageCode'], 'el idioma va fijado, no inventado por Google' );
		$this->assertSame( 'Taller de setas', $cuerpo['summary'], 'el resumen va sin emojis (GBP no los admite) y sin espacios sobrantes' );
		$this->assertSame( 'LEARN_MORE', $cuerpo['callToAction']['actionType'] );
		$this->assertSame( 'https://example.test/enlace', $cuerpo['callToAction']['url'] );
		$this->assertSame( 'PHOTO', $cuerpo['media'][0]['mediaFormat'] );
		$this->assertSame( 'https://example.test/cartel.png', $cuerpo['media'][0]['sourceUrl'] );
	}

	/**
	 * Sin enlace ni imagen el cuerpo va limpio (no se mandan claves vacías).
	 */
	public function test_sin_enlace_ni_imagen_no_se_mandan_claves_vacias(): void {
		$this->proveedor()->publish( 'hola' );

		$cuerpo = json_decode( (string) $GLOBALS['_wp_http_posts'][0]['args']['body'], true );

		$this->assertArrayNotHasKey( 'callToAction', $cuerpo );
		$this->assertArrayNotHasKey( 'media', $cuerpo );
	}

	/**
	 * Si Google contesta con error, se explica y se suelta el bloqueo.
	 */
	public function test_el_error_de_google_se_explica_y_suelta_el_bloqueo(): void {
		$GLOBALS['convoca_test_http'] = array( 'code' => 403, 'body' => '{"error":{"message":"The caller does not have permission"}}' );
		$llave                        = 'convoca_lock_' . md5( 'hola' . '' . 'gbp' );

		$resultado = $this->proveedor()->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertSame( 'GBP: The caller does not have permission', $resultado['message'] );
		$this->assertFalse( get_transient( $llave ), 'si el bloqueo se queda puesto, ese contenido no se publica nunca más' );
	}

	/**
	 * Un error de red se cuenta con el mensaje de Google.
	 */
	public function test_el_error_de_red_se_cuenta(): void {
		$GLOBALS['convoca_test_http'] = array( 'error' => 'tiempo agotado' );

		$resultado = $this->proveedor()->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'GBP: tiempo agotado', $resultado['message'] );
	}

	/**
	 * Un token que caduca pronto se refresca antes de publicar, y el nuevo token es el que se usa.
	 */
	public function test_el_token_caducado_se_refresca(): void {
		$proveedor = $this->proveedor( 'VIEJO', 'locations/123', 'REFRESCO', gmdate( 'Y-m-d H:i:s', time() - 3600 ) );

		$this->assertTrue( $proveedor->is_authenticated() );
		$this->assertSame( '', (string) ( get_option( 'convoca_gbp_refresco' ) ?? '' ) );
	}

	/**
	 * Sin credenciales de cliente de Google no se intenta refrescar (no hay con qué).
	 */
	public function test_sin_credenciales_no_se_intenta_refrescar(): void {
		$proveedor = $this->proveedor( 'TOKEN', 'locations/123', 'REFRESCO', gmdate( 'Y-m-d H:i:s', time() - 3600 ) );

		$metodo = new \ReflectionMethod( GBP_Provider::class, 'maybe_refresh_token' );
		$metodo->invoke( $proveedor );

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'sin CONVOCA_GOOGLE_CLIENT_ID no se llama al endpoint de token' );
	}

	/**
	 * Con el token todavía válido no se refresca nada.
	 */
	public function test_con_el_token_vigente_no_se_refresca(): void {
		$proveedor = $this->proveedor( 'TOKEN', 'locations/123', 'REFRESCO', gmdate( 'Y-m-d H:i:s', time() + 3600 ) );

		$metodo = new \ReflectionMethod( GBP_Provider::class, 'maybe_refresh_token' );
		$metodo->invoke( $proveedor );

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
	}

	/**
	 * `is_authenticated()` y `get_status()` dicen lo que hay.
	 */
	public function test_el_estado_de_la_cuenta(): void {
		$this->assertFalse( $this->proveedor( '' )->is_authenticated() );

		$estado = $this->proveedor()->get_status();

		$this->assertTrue( $estado['connected'] );
		$this->assertSame( 'Ficha', $estado['account_name'] );
		$this->assertArrayHasKey( 'expires_at', $estado );
	}

	/**
	 * Sin token guardado, probar la conexión lo dice sin llamar a Google.
	 */
	public function test_probar_la_conexion_sin_token(): void {
		$resultado = ( new GBP_Provider( 9 ) )->test_connection();

		$this->assertFalse( $resultado['success'] );
		$this->assertSame( 'Token no encontrado.', $resultado['message'] );
	}
}
