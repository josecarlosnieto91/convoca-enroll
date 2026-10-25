<?php
/**
 * Endpoints REST con datos: check-in, ICS y gestión de inscripciones.
 *
 * Son los que mueven información de personas. Lo que se mide, además del camino feliz, son las negativas y **en qué
 * orden** se comprueban: un check-in de un token ajeno no puede marcar nada, ni una actividad sin permiso devolver
 * estadísticas.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-client.php';
require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-sdk.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__ ) . '/../includes/Google_Calendar.php';
require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';

/**
 * Endpoints.
 */
final class RestEndpointsTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * API.
	 *
	 * @var Rest_API
	 */
	private Rest_API $api;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_caps']     = array( 'manage_options' => true, 'manage_inscripciones' => true );
		$GLOBALS['convoca_test_user_can'] = array( 'manage_options' => true );
		$GLOBALS['convoca_test_posts']    = array();
		$GLOBALS['convoca_test_terms']    = array();

		// Actividad abierta y confirmada.
		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => gmdate( 'Y-m-d H:i:s', time() + 604800 ),
			'_convoca_fecha_fin'          => gmdate( 'Y-m-d H:i:s', time() + 604800 + 7200 ),
			'_convoca_plazas_totales'     => 20,
			'_convoca_plazas_disponibles' => 15,
		);

		$this->api = new Rest_API();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['convoca_test_user_can'] = array();
		$GLOBALS['convoca_test_posts'] = array();
	}

	/**
	 * Siembra una inscripción confirmada.
	 *
	 * @param string $token  Token de check-in.
	 * @param string $estado Estado.
	 * @return \WP_Post
	 */
	private function inscripcion( string $token = 'tok-1', string $estado = 'confirmada' ): \WP_Post {
		$post                        = new \WP_Post();
		$post->ID                    = 70;
		$post->post_type             = 'inscripcion';
		$post->post_title            = 'Ana';
		$GLOBALS['_wp_stores']['posts'][70] = $post;
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id'  => 61,
			'_convoca_email'         => 'ana@example.test',
			'_convoca_nombre'        => 'Ana',
			'_convoca_estado'        => $estado,
			'_convoca_asistencia'    => 'no_registrada',
			'_convoca_checkin_token' => $token,
		);

		return $post;
	}

	/**
	 * Petición con parámetros.
	 *
	 * @param array $params Parámetros.
	 * @return \WP_REST_Request
	 */
	private function peticion( array $params ): \WP_REST_Request {
		return new \WP_REST_Request( $params );
	}

	/**
	 * Sin código no hay check-in.
	 */
	public function test_checkin_sin_token(): void {
		$respuesta = $this->api->handle_checkin( $this->peticion( array() ) );

		$this->assertSame( 400, $respuesta->get_status() );
		$this->assertSame( 'Token required', $respuesta->get_data()['error'] );
	}

	/**
	 * Un token que no corresponde a nadie, 404.
	 */
	public function test_checkin_token_invalido(): void {
		$respuesta = $this->api->handle_checkin( $this->peticion( array( 'code' => 'no-existe' ) ) );

		$this->assertSame( 404, $respuesta->get_status() );
		$this->assertSame( 'Invalid token', $respuesta->get_data()['error'] );
	}

	/**
	 * Sin permiso sobre esa actividad no se marca la asistencia de nadie.
	 */
	public function test_checkin_sin_permiso(): void {
		$this->inscripcion();
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][70] );
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		// Sin responsables en la actividad: `is_user_responsible` dice que no.
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '';

		$respuesta = $this->api->handle_checkin( $this->peticion( array( 'code' => 'tok-1' ) ) );

		$this->assertSame( 403, $respuesta->get_status() );
		$this->assertSame( 'no_registrada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'], 'no se ha marcado' );
	}

	/**
	 * Una inscripción sin confirmar no se da por presente.
	 */
	public function test_checkin_sin_confirmar(): void {
		$this->inscripcion( 'tok-1', 'pendiente' );
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][70] );

		$respuesta = $this->api->handle_checkin( $this->peticion( array( 'code' => 'tok-1' ) ) );

		$this->assertSame( 400, $respuesta->get_status() );
		$this->assertSame( 'pendiente', $respuesta->get_data()['estado'] );
	}

	/**
	 * Check-in correcto: marca y devuelve el nombre.
	 */
	public function test_checkin_bien(): void {
		$this->inscripcion();
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][70] );

		$respuesta = $this->api->handle_checkin( $this->peticion( array( 'code' => 'tok-1' ) ) );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertTrue( $respuesta->get_data()['success'] );
		$this->assertSame( 'Ana', $respuesta->get_data()['nombre'] );
		$this->assertFalse( $respuesta->get_data()['already_checked'] );
		$this->assertSame( 'si', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'] );
	}

	/**
	 * Escanear dos veces lo dice, no falla.
	 */
	public function test_checkin_repetido(): void {
		$this->inscripcion();
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'] = 'si';
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][70] );

		$respuesta = $this->api->handle_checkin( $this->peticion( array( 'code' => 'tok-1' ) ) );

		// Un segundo escaneo SÍ se distingue: `cambiar_asistencia()` devuelve si hubo cambio real (enroll#42).
		// Antes esta prueba afirmaba lo contrario —`assertFalse`— porque el endpoint no podía saberlo.
		$this->assertTrue( $respuesta->get_data()['already_checked'] );
		$this->assertStringContainsString( 'ya había sido escaneada', (string) $respuesta->get_data()['message'] );
		$this->assertSame( 'si', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'], 'sigue marcado' );
	}

	/**
	 * Un menor: sale el nombre del participante, no el de quien inscribe.
	 */
	public function test_checkin_de_un_menor(): void {
		$this->inscripcion();
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_es_menor']            = '1';
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_nombre_participante'] = 'Lucía';
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][70] );

		$respuesta = $this->api->handle_checkin( $this->peticion( array( 'code' => 'tok-1' ) ) );

		$this->assertSame( 'Lucía', $respuesta->get_data()['nombre'] );
	}

	/**
	 * Las estadísticas de una actividad ajena, no.
	 */
	public function test_stats_sin_permiso(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '';

		$r = $this->api->get_stats( $this->peticion( array( 'actividad_id' => 61 ) ) );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 403, $r->get_error_data( 'rest_forbidden' )['status'] );
	}

	/**
	 * Con permiso: plazas y ocupación calculada.
	 */
	public function test_las_estadisticas(): void {
		$r = $this->api->get_stats( $this->peticion( array( 'actividad_id' => 61 ) ) );

		$this->assertSame( 200, $r->get_status() );
		$this->assertSame( 20, $r->get_data()['plazas_total'] );
		$this->assertSame( 15, $r->get_data()['plazas_disp'] );
		$this->assertSame( 25.0, (float) $r->get_data()['ocupacion_pct'], '5 de 20 plazas ocupadas' );
	}

	/**
	 * Modificar una inscripción ajena, no.
	 */
	public function test_modificar_sin_permiso(): void {
		$this->inscripcion();
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '';

		$r = $this->api->update_inscription( $this->peticion( array( 'id' => 70, 'estado' => 'cancelada' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'confirmada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'], 'no se ha tocado' );
	}

	/**
	 * Cancelar desde la API pasa por el motor (con su ventana y su plaza), no por un `update_meta` a pelo.
	 */
	public function test_cancelar_pasa_por_el_motor(): void {
		$this->inscripcion();

		$this->api->update_inscription( $this->peticion( array( 'id' => 70, 'estado' => 'cancelada' ) ) );

		$this->assertSame( 'cancelada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
		$this->assertSame( '', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_token'], 'el QR deja de valer' );
	}

	/**
	 * Un estado cualquiera se guarda tal cual; la asistencia va por su propio camino.
	 */
	public function test_estado_libre_y_asistencia(): void {
		$this->inscripcion();

		$this->api->update_inscription( $this->peticion( array( 'id' => 70, 'estado' => 'pagada', 'asistencia' => 'si' ) ) );

		$this->assertSame( 'pagada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_estado'] );
		$this->assertSame( 'si', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'] );
	}

	/**
	 * Descargar el ICS de una inscripción con un token que no es el suyo: 403.
	 */
	public function test_ics_con_token_ajeno(): void {
		$this->inscripcion( 'tok-bueno' );

		$capturado = null;
		try {
			$this->api->download_ics( $this->peticion( array( 'id' => 70, 'token' => 'tok-malo' ) ) );
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$capturado = $cortado->respuesta;
		}

		$this->assertNotNull( $capturado, 'se corta' );
		$this->assertStringContainsString( 'Token de seguridad inválido', (string) ( $capturado['die'] ?? $capturado ) );
	}

	/**
	 * Un id que no existe: 404, no un 500.
	 */
	public function test_ics_inexistente(): void {
		$capturado = null;
		try {
			$this->api->download_ics( $this->peticion( array( 'id' => 999, 'token' => 'x' ) ) );
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$capturado = $cortado->respuesta;
		}

		$this->assertNotNull( $capturado );
		$this->assertStringContainsString( 'No encontrado', (string) ( $capturado['die'] ?? $capturado ) );
	}

	/**
	 * Un tipo que no es actividad ni inscripción: 400.
	 */
	public function test_ics_de_tipo_invalido(): void {
		$post                        = new \WP_Post();
		$post->ID                    = 80;
		$post->post_type             = 'page';
		$GLOBALS['_wp_stores']['posts'][80] = $post;

		$capturado = null;
		try {
			$this->api->download_ics( $this->peticion( array( 'id' => 80, 'token' => 'x' ) ) );
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$capturado = $cortado->respuesta;
		}

		$this->assertNotNull( $capturado );
		$this->assertStringContainsString( 'Tipo de post no válido', (string) ( $capturado['die'] ?? $capturado ) );
	}
}
