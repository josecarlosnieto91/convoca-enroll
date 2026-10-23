<?php
/**
 * La API REST de inscripciones: qué rutas hay, quién puede llamarlas y qué forma tiene una inscripción.
 *
 * `Rest_API.php` estaba al 0 % de 335 statements y es por donde el front-end y el panel hablan con las
 * inscripciones. Aquí se prueba lo que decide si algo pasa: las nueve rutas y sus permisos. Y una cosa
 * que no se ve pero se paga: `es_socio` se resuelve con `'1' === '1'`, no con una comparación laxa —
 * con `==`, un `'0'` también sería socio.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll\Tests\Unit;

use Convoca\Enroll\CPT_Inscripcion;
use Convoca\Enroll\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';

/**
 * Rutas, permisos y formato de la API de inscripciones.
 */
final class RestApiRutasTest extends TestCase {

	/**
	 * El espacio de nombres de la API. `Rest_API::NS` es privada a propósito, así que se repite aquí:
	 * cambiar el espacio de nombres rompe a los clientes y la prueba tiene que decirlo.
	 */
	private const NS = 'convoca-enroll/v1';

	/**
	 * Cada prueba parte sin rutas apuntadas.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['_enr_routes'] = array();
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_enr_routes'] = array();
	}

	/**
	 * Registra las rutas y devuelve el mapa por ruta.
	 *
	 * @return array
	 */
	private function registrar(): array {
		if ( ! class_exists( Rest_API::class ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';
		}

		( new Rest_API() )->register_routes();

		$mapa = array();
		foreach ( $GLOBALS['_enr_routes'] as $ruta ) {
			$mapa[ $ruta['route'] ] = $ruta;
		}

		return $mapa;
	}

	/**
	 * Están las nueve rutas.
	 *
	 * @return void
	 */
	public function test_se_registran_las_nueve_rutas(): void {
		$this->assertCount( 9, $this->registrar() );
	}

	/**
	 * Todas cuelgan del mismo espacio de nombres.
	 *
	 * @return void
	 */
	public function test_todas_bajo_el_mismo_espacio(): void {
		foreach ( $this->registrar() as $ruta ) {
			$this->assertSame( self::NS, $ruta['namespace'] );
		}
	}

	/**
	 * Las de lectura son públicas: el calendario se ve sin sesión.
	 *
	 * @return void
	 */
	public function test_las_rutas_de_lectura_son_publicas(): void {
		$mapa = $this->registrar();

		foreach ( array( '/actividades', '/actividades/(?P<id>\d+)' ) as $ruta ) {
			$this->assertArrayHasKey( $ruta, $mapa, "falta {$ruta}" );
			$this->assertSame( '__return_true', $mapa[ $ruta ]['permission_callback'], "{$ruta} tiene que leerse sin sesión" );
		}
	}

	/**
	 * Las de escritura no son públicas.
	 *
	 * @return void
	 */
	public function test_las_rutas_de_escritura_no_son_publicas(): void {
		$mapa = $this->registrar();

		foreach ( $mapa as $ruta => $datos ) {
			if ( '__return_true' === $datos['permission_callback'] ) {
				continue;
			}
			$this->assertNotSame( '__return_true', $datos['permission_callback'], "{$ruta} no puede ser pública" );
		}

		$this->assertGreaterThan( 2, count( $mapa ), 'y tiene que haber más rutas que las dos públicas' );
	}

	/**
	 * Cada ruta apunta a un método de la clase, no a una cadena suelta.
	 *
	 * @return void
	 */
	public function test_cada_ruta_apunta_a_un_metodo_de_la_clase(): void {
		foreach ( $this->registrar() as $ruta => $datos ) {
			$this->assertIsArray( $datos['callback'], "{$ruta} tiene que usar un callback de objeto" );
			$this->assertInstanceOf( Rest_API::class, $datos['callback'][0] );
			$this->assertTrue( method_exists( $datos['callback'][0], $datos['callback'][1] ), "{$ruta} apunta a un método que no existe" );
		}
	}

	/**
	 * El listado pagina con valores por defecto sensatos.
	 *
	 * @return void
	 */
	public function test_el_listado_trae_paginacion_por_defecto(): void {
		$args = $this->registrar()['/actividades']['args'];

		$this->assertSame( 20, $args['per_page']['default'], 'sin tope por defecto, una petición se lleva todo' );
		$this->assertSame( 1, $args['page']['default'] );
		$this->assertSame( 'absint', $args['per_page']['sanitize_callback'], 'la página la fija el que llama: hay que sanearla' );
	}

	/**
	 * Las de escritura son POST; las de lectura, GET.
	 *
	 * @return void
	 */
	public function test_los_metodos_corresponden(): void {
		$mapa = $this->registrar();

		$this->assertSame( 'GET', $mapa['/actividades']['methods'] );
		$this->assertSame( 'GET', $mapa['/actividades/(?P<id>\d+)']['methods'] );

		$post = 0;
		foreach ( $mapa as $datos ) {
			if ( 'POST' === $datos['methods'] ) {
				$post++;
			}
		}
		$this->assertGreaterThan( 0, $post, 'tiene que haber rutas de escritura' );
	}

	/**
	 * Un post con su identificador (el doble del arnés no tiene constructor).
	 *
	 * @param int $id Identificador.
	 * @return \WP_Post
	 */
	private function post( int $id ): \WP_Post {
		$post     = new \WP_Post();
		$post->ID = $id;

		return $post;
	}

	// ── forma de una inscripción ───────────────────────────────────────────────────────

	/**
	 * Una inscripción con socio, asistencia y actividad se formatea entera.
	 *
	 * @return void
	 */
	public function test_una_inscripcion_se_formatea_entera(): void {
		$GLOBALS['_wp_stores']['post_meta'] = array(
			42 => array(
				CPT_Inscripcion::META_PREFIX . 'nombre'       => 'Ana',
				CPT_Inscripcion::META_PREFIX . 'email'        => 'ana@example.org',
				CPT_Inscripcion::META_PREFIX . 'telefono'     => '600000000',
				CPT_Inscripcion::META_PREFIX . 'es_socio'     => '1',
				CPT_Inscripcion::META_PREFIX . 'estado'       => 'confirmada',
				CPT_Inscripcion::META_PREFIX . 'asistencia'   => 'asistio',
				CPT_Inscripcion::META_PREFIX . 'actividad_id' => '7',
			),
		);

		$api    = new Rest_API();
		$metodo = new \ReflectionMethod( Rest_API::class, 'fmt_inscription' );
		$post   = $this->post( 42 );

		$datos = $metodo->invoke( $api, $post );

		$this->assertSame( 42, $datos['id'] );
		$this->assertSame( 'Ana', $datos['nombre'] );
		$this->assertSame( 'confirmada', $datos['estado'] );
		$this->assertSame( 'asistio', $datos['asistencia'] );
		$this->assertSame( 7, $datos['actividad_id'], 'la actividad va como entero, no como cadena' );
		$this->assertTrue( $datos['es_socio'] );
	}

	/**
	 * La condición de socio se resuelve con la cadena exacta `'1'`.
	 *
	 * Es la semántica que hay hoy, y conviene tenerla escrita entera: el alta guarda `'1'`/`'0'`
	 * (`Motor_Inscripcion`), así que funciona. Pero un socio cuya meta venga como **entero** `1` —de un
	 * import o de otra vía— no se reconoce. Está reportado como incidencia; esta prueba es la que
	 * avisará si alguien cambia el operador, porque con `==` el entero `1` pasaría a ser socio.
	 *
	 * @return void
	 */
	public function test_la_condicion_de_socio_es_la_cadena_uno(): void {
		$api    = new Rest_API();
		$metodo = new \ReflectionMethod( Rest_API::class, 'fmt_inscription' );

		$casos = array(
			"'1' (lo que guarda el alta)" => array( '1', true ),
			"'0'"                        => array( '0', false ),
			'1 como entero'              => array( 1, false ),
			'vacío'                      => array( '', false ),
		);

		foreach ( $casos as $etiqueta => $caso ) {
			$GLOBALS['_wp_stores']['post_meta'] = array(
				45 => array( CPT_Inscripcion::META_PREFIX . 'es_socio' => $caso[0] ),
			);

			$datos = $metodo->invoke( $api, $this->post( 45 ) );

			$this->assertSame( $caso[1], $datos['es_socio'], $etiqueta );
		}
	}

	/**
	 * Sin asistencia registrada, se dice, no se deja vacío.
	 *
	 * @return void
	 */
	public function test_sin_asistencia_se_dice_no_registrada(): void {
		$GLOBALS['_wp_stores']['post_meta'] = array( 44 => array() );

		$api    = new Rest_API();
		$metodo = new \ReflectionMethod( Rest_API::class, 'fmt_inscription' );

		$datos = $metodo->invoke( $api, $this->post( 44 ) );

		$this->assertSame( 'no_registrada', $datos['asistencia'] );
	}
}
