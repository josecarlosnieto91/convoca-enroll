<?php
/**
 * Los caminos que solo se recorren cuando algo va mal a mitad.
 *
 * Carreras de concurrencia (otra petición confirmó su fila entre la comprobación y el alta), el alta que no se puede
 * crear, la plaza que se agota en el último instante y la transacción que revienta. Son las ramas que nadie prueba a
 * mano y las que dejan datos a medias si están mal: cada una tiene que hacer `ROLLBACK` y devolver un error, no una
 * inscripción a medio escribir.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';

/**
 * Errores y carreras.
 */
final class MotorErroresTest extends TestCase {

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
			'queries'    => array(),
		);
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_get_posts_cola'] = null;
		$GLOBALS['convoca_test_dns']            = array( 'example.com' );
		$GLOBALS['convoca_test_caps']           = array(
			'manage_options'        => false,
			'convoca_manage_enroll' => false,
		);
		$GLOBALS['_wp_insert_lanza']            = false;
		$GLOBALS['_wp_insert_falla']            = false;
		$GLOBALS['convoca_test_query_lanza']    = false;
		$GLOBALS['convoca_test_query_retorno']  = array();
		$GLOBALS['__identidad']                 = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_get_posts_cola'] = null;
		$GLOBALS['convoca_test_dns']            = null;
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['_wp_insert_lanza']            = false;
		$GLOBALS['_wp_insert_falla']            = false;
		$GLOBALS['convoca_test_query_lanza']    = false;
		$GLOBALS['convoca_test_query_retorno']  = array();
		$GLOBALS['__identidad']                 = null;
	}

	/**
	 * Siembra la actividad.
	 *
	 * @param array $meta Metas (sin prefijo).
	 * @return void
	 */
	private function actividad( array $meta = array() ): void {
		$post                        = new \WP_Post();
		$post->ID                    = 61;
		$post->post_type             = 'actividad';
		$post->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $post;

		$base = array(
			'fecha_inicio'       => gmdate( 'Y-m-d H:i:s', time() + 604800 ),
			'plazas_disponibles' => 10,
		);
		foreach ( array_merge( $base, $meta ) as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][61][ '_convoca_' . $clave ] = $valor;
		}
	}

	/**
	 * Datos válidos.
	 *
	 * @param array $extra Extra.
	 * @return array
	 */
	private function datos( array $extra = array() ): array {
		return array_merge(
			array(
				'nombre'   => 'Ana López',
				'email'    => 'ana@example.com',
				'telefono' => '600000000',
				'rgpd'     => '1',
			),
			$extra
		);
	}

	/**
	 * Consultas lanzadas.
	 *
	 * @return string
	 */
	private function consultas(): string {
		return implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
	}

	/**
	 * Un socio con la sesión abierta se inscribe con los datos de su ficha, no con los del formulario.
	 */
	public function test_socio_autenticado(): void {
		$this->actividad();
		$GLOBALS['__identidad'] = array(
			'autenticado' => true,
			'member_id'   => 5,
			'datos'       => array(
				'name'  => 'Socia Ejemplo',
				'email' => 'socia@example.com',
				'phone' => '611111111',
				'dni'   => '12345678Z',
			),
		);

		$id = Motor_Inscripcion::inscribir( 61, $this->datos( array( 'nombre' => 'Otro Nombre' ) ) );

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( 'Socia Ejemplo', $meta['_convoca_nombre'], 'manda la ficha' );
		$this->assertSame( '1', $meta['_convoca_es_socio'] );
		$this->assertSame( 'socio', $meta['_convoca_tipo_inscripcion'] );
	}

	/**
	 * Otra petición confirmó su fila entre la comprobación previa y el alta: la de dentro de la transacción lo ve.
	 */
	public function test_carrera_de_duplicado(): void {
		$this->actividad();
		$GLOBALS['_wp_stores']['col'] = array( 99 );

		$error = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'duplicate', $error->get_error_code() );
		$this->assertStringContainsString( 'ROLLBACK', $this->consultas() );
	}

	/**
	 * Lo mismo por DNI: la primera lectura (correo) va limpia y la segunda encuentra la fila.
	 */
	public function test_carrera_de_duplicado_por_dni(): void {
		$this->actividad();
		$GLOBALS['_wp_stores']['col_cola'] = array( array(), array( 99 ) );

		$error = Motor_Inscripcion::inscribir(
			61,
			$this->datos( array( 'dni' => '12345678Z' ) )
		);

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'duplicate_dni', $error->get_error_code() );
		$this->assertStringContainsString( 'ROLLBACK', $this->consultas() );
	}

	/**
	 * No se pudo crear la inscripción: error, y nada a medias.
	 */
	public function test_alta_fallida(): void {
		$this->actividad();
		$GLOBALS['_wp_insert_falla'] = true;

		$error = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'insert_failed', $error->get_error_code() );
		$this->assertStringContainsString( 'ROLLBACK', $this->consultas() );
	}

	/**
	 * La plaza se agotó justo antes del descuento: no se crea la inscripción.
	 */
	public function test_sin_plaza_en_el_ultimo_instante(): void {
		$this->actividad();
		$GLOBALS['convoca_test_query_retorno'] = array( 'CAST(meta_value AS SIGNED) - 1' => 0 );

		$error = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'no_slots', $error->get_error_code() );
		$this->assertStringContainsString( 'ROLLBACK', $this->consultas() );
	}

	/**
	 * La transacción revienta: `ROLLBACK` y error, nunca una excepción hacia arriba.
	 */
	public function test_la_transaccion_revienta(): void {
		$this->actividad();
		$GLOBALS['_wp_insert_lanza'] = true;

		$error = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'inscription_failed', $error->get_error_code() );
		$this->assertStringContainsString( 'ROLLBACK', $this->consultas() );
	}

	/**
	 * Cancelar también recoge el fallo de la transacción.
	 */
	public function test_cancelar_revienta(): void {
		$this->actividad();
		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => 'confirmada',
		);
		$GLOBALS['convoca_test_query_lanza'] = 'CAST(meta_value AS SIGNED)';

		$error = Motor_Inscripcion::cancelar( 70 );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'cancel_failed', $error->get_error_code() );
	}

	/**
	 * Confirmar también.
	 */
	public function test_confirmar_revienta(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => 'lista_espera',
		);
		$GLOBALS['convoca_test_query_lanza'] = 'CAST(meta_value AS SIGNED)';

		$error = Motor_Inscripcion::confirmar( 70 );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'confirm_failed', $error->get_error_code() );
	}

	/**
	 * Con el límite de una reserva activa por persona, la segunda se rechaza.
	 */
	public function test_limite_de_reservas(): void {
		$this->actividad();
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'limite_una_reserva' => 1 );

		$previa                        = new \WP_Post();
		$previa->ID                    = 80;
		$previa->post_type             = 'inscripcion';
		$GLOBALS['convoca_test_posts'] = array( $previa );
		// Las metas que la consulta **filtra**: `actividad_id`, `email` y `estado`. Sin ellas el doble estricto
		// no ve la reserva previa y el límite no se aplica (enroll#44).
		$GLOBALS['_wp_stores']['post_meta'][80] = array(
			'_convoca_actividad_id' => '61',
			'_convoca_email'        => 'ana@example.com',
			'_convoca_estado'       => 'confirmada',
		);

		$error = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'limite_reservas', $error->get_error_code() );
	}

	/**
	 * El DNI ya está inscrito en esa actividad: se ve antes de abrir la transacción.
	 */
	public function test_duplicado_por_dni_previo(): void {
		$this->actividad();
		$previa                        = new \WP_Post();
		$previa->ID                    = 80;
		$previa->post_type             = 'inscripcion';
		// El correo va limpio (no hay duplicado por correo); el DNI sí está.
		$GLOBALS['convoca_test_get_posts_cola'] = array( array(), array( $previa ) );

		$error = Motor_Inscripcion::inscribir( 61, $this->datos( array( 'dni' => '12345678Z' ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'duplicate_dni', $error->get_error_code() );
		$this->assertStringNotContainsString( 'START TRANSACTION', $this->consultas(), 'ni se abre la transacción' );
	}

	/**
	 * Un estado de asistencia que no existe se rechaza, no se guarda tal cual.
	 */
	public function test_asistencia_invalida(): void {
		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;

		$error = Motor_Inscripcion::set_asistencia( 70, 'quizas' );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_state', $error->get_error_code() );
	}

	/**
	 * Regenerar el token de check-in invalida el anterior y devuelve uno nuevo.
	 */
	public function test_regenerar_token(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array( '_convoca_checkin_token' => 'viejo' );

		$nuevo = Motor_Inscripcion::regenerar_token_checkin( 70 );

		$this->assertNotEmpty( $nuevo );
		$this->assertNotSame( 'viejo', $nuevo );
		$this->assertSame( $nuevo, $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_token'] );
	}
}
