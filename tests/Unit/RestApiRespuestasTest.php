<?php
/**
 * Lo que la API de inscripciones contesta: el 429, el 404 y el estado de sesión.
 *
 * `Rest_API` sigue en el 39,1 % de la tanda anterior. Aquí se cubre lo que decide **qué contesta** cuando
 * algo va mal, que es lo que nadie mira hasta que pasa: el límite de peticiones, la actividad que no
 * existe y la que existe pero es de otro tipo (un post normal pedido como actividad).
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll\Tests\Unit;

use Convoca\Enroll\Rest_API;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';

/**
 * Respuestas de error y estado de sesión.
 */
final class RestApiRespuestasTest extends TestCase {

	/**
	 * Cada prueba parte con el límite de peticiones disponible y sin sesión de socio.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_rate_ok']   = array();
		$GLOBALS['convoca_test_member']    = false;
		$GLOBALS['convoca_test_member_id'] = 0;
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();

		if ( ! class_exists( Rest_API::class ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/Rest_API.php';
		}
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_rate_ok']    = array();
		$GLOBALS['convoca_test_member']     = false;
		$GLOBALS['convoca_test_member_id']  = 0;
		$GLOBALS['convoca_test_post_types'] = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	/**
	 * Una petición con los parámetros que se le pasen.
	 *
	 * @param array $params Parámetros.
	 * @return \WP_REST_Request
	 */
	private function peticion( array $params = array() ): \WP_REST_Request {
		return new \WP_REST_Request( $params );
	}

	// ── límite de peticiones ───────────────────────────────────────────────────────────

	/**
	 * Agotado el límite, el listado contesta 429 y no devuelve datos.
	 *
	 * @return void
	 */
	public function test_el_listado_corta_con_429_al_agotar_el_limite(): void {
		$GLOBALS['convoca_test_rate_ok']['convoca_enroll_list_activities'] = false;

		$respuesta = ( new Rest_API() )->list_activities( $this->peticion( array( 'per_page' => 20, 'page' => 1 ) ) );

		$this->assertSame( 429, $respuesta->get_status(), 'sin límite, un bucle tumba el sitio' );
		$this->assertArrayHasKey( 'error', $respuesta->get_data() );
		$this->assertArrayNotHasKey( 'X-WP-Total', $respuesta->get_headers(), 'un 429 no pagina' );
	}

	/**
	 * La ficha de una actividad también corta.
	 *
	 * @return void
	 */
	public function test_la_ficha_corta_con_429(): void {
		$GLOBALS['convoca_test_rate_ok']['convoca_enroll_get_activity'] = false;

		$respuesta = ( new Rest_API() )->get_activity( $this->peticion( array( 'id' => 5 ) ) );

		$this->assertSame( 429, $respuesta->get_status() );
	}

	/**
	 * Y el estado de sesión tiene su propio límite, más bajo.
	 *
	 * @return void
	 */
	public function test_el_estado_de_sesion_corta_con_429(): void {
		$GLOBALS['convoca_test_rate_ok']['session_status'] = false;

		$respuesta = ( new Rest_API() )->get_session_status();

		$this->assertSame( 429, $respuesta->get_status() );
	}

	// ── actividad inexistente ──────────────────────────────────────────────────────────

	/**
	 * Pedir una actividad que no existe da 404, no un objeto vacío.
	 *
	 * @return void
	 */
	public function test_una_actividad_que_no_existe_da_404(): void {
		$resultado = ( new Rest_API() )->get_activity( $this->peticion( array( 'id' => 999 ) ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado );
		$this->assertSame( 'not_found', $resultado->get_error_code() );
	}

	/**
	 * Una entrada que existe pero no es una actividad también da 404.
	 *
	 * @return void
	 */
	public function test_una_entrada_que_no_es_actividad_da_404(): void {
		$GLOBALS['convoca_test_post_types'] = array( 12 => 'post' );

		$resultado = ( new Rest_API() )->get_activity( $this->peticion( array( 'id' => 12 ) ) );

		$this->assertInstanceOf( \WP_Error::class, $resultado, 'un post normal no se sirve como actividad' );
		$this->assertSame( 'not_found', $resultado->get_error_code() );
	}

	/**
	 * Una actividad de verdad se devuelve formateada.
	 *
	 * @return void
	 */
	public function test_una_actividad_valida_se_devuelve(): void {
		$actividad                        = new \WP_Post();
		$actividad->ID                    = 12;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Actividad de ejemplo';
		$GLOBALS['_wp_stores']['posts'][12] = $actividad;

		$respuesta = ( new Rest_API() )->get_activity( $this->peticion( array( 'id' => 12 ) ) );

		$this->assertSame( 200, $respuesta->get_status() );
		$this->assertSame( 12, $respuesta->get_data()['id'] );
		$this->assertArrayHasKey( 'titulo', $respuesta->get_data() );
	}

	// El camino «sin sesión» y «con sesión» de `get_session_status()` NO se prueba aquí: depende de
	// `Convoca\Core\Member_Identity`, que es la clase real del núcleo y decide con el usuario de
	// WordPress. Doblar esa clase en este arnés tapaba la de verdad y rompía dos pruebas de
	// `MotorInscripcionDecisionesTest`. Ese camino se cubre en las pruebas del núcleo.
}
