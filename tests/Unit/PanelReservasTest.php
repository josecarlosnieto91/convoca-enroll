<?php
/**
 * El panel público de reservas: entrar con email + código y cancelar.
 *
 * `public/class-panel-reservas.php` estaba al 0 % (105 statements). Es la única pantalla sin sesión de WordPress
 * que enseña datos de una persona, así que lo que se mide aquí es, sobre todo, quién puede ver y cancelar qué:
 * el límite de intentos, que el código sea el de esa inscripción y que no se pueda cancelar dos veces.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Inscripcion;
use Convoca\Enroll\Motor_Inscripcion;
use Convoca\Enroll\Panel_Reservas;
use PHPUnit\Framework\TestCase;

class PanelReservasTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']               = array( 'options' => array(), 'post_meta' => array(), 'transients' => array() );
		$GLOBALS['_wp_shortcodes']           = array();
		$GLOBALS['_convoca_acciones']        = array();
		$GLOBALS['_wp_json']                 = array();
		$GLOBALS['convoca_test_rate_ok']     = array();
		$GLOBALS['convoca_test_get_posts_cola'] = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['nonces_invalidos']         = array();
		$_POST                               = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset(
			$GLOBALS['_wp_shortcodes'],
			$GLOBALS['_convoca_acciones'],
			$GLOBALS['_wp_json'],
			$GLOBALS['convoca_test_rate_ok'],
			$GLOBALS['convoca_test_get_posts_cola'],
			$GLOBALS['convoca_test_posts'],
			$GLOBALS['convoca_test_get_posts_args'],
			$GLOBALS['nonces_invalidos']
		);
		$_POST = array();
	}


	/**
	 * Una inscripción como objeto de WordPress (la firma de `buscar_por_codigo()` exige `WP_Post`).
	 *
	 * @param int $id Identificador.
	 * @return \WP_Post
	 */
	private function inscripcion( int $id ): \WP_Post {
		$post           = new \WP_Post();
		$post->ID       = $id;
		$post->post_type = 'inscripcion';

		return $post;
	}

	/**
	 * Una actividad como objeto de WordPress.
	 *
	 * @param int    $id    Identificador.
	 * @param string $titulo Título.
	 * @return \WP_Post
	 */
	private function actividad( int $id, string $titulo ): \WP_Post {
		$post             = new \WP_Post();
		$post->ID         = $id;
		$post->post_title = $titulo;
		$post->post_type  = 'actividad';

		return $post;
	}

	/**
	 * Ejecuta un manejador AJAX y devuelve el JSON que habría enviado.
	 *
	 * @param callable $manejador Manejador.
	 * @return array{ok: bool, data: mixed}
	 */
	private function json( callable $manejador ): array {
		try {
			$manejador();
		} catch ( \Convoca_Json_Cortado $corte ) {
			return (array) $corte->respuesta;
		}

		$this->fail( 'el manejador tenía que responder con JSON' );
		return array();
	}

	/**
	 * El panel engancha su shortcode y sus cuatro endpoints (dos de ellos también sin sesión).
	 */
	public function test_los_enganches_del_panel(): void {
		new Panel_Reservas();

		$this->assertArrayHasKey( 'convoca_panel_reservas', (array) $GLOBALS['_wp_shortcodes'] );

		$acciones = array_merge( (array) ( $GLOBALS['convoca_test_actions'] ?? array() ), (array) ( $GLOBALS['_convoca_acciones'] ?? array() ) );
		foreach ( array( 'wp_ajax_convoca_panel_login', 'wp_ajax_nopriv_convoca_panel_login', 'wp_ajax_convoca_panel_cancelar', 'wp_ajax_nopriv_convoca_panel_cancelar' ) as $hook ) {
			$this->assertNotEmpty( $acciones[ $hook ] ?? array(), "falta el endpoint {$hook}" );
		}
	}

	/**
	 * El shortcode pinta la plantilla y le pasa la URL de admin-ajax con su nonce.
	 */
	public function test_el_shortcode_pinta_la_plantilla(): void {
		$html = ( new Panel_Reservas() )->shortcode( array() );

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'convoca_enroll_panel_nonce', (string) ( $GLOBALS['_wp_encolados']['localizados']['bdePanel']['nonce'] ?? '' ), 'el JS necesita el nonce de esa acción para poder entrar' );
		$this->assertStringContainsString( 'admin-ajax.php', (string) ( $GLOBALS['_wp_encolados']['localizados']['bdePanel']['ajaxUrl'] ?? '' ) );
	}

	/**
	 * Con demasiados intentos no se prueba ni el email: se corta con 429.
	 */
	public function test_el_limite_de_intentos_del_login(): void {
		$GLOBALS['convoca_test_rate_ok']['panel_login'] = false;
		$_POST['email'] = 'socia@example.test';
		$_POST['codigo'] = 'ABC123';

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_login' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'Demasiados intentos', (string) $respuesta['data']['message'] );
		$this->assertSame( array(), (array) $GLOBALS['convoca_test_get_posts_args'], 'no se consulta la base cuando hay que esperar' );
	}

	/**
	 * Un email que no es un email se rechaza antes de consultar nada.
	 */
	public function test_un_email_invalido_se_rechaza(): void {
		$_POST['email']  = 'no-es-un-email';
		$_POST['codigo'] = 'ABC123';

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_login' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'email válido', (string) $respuesta['data']['message'] );
	}

	/**
	 * Sin código tampoco se busca.
	 */
	public function test_sin_codigo_no_se_busca(): void {
		$_POST['email'] = 'socia@example.test';

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_login' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'código de reserva', (string) $respuesta['data']['message'] );
	}

	/**
	 * Si el email y el código no casan con ninguna reserva, no se enseña nada.
	 */
	public function test_sin_coincidencia_no_se_ensena_nada(): void {
		$_POST['email']  = 'socia@example.test';
		$_POST['codigo'] = 'ZZZZZZ';
		$GLOBALS['convoca_test_get_posts_cola'] = array( array() ); // Ninguna inscripción coincide.

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_login' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'No se encontró ninguna reserva', (string) $respuesta['data']['message'] );
		$this->assertArrayNotHasKey( 'reservas', (array) $respuesta['data'], 'sin coincidencia no viajan datos de nadie' );
	}

	/**
	 * Con la combinación correcta se devuelven TODAS las reservas de ese email, formateadas.
	 */
	public function test_entrar_devuelve_las_reservas_formateadas(): void {
		$_POST['email']  = 'socia@example.test';
		$_POST['codigo'] = 'abc123';
		$GLOBALS['convoca_test_get_posts_cola'] = array(
			array( $this->inscripcion( 501 ) ),   // La que casa con email + código.
			array( $this->inscripcion( 501 ) ),   // Y la lista completa de ese email.
		);
		$GLOBALS['_wp_stores']['posts'][77] = $this->actividad( 77, 'Taller de setas' );
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_actividad_id'    => 77,
			'_convoca_estado'          => 'confirmada',
			'_convoca_codigo_reserva'  => 'ABC123',
			'_convoca_nombre'          => 'Ana',
			'_convoca_es_menor'        => '0',
		);
		$GLOBALS['_wp_stores']['post_meta'][77] = array(
			'_convoca_fecha_inicio' => '2026-11-14 10:00:00',
			'_convoca_ubicacion'    => 'Aula del centro',
		);

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_login' ) );

		$this->assertTrue( $respuesta['ok'] );
		$this->assertSame( 'socia@example.test', $respuesta['data']['email'] );
		$this->assertCount( 1, $respuesta['data']['reservas'] );

		$reserva = $respuesta['data']['reservas'][0];
		$this->assertSame( 501, $reserva['id'] );
		$this->assertSame( 'Taller de setas', $reserva['actividad'] );
		$this->assertSame( 'Aula del centro', $reserva['ubicacion'] );
		$this->assertSame( 'Ana', $reserva['participante'] );
		$this->assertSame( 'ABC123', $reserva['codigo'] );
		$this->assertTrue( $reserva['cancelable'], 'una reserva confirmada se puede cancelar' );

		// El código se normaliza a mayúsculas: se busca con lo que teclea la persona, no con lo que debería.
		$args = (array) $GLOBALS['convoca_test_get_posts_args'][0];
		$this->assertSame( 'inscripcion', $args['post_type'] );
		$this->assertSame( '_convoca_codigo_reserva', $args['meta_query'][1]['key'] );
	}

	/**
	 * Una reserva de una actividad que ya no existe se sigue pudiendo ver, sin inventar fecha ni lugar.
	 */
	public function test_actividad_borrada_no_rompe_el_panel(): void {
		$_POST['email']  = 'socia@example.test';
		$_POST['codigo'] = 'ABC123';
		$GLOBALS['convoca_test_get_posts_cola'] = array( array( $this->inscripcion( 501 ) ), array( $this->inscripcion( 501 ) ) );
		unset( $GLOBALS['_wp_stores']['posts'] ); // La actividad ya no existe: `get_post()` devuelve null.
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_actividad_id'   => 999,
			'_convoca_estado'         => 'cancelada',
			'_convoca_codigo_reserva' => 'ABC123',
			'_convoca_nombre'         => 'Ana',
		);

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_login' ) );

		$this->assertTrue( $respuesta['ok'] );
		$reserva = $respuesta['data']['reservas'][0];
		$this->assertSame( 'Actividad no disponible', $reserva['actividad'] );
		$this->assertSame( '—', $reserva['fecha'] );
		$this->assertSame( '—', $reserva['ubicacion'] );
		$this->assertFalse( $reserva['cancelable'], 'una reserva ya cancelada no se vuelve a cancelar desde el panel' );
	}

	/**
	 * Cancelar también tiene su límite de intentos.
	 */
	public function test_el_limite_de_intentos_al_cancelar(): void {
		$GLOBALS['convoca_test_rate_ok']['panel_cancelar'] = false;
		$_POST['email']  = 'socia@example.test';
		$_POST['codigo'] = 'ABC123';

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_cancelar' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'Demasiados intentos de cancelación', (string) $respuesta['data']['message'] );
	}

	/**
	 * No se cancela la reserva de otra persona aunque se sepa el id.
	 */
	public function test_no_se_cancela_la_reserva_de_otra_persona(): void {
		$_POST['email']          = 'intrusa@example.test';
		$_POST['codigo']         = 'ABC123';
		$_POST['inscripcion_id'] = '501';
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_email'          => 'socia@example.test',
			'_convoca_codigo_reserva' => 'ABC123',
			'_convoca_estado'         => 'confirmada',
		);

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_cancelar' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'No tienes permiso', (string) $respuesta['data']['message'] );
	}

	/**
	 * Ni con el email bueno se cancela si el código no es el de esa inscripción.
	 */
	public function test_no_se_cancela_con_el_codigo_equivocado(): void {
		$_POST['email']          = 'socia@example.test';
		$_POST['codigo']         = 'OTROCODIGO';
		$_POST['inscripcion_id'] = '501';
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_email'          => 'socia@example.test',
			'_convoca_codigo_reserva' => 'ABC123',
			'_convoca_estado'         => 'confirmada',
		);

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_cancelar' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'No tienes permiso', (string) $respuesta['data']['message'] );
	}

	/**
	 * Una reserva ya cancelada lo dice, sin volver a cancelarla.
	 */
	public function test_una_reserva_ya_cancelada_se_avisa(): void {
		$_POST['email']          = 'socia@example.test';
		$_POST['codigo']         = 'ABC123';
		$_POST['inscripcion_id'] = '501';
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_email'          => 'socia@example.test',
			'_convoca_codigo_reserva' => 'ABC123',
			'_convoca_estado'         => 'cancelada',
		);

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_cancelar' ) );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'ya está cancelada', (string) $respuesta['data']['message'] );
	}

	/**
	 * Con email, código y estado correctos se cancela y se devuelve la lista al día.
	 */
	public function test_se_cancela_la_reserva_propia(): void {
		$_POST['email']          = 'Socia@Example.test';
		$_POST['codigo']         = 'abc123';
		$_POST['inscripcion_id'] = '501';
		// La inscripción tiene que existir como post: `cancelar()` no cancela lo que no encuentra.
		$GLOBALS['_wp_stores']['posts'][501]     = $this->inscripcion( 501 );
		$GLOBALS['_wp_stores']['posts'][77]      = $this->actividad( 77, 'Taller de setas' );
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_email'          => 'socia@example.test',
			'_convoca_codigo_reserva' => 'ABC123',
			'_convoca_estado'         => 'confirmada',
			'_convoca_actividad_id'   => 77,
		);
		$GLOBALS['_wp_stores']['post_meta'][77]  = array( '_convoca_fecha_inicio' => '2027-06-01 10:00:00' );
		$GLOBALS['convoca_test_get_posts_cola'] = array( array( $this->inscripcion( 501 ) ) );

		$respuesta = $this->json( array( new Panel_Reservas(), 'ajax_cancelar' ) );

		$this->assertTrue( $respuesta['ok'], 'el email se compara sin distinguir mayúsculas y el código se normaliza' );
		$this->assertStringContainsString( 'cancelada correctamente', (string) $respuesta['data']['message'] );
		$this->assertArrayHasKey( 'reservas', $respuesta['data'], 'se devuelve la lista actualizada' );
	}
}
