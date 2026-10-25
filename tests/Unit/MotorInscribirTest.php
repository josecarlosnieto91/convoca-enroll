<?php
/**
 * Alta de una inscripción: las puertas que cierra y la plaza que ocupa.
 *
 * Todo lo que decide `inscribir()` se ve aquí: a quién se le dice que no (sin nombre, correo con dominio que no
 * existe, DNI inválido, sin consentimiento, edad imposible, ya inscrito) y —lo que de verdad importa— qué pasa con
 * el aforo: con plazas queda `pendiente` (o `pendiente_pago`), y sin plazas **entra en lista de espera** en vez de
 * ocupar una butaca que no hay.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use Convoca\Enroll\CPT_Inscripcion;
use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';

/**
 * Alta.
 */
final class MotorInscribirTest extends TestCase {

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
		$GLOBALS['convoca_test_dns']            = array( 'example.com' );
		$GLOBALS['_wp_insert_args']             = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_dns']            = null;
		$GLOBALS['_wp_insert_args']             = array();
	}

	/**
	 * Siembra la actividad.
	 *
	 * @param array $meta Metas (sin prefijo).
	 * @param int   $id   Id.
	 * @return void
	 */
	private function actividad( array $meta = array(), int $id = 61 ): void {
		$post                        = new \WP_Post();
		$post->ID                    = $id;
		$post->post_type             = 'actividad';
		$post->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;

		$base = array(
			'fecha_inicio'        => gmdate( 'Y-m-d H:i:s', time() + 604800 ),
			'plazas_disponibles'  => 10,
			'requiere_pago'       => false,
		);
		foreach ( array_merge( $base, $meta ) as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][ $id ][ '_convoca_' . $clave ] = $valor;
		}
	}

	/**
	 * Datos válidos del formulario.
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
				'dni'      => '',
				'rgpd'     => '1',
			),
			$extra
		);
	}

	/**
	 * Actividad inexistente.
	 */
	public function test_actividad_inexistente(): void {
		$error = Motor_Inscripcion::inscribir( 999, $this->datos() );

		$this->assertWPErrorCode( 'invalid_activity', $error );
	}

	/**
	 * Actividad que ya pasó.
	 */
	public function test_actividad_terminada(): void {
		$this->actividad( array( 'fecha_inicio' => '2020-01-01 10:00:00' ) );

		$this->assertWPErrorCode( 'activity_ended', Motor_Inscripcion::inscribir( 61, $this->datos() ) );
	}

	/**
	 * El nombre es obligatorio.
	 */
	public function test_sin_nombre(): void {
		$this->actividad();

		$this->assertWPErrorCode( 'missing_nombre', Motor_Inscripcion::inscribir( 61, $this->datos( array( 'nombre' => '' ) ) ) );
	}

	/**
	 * Correo con forma inválida.
	 */
	public function test_email_invalido(): void {
		$this->actividad();

		$this->assertWPErrorCode( 'invalid_email', Motor_Inscripcion::inscribir( 61, $this->datos( array( 'email' => 'ana-arroba-nada' ) ) ) );
	}

	/**
	 * Correo con forma válida pero dominio que no existe: nadie recibiría el código de reserva.
	 */
	public function test_dominio_sin_dns(): void {
		$this->actividad();

		$this->assertWPErrorCode( 'invalid_email_domain', Motor_Inscripcion::inscribir( 61, $this->datos( array( 'email' => 'ana@example.test' ) ) ) );
	}

	/**
	 * Sin consentimiento no hay inscripción.
	 */
	public function test_sin_consentimiento(): void {
		$this->actividad();

		$this->assertWPErrorCode( 'missing_rgpd', Motor_Inscripcion::inscribir( 61, $this->datos( array( 'rgpd' => '' ) ) ) );
	}

	/**
	 * Un «menor» de 30 años no es un menor: el dato es un error del formulario.
	 */
	public function test_edad_imposible(): void {
		$this->actividad();

		$error = Motor_Inscripcion::inscribir(
			61,
			$this->datos(
				array(
					'es_menor'          => '1',
					'nombre_participante' => 'Lucía',
					'edad_participante' => 30,
				)
			)
		);

		$this->assertWPErrorCode( 'invalid_edad', $error );
	}

	/**
	 * DNI que no cuadra con la letra: se rechaza antes de crear nada.
	 */
	public function test_dni_invalido(): void {
		$this->actividad();

		$this->assertWPErrorCode( 'invalid_dni', Motor_Inscripcion::inscribir( 61, $this->datos( array( 'dni' => '12345678A' ) ) ) );
	}

	/**
	 * Ya inscrito en esa actividad.
	 */
	public function test_duplicado(): void {
		$this->actividad();
		$existente               = new \WP_Post();
		$existente->ID           = 70;
		$existente->post_type    = 'inscripcion';
		$GLOBALS['convoca_test_posts'] = array( $existente );
		// Las metas que la consulta filtra; sin ellas el doble estricto no ve el duplicado (enroll#44).
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => '61',
			'_convoca_email'        => 'ana@example.com',
			'_convoca_estado'       => 'confirmada',
		);

		$this->assertWPErrorCode( 'duplicate', Motor_Inscripcion::inscribir( 61, $this->datos() ) );
	}

	/**
	 * Con plazas: `pendiente`, y la plaza se descuenta.
	 */
	public function test_con_plaza(): void {
		$this->actividad();

		$id = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertIsInt( $id, 'devuelve el id de la inscripción' );
		$this->assertSame( 'pendiente', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado'] );
		$this->assertSame( 'no_registrada', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_asistencia'] );
		$this->assertNotEmpty( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_codigo_reserva'] );

		$descuento = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringContainsString( 'meta_value AS SIGNED) - 1', $descuento, 'la plaza se descuenta en la misma transacción' );
		$this->assertStringContainsString( 'FOR UPDATE', $descuento, 'la actividad se bloquea: dos altas a la vez no pueden comer la misma plaza' );
	}

	/**
	 * Actividad de pago: queda `pendiente_pago`, no `pendiente`.
	 */
	public function test_con_pago(): void {
		$this->actividad( array( 'requiere_pago' => true ) );

		$id = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertSame( 'pendiente_pago', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado'] );
	}

	/**
	 * Sin plazas: **lista de espera**. Ocupar una butaca que no hay es el fallo que se nota en el mostrador.
	 */
	public function test_sin_plazas_va_a_la_espera(): void {
		$this->actividad( array( 'plazas_disponibles' => 0 ) );

		$id = Motor_Inscripcion::inscribir( 61, $this->datos() );

		$this->assertSame( 'lista_espera', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_estado'] );

		$consultas = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringNotContainsString( 'CAST(meta_value AS SIGNED) - 1', $consultas, 'sin plaza no se descuenta nada' );
	}

	/**
	 * Un menor se guarda con su nombre, su edad y quién responde de él.
	 */
	public function test_menor(): void {
		$this->actividad();

		$id = Motor_Inscripcion::inscribir(
			61,
			$this->datos(
				array(
					'es_menor'            => '1',
					'nombre_participante' => 'Lucía',
					'edad_participante'   => 9,
				)
			)
		);

		$meta = $GLOBALS['_wp_stores']['post_meta'][ $id ];
		$this->assertSame( 'Lucía', $meta['_convoca_nombre_participante'] );
		$this->assertSame( '9', (string) $meta['_convoca_edad_participante'] );
		$this->assertSame( 'Ana López', $meta['_convoca_nombre_responsable'], 'quien inscribe responde' );
	}

	/**
	 * Afirma el código del error.
	 *
	 * @param string $esperado Código.
	 * @param mixed  $error    Resultado.
	 * @return void
	 */
	private function assertWPErrorCode( string $esperado, $error ): void {
		$this->assertInstanceOf( \WP_Error::class, $error, 'se esperaba un error, no una inscripción' );
		$this->assertSame( $esperado, $error->get_error_code() );
	}
}
