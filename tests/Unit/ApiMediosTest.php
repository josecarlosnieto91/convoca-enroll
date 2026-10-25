<?php
/**
 * La API REST de medios: qué se le pide al motor de carteles y qué se hace con su respuesta.
 *
 * `media/class-media-rest-api.php` estaba al 0 % (92 statements). El endpoint no se podía probar sin arrastrar
 * Imagick y la base de datos, así que se le han añadido **puntos de inyección** (`convoca_media_poster`, `_qr`,
 * `_templates`, `_template`, `_blog`): sin nadie enganchado el comportamiento es EXACTAMENTE el de antes, y aquí
 * se sustituyen para poder afirmar qué se pide y qué se responde.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Media_Rest_API;
use PHPUnit\Framework\TestCase;

class ApiMediosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']       = array( 'options' => array(), 'edit_links' => array() );
		$GLOBALS['_enr_routes']      = array();
		$GLOBALS['_wp_filters']      = array();
		$GLOBALS['_media_peticiones'] = array();
		$GLOBALS['_wp_logs']         = array();
		$GLOBALS['convoca_test_caps'] = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array() );
		$GLOBALS['convoca_test_caps'] = array();
		unset( $GLOBALS['_enr_routes'], $GLOBALS['_wp_filters'], $GLOBALS['_media_peticiones'], $GLOBALS['_wp_logs'] );
	}

	/**
	 * Engancha un colaborador del módulo.
	 *
	 * @param string   $nombre  Nombre del filtro (poster, qr, templates, template, blog).
	 * @param callable $gestor  Sustituto.
	 */
	private function inyectar( string $nombre, callable $gestor ): void {
		add_filter( 'convoca_media_' . $nombre, static function () use ( $gestor ) {
			return $gestor;
		} );
	}

	/**
	 * Las cinco rutas con su permiso.
	 */
	public function test_las_rutas_y_sus_permisos(): void {
		( new Media_Rest_API() )->register_routes();

		$rutas = array();
		foreach ( (array) $GLOBALS['_enr_routes'] as $ruta ) {
			$rutas[ (string) $ruta['route'] ] = $ruta;
		}

		foreach ( array( '/media/poster/render', '/media/poster/regenerate', '/media/templates', '/media/templates/(?P<id>\d+)', '/media/blog/create' ) as $esperada ) {
			$this->assertArrayHasKey( $esperada, $rutas, "falta la ruta {$esperada}" );
			$this->assertSame( 'convoca/v1', $rutas[ $esperada ]['namespace'] );
			$permiso = $rutas[ $esperada ]['permission_callback'];
			$this->assertIsArray( $permiso, "la ruta {$esperada} no puede quedar sin guarda" );
			$this->assertSame( 'can_manage_media', $permiso[1], "la ruta {$esperada} no exige el permiso de medios" );
		}
	}

	/**
	 * Gestionar medios vale con el permiso propio o con el de administración.
	 */
	public function test_quien_puede_gestionar_medios(): void {
		$api = new Media_Rest_API();

		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_media' => true, 'manage_options' => false );
		$this->assertTrue( $api->can_manage_media() );

		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_media' => false, 'manage_options' => true );
		$this->assertTrue( $api->can_manage_media() );

		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_media' => false, 'manage_options' => false );
		$this->assertFalse( $api->can_manage_media() );
	}

	/**
	 * Generar un cartel: lo que se pide al motor, con sus valores por defecto.
	 */
	public function test_generar_un_cartel_pide_lo_correcto(): void {
		$this->inyectar(
			'poster',
			function ( $id, $plantilla, $opciones ) {
				$GLOBALS['_media_peticiones']['render'] = compact( 'id', 'plantilla', 'opciones' );

				return array( 'url' => 'https://example.test/cartel.png', 'cached' => false );
			}
		);

		$peticion = new \WP_REST_Request( array( 'actividad_id' => '2797' ) );
		$respuesta = ( new Media_Rest_API() )->render_poster( $peticion );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertSame( 'https://example.test/cartel.png', $respuesta->get_data()['url'] );

		$pedido = $GLOBALS['_media_peticiones']['render'];
		$this->assertSame( 2797, $pedido['id'] );
		$this->assertSame( 'naturaleza', $pedido['plantilla'], 'la plantilla por defecto' );
		$this->assertSame( array( 'square' ), $pedido['opciones']['formats'], 'el formato por defecto' );
		$this->assertSame( 'png', $pedido['opciones']['export'] );
		$this->assertFalse( $pedido['opciones']['force'] );
	}

	/**
	 * Los parámetros de la petición llegan al motor tal cual (y el `image_id` como entero).
	 */
	public function test_los_parametros_llegan_al_motor(): void {
		$this->inyectar(
			'poster',
			function ( $id, $plantilla, $opciones ) {
				$GLOBALS['_media_peticiones']['render'] = compact( 'id', 'plantilla', 'opciones' );

				return array( 'url' => 'https://example.test/c.webp' );
			}
		);

		$peticion = new \WP_REST_Request(
			array(
				'actividad_id' => '12',
				'template'     => 'familiar',
				'format'       => 'story',
				'image_id'     => '34',
				'force'        => true,
				'export'       => 'webp',
			)
		);
		( new Media_Rest_API() )->render_poster( $peticion );

		$pedido = $GLOBALS['_media_peticiones']['render'];
		$this->assertSame( 12, $pedido['id'] );
		$this->assertSame( 'familiar', $pedido['plantilla'] );
		$this->assertSame( array( 'story' ), $pedido['opciones']['formats'] );
		$this->assertSame( 34, $pedido['opciones']['image_id'], 'la imagen va como entero' );
		$this->assertTrue( $pedido['opciones']['force'] );
		$this->assertSame( 'webp', $pedido['opciones']['export'] );
	}

	/**
	 * Si el motor devuelve un error, se explica con un 400 en vez de un 500 pelado.
	 */
	public function test_si_el_motor_falla(): void {
		$this->inyectar(
			'poster',
			static function () {
				return new \WP_Error( 'sin_plantilla', 'No hay plantilla para ese formato.' );
			}
		);

		$respuesta = ( new Media_Rest_API() )->render_poster( new \WP_REST_Request( array( 'actividad_id' => '2797' ) ) );

		$this->assertSame( 400, $respuesta->get_status() );
		$this->assertSame( 'No hay plantilla para ese formato.', $respuesta->get_data()['error'] );
	}

	/**
	 * Regenerar invalida el QR viejo y fuerza el repintado.
	 */
	public function test_regenerar_invalida_el_qr_y_fuerza(): void {
		$this->inyectar(
			'qr',
			function ( $id ) {
				$GLOBALS['_media_peticiones']['qr'][] = $id;
			}
		);
		$this->inyectar(
			'poster',
			function ( $id, $plantilla, $opciones ) {
				$GLOBALS['_media_peticiones']['render'] = compact( 'id', 'plantilla', 'opciones' );

				return array( 'url' => 'https://example.test/cartel.png' );
			}
		);

		$respuesta = ( new Media_Rest_API() )->regenerate_poster( new \WP_REST_Request( array( 'actividad_id' => '2797' ) ) );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertSame( array( 2797 ), $GLOBALS['_media_peticiones']['qr'], 'se invalida el QR de esa actividad' );
		$this->assertTrue( $GLOBALS['_media_peticiones']['render']['opciones']['force'], 'regenerar fuerza el repintado' );
	}

	/**
	 * La lista de plantillas se devuelve tal cual.
	 */
	public function test_listar_plantillas(): void {
		$this->inyectar(
			'templates',
			static function () {
				return array(
					array( 'slug' => 'naturaleza', 'name' => 'Naturaleza' ),
					array( 'slug' => 'familiar', 'name' => 'Familiar' ),
				);
			}
		);

		$respuesta = ( new Media_Rest_API() )->list_templates();

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertCount( 2, $respuesta->get_data() );
		$this->assertSame( 'naturaleza', $respuesta->get_data()[0]['slug'] );
	}

	/**
	 * Una plantilla que no existe es un 404 con su mensaje, no un 200 vacío.
	 */
	public function test_una_plantilla_que_no_existe(): void {
		$this->inyectar(
			'template',
			static function () {
				return null;
			}
		);

		$respuesta = ( new Media_Rest_API() )->get_template( new \WP_REST_Request( array( 'id' => '99' ) ) );

		$this->assertSame( 404, $respuesta->get_status() );
		$this->assertSame( 'Template not found', $respuesta->get_data()['error'] );
	}

	/**
	 * La plantilla existente se devuelve con 200.
	 */
	public function test_la_plantilla_que_existe(): void {
		$this->inyectar(
			'template',
			static function ( $id ) {
				return array( 'slug' => 'familiar', 'id' => $id );
			}
		);

		$respuesta = ( new Media_Rest_API() )->get_template( new \WP_REST_Request( array( 'id' => '7' ) ) );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertSame( 7, $respuesta->get_data()['id'] );
	}

	/**
	 * Crear la entrada del blog devuelve su id, su enlace de edición y su estado.
	 */
	public function test_crear_la_entrada_del_blog(): void {
		$this->inyectar(
			'blog',
			function ( $id, $previo, $estado ) {
				$GLOBALS['_media_peticiones']['blog'] = compact( 'id', 'previo', 'estado' );

				return 555;
			}
		);
		$GLOBALS['_wp_stores']['edit_links'][555] = 'https://example.test/wp-admin/post.php?post=555';

		$respuesta = ( new Media_Rest_API() )->create_blog_post( new \WP_REST_Request( array( 'actividad_id' => '2797' ) ) );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertSame( 555, $respuesta->get_data()['post_id'] );
		$this->assertStringContainsString( 'post=555', (string) $respuesta->get_data()['edit_url'], 'con enlace para abrirla' );
		$this->assertArrayHasKey( 'status', $respuesta->get_data(), 'y su estado' );

		$pedido = $GLOBALS['_media_peticiones']['blog'];
		$this->assertSame( 2797, $pedido['id'] );
		$this->assertSame( 'draft', $pedido['estado'], 'por defecto, borrador' );
	}

	/**
	 * Un fallo al crear la entrada se explica con un 400.
	 */
	public function test_si_falla_la_entrada_del_blog(): void {
		$this->inyectar(
			'blog',
			static function () {
				return new \WP_Error( 'sin_actividad', 'La actividad no existe.' );
			}
		);

		$respuesta = ( new Media_Rest_API() )->create_blog_post( new \WP_REST_Request( array( 'actividad_id' => '0' ) ) );

		$this->assertSame( 400, $respuesta->get_status() );
		$this->assertSame( 'La actividad no existe.', $respuesta->get_data()['error'] );
	}

	/**
	 * Sin nadie enganchado al filtro se usa la implementación REAL (el comportamiento de siempre).
	 *
	 * Es la garantía de que el punto de inyección es aditivo: si no hay filtro, o si el filtro devuelve algo
	 * que no se puede llamar, el endpoint se comporta exactamente como antes.
	 */
	public function test_sin_inyeccion_se_usa_la_implementacion_real(): void {
		$metodo  = new \ReflectionMethod( Media_Rest_API::class, 'colaborador' );
		$defecto = static function () {
			return 'real';
		};

		$this->assertSame( $defecto, $metodo->invoke( new Media_Rest_API(), 'poster', $defecto ), 'sin filtro, la clase real' );

		// Un filtro que devuelve algo que no es invocable tampoco rompe: se ignora.
		add_filter( 'convoca_media_poster', static function () {
			return 'esto no es una función';
		} );
		$this->assertSame( $defecto, $metodo->invoke( new Media_Rest_API(), 'poster', $defecto ) );
	}
}
