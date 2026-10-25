<?php
/**
 * Check-in como aplicación instalable (PWA).
 *
 * `includes/Checkin_PWA.php` estaba al 0 % (151 statements). Es lo que hace que el escáner QR de asistencia se
 * pueda instalar en el móvil del monitor y funcione sin cobertura: el manifiesto, el service worker, las
 * etiquetas del `<head>` y la cola de fichajes que se vacía al recuperar la conexión.
 *
 * `serve_manifest()` y `serve_sw()` terminan en `exit;` (cortan la petición: es lo correcto en producción), así
 * que su último tramo no se mide en proceso; lo que producen se comprueba en un proceso hijo, y aquí se mide
 * todo lo demás.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Checkin_PWA;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CheckinPwaTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']          = array( 'options' => array(), 'rewrite_rules' => array() );
		$GLOBALS['_wp_query_vars']   = array();
		$GLOBALS['_convoca_acciones']   = array();
		$GLOBALS['_enr_routes']          = array();
		$GLOBALS['convoca_test_adjuntos'] = array();
		$_SERVER['REQUEST_URI']         = '/';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_wp_query_vars'], $GLOBALS['_convoca_acciones'], $GLOBALS['_enr_routes'], $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Arrancar la PWA engancha el manifiesto, el service worker, las etiquetas y el endpoint de la cola.
	 */
	public function test_init_engancha_lo_necesario(): void {
		Checkin_PWA::init();

		$acciones = (array) ( $GLOBALS['_wp_stores']['acciones'] ?? array() );
		$this->assertNotEmpty( $acciones['template_redirect'] ?? ( $GLOBALS['_convoca_acciones']['template_redirect'] ?? array() ), 'falta el manifiesto' );
		$this->assertNotEmpty( $acciones['wp_head'] ?? ( $GLOBALS['_convoca_acciones']['wp_head'] ?? array() ), 'faltan las etiquetas del head' );

		// La ruta se registra en `rest_api_init`: hay que disparar el enganche para verla.
		$enganches = (array) ( $GLOBALS['_wp_stores']['acciones']['rest_api_init'] ?? ( $GLOBALS['_convoca_acciones']['rest_api_init'] ?? array() ) );
		$this->assertNotEmpty( $enganches, 'sin el enganche no se registra nunca el endpoint de la cola' );

		foreach ( $enganches as $enganche ) {
			if ( is_callable( $enganche ) ) {
				call_user_func( $enganche );
			}
		}

		$rutas = (array) ( $GLOBALS['_enr_routes'] ?? array() );
		$this->assertNotEmpty( $rutas, 'sin el endpoint, la cola offline no se puede vaciar' );
		$this->assertStringContainsString( 'flush', (string) ( $rutas[0]['route'] ?? '' ), 'el endpoint es el de vaciar la cola' );
	}

	/**
	 * Registrar reescrituras no hace nada: los assets se sirven por `template_redirect`.
	 *
	 * Se prueba justo eso, que no añade reglas (si algún día añadiera, el test lo dice).
	 */
	public function test_no_se_anaden_reescrituras(): void {
		Checkin_PWA::register_rewrites();

		$this->assertSame( array(), (array) ( $GLOBALS['_wp_stores']['rewrite_rules'] ?? array() ) );
	}

	/**
	 * Fuera de la página de check-in no se pinta ni una etiqueta.
	 */
	public function test_fuera_de_la_pagina_no_se_pinta_nada(): void {
		$GLOBALS['_wp_query_vars']['convoca_enroll_checkin_page'] = false;

		ob_start();
		Checkin_PWA::add_meta_tags();
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html, 'el resto del sitio no carga con etiquetas de aplicación' );
	}

	/**
	 * En la página del escáner se pinta el manifiesto, el registro del service worker y el color del tema.
	 */
	public function test_en_la_pagina_se_pinta_la_aplicacion(): void {
		$GLOBALS['_wp_query_vars']['convoca_enroll_checkin_page'] = true;

		ob_start();
		Checkin_PWA::add_meta_tags();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'rel="manifest"', $html );
		$this->assertStringContainsString( '/checkin/manifest.json', $html );
		$this->assertStringContainsString( "serviceWorker.register('/checkin/?sw=1'", $html, 'el service worker se registra desde PHP' );
		$this->assertStringContainsString( 'name="theme-color" content="#0f172a"', $html );
		$this->assertStringContainsString( 'apple-mobile-web-app-capable', $html, 'en iOS hace falta la etiqueta de Apple' );
	}

	/**
	 * Los iconos del manifiesto salen del logo del centro cuando lo hay.
	 */
	public function test_los_iconos_del_manifiesto(): void {
		$metodo = new ReflectionMethod( Checkin_PWA::class, 'get_icons' );

		$iconos = $metodo->invoke( null, 'https://example.test/logo.png' );

		$this->assertCount( 2, $iconos, 'sin icono de sitio, los dos del logo' );
		$this->assertSame( 'https://example.test/logo.png', $iconos[0]['src'] );
		$this->assertSame( '192x192', $iconos[0]['sizes'] );
		$this->assertSame( '512x512', $iconos[1]['sizes'] );
		$this->assertSame( 'any maskable', $iconos[1]['purpose'], 'el grande va maskable para Android' );

		$this->assertSame( array(), $metodo->invoke( null, '' ), 'sin logo y sin icono de sitio no se inventan iconos' );
	}

	/**
	 * La cola offline: lo que no se puede verificar se marca, no se da por bueno.
	 */
	public function test_la_cola_descarta_lo_que_no_se_puede_verificar(): void {
		$GLOBALS['nonce_invalido'] = true; // El arnés marca así un nonce que no vale.
		$peticion                  = new \WP_REST_Request();
		$peticion->set_json_params(
			array(
				'items' => array(
					array( 'body' => 'id=ABC&nonce=NONCE' ),
					array( 'body' => 'nonce=NONCE' ),
				),
			)
		);

		$respuesta = Checkin_PWA::rest_flush_queue( $peticion );
		$datos     = $respuesta->get_data();

		$this->assertSame( 2, $datos['processed'] );
		$this->assertSame( 'expired_nonce', $datos['results'][0]['status'], 'un nonce que no vale no ficha a nadie' );
		$this->assertSame( 'invalid', $datos['results'][1]['status'], 'sin identificador no hay nada que hacer' );
		$this->assertSame( 'ABC', $datos['results'][0]['token'] );
	}

	/**
	 * Una cola vacía no es un error: se responde con cero elementos.
	 */
	public function test_la_cola_vacia_responde_cero(): void {
		$peticion = new \WP_REST_Request();
		$peticion->set_json_params( array( 'items' => array() ) );

		$datos = Checkin_PWA::rest_flush_queue( $peticion )->get_data();

		$this->assertSame( 0, $datos['processed'] );
		$this->assertSame( array(), $datos['results'] );
	}
}
