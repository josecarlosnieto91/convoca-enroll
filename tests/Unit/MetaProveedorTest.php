<?php
/**
 * Meta (Facebook + Instagram) como red social.
 *
 * `social/class-meta-provider.php` estaba al 0 % (135 statements). Lo que importa aquí no es «que no reviente»
 * sino qué se envía a la API: a qué endpoint (feed o photos), con qué cuerpo, qué se hace cuando Meta contesta
 * con un error y qué pasa cuando no hay token o no hay cuenta de Instagram vinculada.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Meta_Provider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class MetaProveedorTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']     = array( 'options' => array(), 'transients' => array() );
		$GLOBALS['_wp_http_posts'] = array();
		$GLOBALS['convoca_test_http'] = array( 'code' => 200, 'body' => '{"id":"123_456"}' );
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_wp_http_posts'], $GLOBALS['convoca_test_http'] );
	}

	/**
	 * Crea el proveedor y le pone la cuenta por dentro: la lectura del token va contra `$wpdb` y aquí lo que
	 * se prueba es lo que el proveedor HACE con ese token, no cómo lo lee.
	 *
	 * @param string $token   Token de acceso.
	 * @param string $page_id Página de Facebook.
	 * @param string $ig_id   Cuenta de Instagram Business.
	 * @return Meta_Provider
	 */
	private function proveedor( string $token = 'TOKEN', string $page_id = 'PAGINA', string $ig_id = '' ): Meta_Provider {
		$proveedor = new Meta_Provider( 7 );

		$poner = static function ( Meta_Provider $objeto, string $propiedad, string $valor ): void {
			$reflexion = new ReflectionProperty( Meta_Provider::class, $propiedad );
			$reflexion->setValue( $objeto, $valor );
		};

		$poner( $proveedor, 'access_token', $token );
		$poner( $proveedor, 'page_id', $page_id );
		$poner( $proveedor, 'ig_user_id', $ig_id );

		return $proveedor;
	}

	/**
	 * Conectar Meta no se hace con un código: se hace por los endpoints REST.
	 */
	public function test_autenticar_manda_a_la_api_rest(): void {
		$resultado = ( new Meta_Provider( 7 ) )->authenticate( 'codigo' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( '/social/auth/meta', $resultado['message'], 'dice dónde se conecta de verdad' );
	}

	/**
	 * Sin token no se publica y se pide reconectar.
	 */
	public function test_sin_token_no_publica(): void {
		$resultado = $this->proveedor( '' )->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'Token no disponible', $resultado['message'] );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'no se llama a la API sin credenciales' );
	}

	/**
	 * Con el bloqueo puesto (ya hay una publicación en curso) no se duplica el envío.
	 */
	public function test_con_bloqueo_activo_no_se_duplica(): void {
		$proveedor = $this->proveedor();
		$llave     = 'convoca_lock_' . md5( 'hola' . '' . 'meta' );
		set_transient( $llave, time(), 300 );

		$resultado = $proveedor->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'lock activo', $resultado['message'] );
		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'el bloqueo existe para no publicar dos veces' );
	}

	/**
	 * Un texto sin imagen va al feed, con el enlace, y al terminar suelta el bloqueo.
	 */
	public function test_publicar_texto_va_al_feed(): void {
		$proveedor = $this->proveedor();
		$llave     = 'convoca_lock_' . md5( 'hola' . '' . 'meta' );

		$resultado = $proveedor->publish( 'hola', '', 'https://example.test/enlace' );

		$this->assertTrue( $resultado['success'] );
		$this->assertCount( 1, $GLOBALS['_wp_http_posts'], 'solo una llamada: no hay Instagram sin imagen' );

		$llamada = $GLOBALS['_wp_http_posts'][0];
		$this->assertStringContainsString( '/v19.0/PAGINA/feed', $llamada['url'], 'sin imagen el endpoint es el feed' );
		$this->assertSame( 'hola', $llamada['args']['body']['message'] );
		$this->assertSame( 'https://example.test/enlace', $llamada['args']['body']['link'] );
		$this->assertSame( 'TOKEN', $llamada['args']['body']['access_token'] );
		$this->assertFalse( get_transient( $llave ), 'el bloqueo se suelta al terminar' );
	}

	/**
	 * Con imagen se sube a photos (y la imagen viaja en el cuerpo).
	 */
	public function test_publicar_con_imagen_usa_photos(): void {
		$resultado = $this->proveedor()->publish( 'hola', 'https://example.test/cartel.png' );

		$this->assertTrue( $resultado['success'] );
		$llamada = $GLOBALS['_wp_http_posts'][0];
		$this->assertStringContainsString( '/PAGINA/photos', $llamada['url'] );
		$this->assertSame( 'https://example.test/cartel.png', $llamada['args']['body']['url'] );
		$this->assertSame( 'hola', $llamada['args']['body']['message'] );
	}

	/**
	 * Si la subida de la foto falla, se reintenta como texto con enlace.
	 */
	public function test_si_falla_la_foto_reintenta_como_texto(): void {
		$GLOBALS['convoca_test_http'] = array( 'code' => 400, 'body' => '{"error":{"message":"La url de la imagen no es válida"}}' );

		$resultado = $this->proveedor()->publish( 'hola', 'https://example.test/cartel.png', 'https://example.test/enlace' );

		$this->assertCount( 2, $GLOBALS['_wp_http_posts'], 'primero la foto y después el texto' );
		$this->assertStringContainsString( '/photos', $GLOBALS['_wp_http_posts'][0]['url'] );
		$this->assertStringContainsString( '/feed', $GLOBALS['_wp_http_posts'][1]['url'], 'el reintento va al feed' );
		$this->assertArrayNotHasKey( 'url', $GLOBALS['_wp_http_posts'][1]['args']['body'], 'sin imagen no se manda la imagen' );
		$this->assertSame( 'https://example.test/enlace', $GLOBALS['_wp_http_posts'][1]['args']['body']['link'] );
		$this->assertFalse( $resultado['success'], 'el reintento también falló con este doble' );
	}

	/**
	 * Un error de red se cuenta con el mensaje de Meta y sin reintento.
	 */
	public function test_el_error_de_red_se_explica(): void {
		$GLOBALS['convoca_test_http'] = array( 'error' => 'no hay ruta al host' );

		$resultado = $this->proveedor()->publish( 'hola' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'Facebook:', (string) $resultado['results'][0]['message'] );
		$this->assertStringContainsString( 'no hay ruta al host', (string) $resultado['results'][0]['message'] );
	}

	/**
	 * Instagram va en dos pasos: contenedor y publicación.
	 */
	public function test_instagram_publica_en_dos_pasos(): void {
		$resultado = $this->proveedor( 'TOKEN', 'PAGINA', 'IGUSER' )->publish( 'hola', 'https://example.test/cartel.png' );

		$this->assertTrue( $resultado['success'] );
		$urls = array_column( $GLOBALS['_wp_http_posts'], 'url' );
		$this->assertStringContainsString( '/IGUSER/media', $urls[1], 'primero el contenedor' );
		$this->assertStringContainsString( '/IGUSER/media_publish', $urls[2], 'después la publicación' );
		$this->assertSame( 'https://example.test/cartel.png', $GLOBALS['_wp_http_posts'][1]['args']['body']['image_url'] );
		$this->assertSame( '123_456', $GLOBALS['_wp_http_posts'][2]['args']['body']['creation_id'], 'se publica el contenedor que devolvió Meta' );
	}

	/**
	 * Sin cuenta de Instagram vinculada, la publicación no la intenta siquiera.
	 */
	public function test_sin_instagram_vinculado_no_se_intenta(): void {
		$resultado = $this->proveedor( 'TOKEN', 'PAGINA', '' )->publish( 'hola', 'https://example.test/cartel.png' );

		$this->assertTrue( $resultado['success'] );
		$this->assertCount( 1, $GLOBALS['_wp_http_posts'], 'solo Facebook: Instagram no está vinculado' );
		$this->assertCount( 1, (array) $resultado['results'] );
	}

	/**
	 * Si se fuerza Instagram y la página no lo tiene, se dice qué falta en vez de fallar en silencio.
	 *
	 * Se llama al paso de Instagram directamente: el descubrimiento de la cuenta es lo que aquí se mide, y el
	 * doble de `wp_remote_get` devuelve `{}`, que es el caso de una página sin cuenta Business vinculada.
	 */
	public function test_instagram_sin_cuenta_vinculada_lo_dice(): void {
		$proveedor = $this->proveedor( 'TOKEN', 'PAGINA', '' );

		$metodo = new \ReflectionMethod( Meta_Provider::class, 'publish_instagram' );
		$resultado = $metodo->invoke( $proveedor, 'hola', 'https://example.test/cartel.png' );

		$this->assertFalse( $resultado['success'] );
		$this->assertStringContainsString( 'no hay cuenta de Instagram Business', $resultado['message'] );
	}

	/**
	 * `is_authenticated()` y `get_status()` cuentan lo que hay de verdad.
	 */
	public function test_el_estado_de_la_cuenta(): void {
		$this->assertFalse( $this->proveedor( '' )->is_authenticated() );

		$proveedor = $this->proveedor();
		$this->assertTrue( $proveedor->is_authenticated() );

		$estado = $proveedor->get_status();
		$this->assertTrue( $estado['connected'] );
		$this->assertArrayHasKey( 'account_name', $estado );
		$this->assertArrayHasKey( 'expires_at', $estado );
	}

	/**
	 * Sin token guardado, probar la conexión lo dice sin llamar a la API.
	 */
	public function test_probar_la_conexion_sin_token(): void {
		$resultado = ( new Meta_Provider( 7 ) )->test_connection();

		$this->assertFalse( $resultado['success'] );
		$this->assertSame( 'Token no encontrado.', $resultado['message'] );
	}
}
