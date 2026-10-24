<?php
/**
 * Reglas del motor de inscripciones que no tocan la base de datos.
 *
 * Aquí viven las decisiones que se notan en el mostrador: si una cancelación entra dentro de plazo, quién pasa antes
 * en la lista de espera, si alguien ya tiene reserva o si un DNI es válido. Son funciones puras: se prueban con una
 * fecha fija y sin efectos.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Inscripcion;
use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';

/**
 * Reglas.
 */
final class MotorReglasTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

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
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		unset( $GLOBALS['__prioridad'] );
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		unset( $GLOBALS['__prioridad'] );
	}

	/**
	 * ¿La cancelación es tardía? Con 48 h de margen, no; con 2 h, sí.
	 */
	public function test_cancelacion_tardia(): void {
		$ahora  = strtotime( '2026-11-14 10:00:00' );
		$inicio = '2026-11-16 10:00:00'; // 48 h después.

		$this->assertFalse( Motor_Inscripcion::es_cancelacion_tardia( $inicio, 24, $ahora ), '48 h > 24 h: entra' );
		$this->assertTrue( Motor_Inscripcion::es_cancelacion_tardia( '2026-11-14 12:00:00', 24, $ahora ), '2 h < 24 h: tardía' );

		$this->assertFalse( Motor_Inscripcion::es_cancelacion_tardia( '', 24, $ahora ), 'sin fecha no es tardía' );
		$this->assertFalse( Motor_Inscripcion::es_cancelacion_tardia( 'no es fecha', 24, $ahora ), 'ilegible, tampoco' );
	}

	/**
	 * La ventana: un privilegiado puede cancelar siempre; el resto, no si es tardía.
	 */
	public function test_la_ventana_de_cancelacion(): void {
		$ahora = strtotime( '2026-11-14 10:00:00' );

		$this->assertTrue( Motor_Inscripcion::validar_ventana_cancelacion( '2026-11-14 12:00:00', 24, $ahora, true ), 'admin: puede' );
		$this->assertTrue( Motor_Inscripcion::validar_ventana_cancelacion( '2026-11-16 10:00:00', 24, $ahora, false ), 'con margen: puede' );

		$error = Motor_Inscripcion::validar_ventana_cancelacion( '2026-11-14 12:00:00', 24, $ahora, false );

		$this->assertInstanceOf( \WP_Error::class, $error, 'sin margen: no puede' );
		$this->assertSame( 'cancel_window_closed', $error->get_error_code() );
		$this->assertStringContainsString( '24 horas', $error->get_error_message(), 'dice cuántas horas hacen falta' );
	}

	/**
	 * La lista de espera: primero los socios; dentro de cada grupo, quien llegó antes.
	 */
	public function test_orden_de_la_lista_de_espera(): void {
		$socio_tarde = (object) array( 'ID' => 1, 'post_date' => '2026-11-02 10:00:00' );
		$socio_pronto = (object) array( 'ID' => 2, 'post_date' => '2026-11-01 10:00:00' );
		$normal_pronto = (object) array( 'ID' => 3, 'post_date' => '2026-10-30 10:00:00' );
		$normal_tarde  = (object) array( 'ID' => 4, 'post_date' => '2026-11-05 10:00:00' );

		$GLOBALS['_wp_stores']['post_meta'][1] = array( '_convoca_es_socio' => '1' );
		$GLOBALS['_wp_stores']['post_meta'][2] = array( '_convoca_es_socio' => '1' );

		$orden = Motor_Inscripcion::sort_waitlist( array( $normal_pronto, $socio_tarde, $normal_tarde, $socio_pronto ) );

		$this->assertSame( array( 2, 1, 3, 4 ), array_map( static fn( $p ) => $p->ID, $orden ), 'socios primero, y por fecha' );
	}

	/**
	 * Un socio solo tiene prioridad si su ficha lo dice **y** el contrato del núcleo lo concede.
	 */
	public function test_socio_con_prioridad(): void {
		$GLOBALS['_wp_stores']['post_meta'][1] = array( '_convoca_es_socio' => '0' );
		$this->assertFalse( Motor_Inscripcion::es_socio_activo( 1 ), 'sin es_socio no hay prioridad' );

		$GLOBALS['_wp_stores']['post_meta'][1]['_convoca_es_socio'] = '1';
		$this->assertTrue( Motor_Inscripcion::es_socio_activo( 1 ) );

		$GLOBALS['__prioridad'] = false;
		$this->assertFalse( Motor_Inscripcion::es_socio_activo( 1 ), 'la cuota manda: sin cuota al día, no' );
	}

	/**
	 * La regla de una reserva por persona: sin reservas entra; con una, no.
	 */
	public function test_limite_de_reservas(): void {
		$this->assertTrue( Motor_Inscripcion::check_limite_reservas( 'ana@example.test' ), 'sin reservas, entra' );

		$GLOBALS['convoca_test_posts'] = array( (object) array( 'ID' => 70 ) );
		$error                         = Motor_Inscripcion::check_limite_reservas( 'ana@example.test' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'limite_reservas', $error->get_error_code() );
		$this->assertStringContainsString( 'una única reserva', $error->get_error_message() );
	}

	/**
	 * El DNI/NIE: formato y letra.
	 */
	public function test_el_dni(): void {
		$this->assertTrue( Motor_Inscripcion::validar_dni( '12345678Z' ), 'DNI con la letra correcta' );
		$this->assertFalse( Motor_Inscripcion::validar_dni( '12345678A' ), 'letra equivocada' );
		$this->assertFalse( Motor_Inscripcion::validar_dni( 'nada' ) );
	}

	/**
	 * Buscar por código: lo que devuelve la consulta, y `null` si no hay.
	 */
	public function test_buscar_por_codigo(): void {
		$this->assertNull( Motor_Inscripcion::buscar_por_codigo( 'ana@example.test', 'AAAABBBB' ), 'sin resultados, null' );

		$inscripcion                         = new \WP_Post();
		$inscripcion->ID                     = 70;
		$GLOBALS['convoca_test_posts']       = array( $inscripcion );

		$this->assertSame( 70, Motor_Inscripcion::buscar_por_codigo( 'ana@example.test', 'aaaabbbb' )->ID, 'el código se busca en mayúsculas' );
	}

	/**
	 * Las reservas de un correo: sin canceladas y con su límite.
	 */
	public function test_reservas_por_email(): void {
		Motor_Inscripcion::get_reservas_por_email( 'ana@example.test' );

		$args = end( $GLOBALS['convoca_test_get_posts_args'] );
		$this->assertSame( 50, $args['posts_per_page'] );
		$this->assertSame( 'DESC', $args['order'], 'de la más reciente a la más antigua' );

		$meta = $args['meta_query'];
		$this->assertSame( 'ana@example.test', $meta[0]['value'], 'por el correo, saneado' );
		$this->assertSame( 'cancelada', $meta[1]['value'] );
		$this->assertSame( '!=', $meta[1]['compare'], 'las canceladas no cuentan' );
	}
}
