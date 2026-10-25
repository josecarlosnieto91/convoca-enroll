<?php
/**
 * Guardar la ficha de una actividad.
 *
 * Es el formulario que se rellena para cada actividad del centro: fechas, plazas, precios, lugar, responsables y los
 * recordatorios. Un campo que se guarde mal deja la actividad con un precio raro o sin sitio, y una fecha de fin
 * anterior a la de inicio rompe los informes. Aquí se prueba el saneado campo a campo y las dos correcciones que hace
 * el guardado (fechas y Google).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Guardado de la ficha.
 */
final class ActividadSaveTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * CPT.
	 *
	 * @var CPT_Actividad
	 */
	private CPT_Actividad $cpt;

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
		$GLOBALS['nonce_invalido']    = false;
		$GLOBALS['convoca_test_caps'] = array( 'edit_post' => true );
		$GLOBALS['_wp_filters']       = array();
		$_POST                        = array();

		$this->cpt = new CPT_Actividad();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		// `save_metabox()` lleva un `static $is_running`: una salida temprana lo deja en false para la siguiente.
		$_POST = array();
		$this->cpt->save_metabox( 61 );

		$GLOBALS['_wp_stores']        = $this->previo;
		$GLOBALS['nonce_invalido']    = false;
		$GLOBALS['convoca_test_caps'] = array();
		$GLOBALS['_wp_filters']       = array();
		$_POST                        = array();
	}

	/**
	 * Envía la ficha con los campos dados.
	 *
	 * @param array $campos Campos (sin el prefijo).
	 * @return void
	 */
	private function enviar( array $campos ): void {
		// **Se simula lo que EMITE el formulario**, `conv_enroll_<clave>`: el metabox publica esos nombres y no
		// `convoca_enroll_*`. La prueba enviaba el prefijo largo y por eso daba verde con el guardado roto
		// (enroll#38).
		$_POST = array( 'convoca_enroll_actividad_nonce' => 'nonce' );
		foreach ( $campos as $clave => $valor ) {
			$_POST[ 'conv_enroll_' . $clave ] = $valor;
		}
	}

	/**
	 * El valor guardado de una meta de la actividad.
	 *
	 * @param string $clave Clave sin prefijo.
	 * @return mixed
	 */
	private function meta( string $clave ) {
		return $GLOBALS['_wp_stores']['post_meta'][61][ '_convoca_' . $clave ] ?? null;
	}

	/**
	 * Cada campo se guarda saneado: la coma decimal pasa a punto y la fecha del formulario pierde la `T`.
	 */
	public function test_guarda_los_campos(): void {
		$this->enviar(
			array(
				'fecha_inicio'       => '2026-11-14T18:00',
				'fecha_fin'          => '2026-11-14T20:30',
				'horas_actividad'    => '2,5',
				'plazas_totales'     => '30',
				'plazas_disponibles' => '12',
				'precio_socio'       => '60,50',
				'precio_socio_dia'   => '15',
				'ubicacion'          => 'Centro Social',
				'requiere_pago'      => '1',
				'responsables'       => array( '7', '9' ),
				'funciones'          => "Abrir\nCerrar",
			)
		);

		$this->cpt->save_metabox( 61 );

		$this->assertSame( '2026-11-14 18:00', $this->meta( 'fecha_inicio' ) );
		$this->assertSame( '2026-11-14 20:30', $this->meta( 'fecha_fin' ) );
		// La meta se lee de la base como texto, igual que en WordPress.
		$this->assertSame( 2.5, (float) $this->meta( 'horas_actividad' ) );
		$this->assertSame( 30, (int) $this->meta( 'plazas_totales' ) );
		$this->assertSame( 12, (int) $this->meta( 'plazas_disponibles' ) );
		$this->assertSame( 60.5, (float) $this->meta( 'precio_socio' ), 'la coma decimal no se pierde' );
		$this->assertSame( 15.0, (float) $this->meta( 'precio_socio_dia' ) );
		$this->assertSame( 'Centro Social', $this->meta( 'ubicacion' ) );
		$this->assertSame( '7,9', $this->meta( 'responsables' ), 'los responsables se guardan como lista' );
		$this->assertStringContainsString( 'Abrir', (string) $this->meta( 'funciones' ) );
	}

	/**
	 * Sin nonce no se guarda nada: ni un campo.
	 */
	public function test_sin_nonce_no_guarda(): void {
		$_POST = array( 'conv_enroll_plazas_totales' => '99' );

		$this->cpt->save_metabox( 61 );

		$this->assertNull( $this->meta( 'plazas_totales' ) );
	}

	/**
	 * Sin permiso sobre la entrada, tampoco.
	 */
	public function test_sin_permiso_no_guarda(): void {
		$GLOBALS['convoca_test_caps'] = array( 'edit_post' => false );
		$this->enviar( array( 'plazas_totales' => '99' ) );

		$this->cpt->save_metabox( 61 );

		$this->assertNull( $this->meta( 'plazas_totales' ) );
	}

	/**
	 * Una fecha de fin anterior a la de inicio se corrige, y se avisa por el mensaje de redirección.
	 */
	public function test_fecha_fin_anterior_se_corrige(): void {
		$this->enviar(
			array(
				'fecha_inicio' => '2026-11-20T10:00',
				'fecha_fin'    => '2026-11-19T10:00',
			)
		);

		$this->cpt->save_metabox( 61 );

		$this->assertSame( '2026-11-20 10:00', $this->meta( 'fecha_fin' ), 'la fin se ajusta a la inicio' );

		$destino = apply_filters( 'redirect_post_location', 'https://example.test/wp-admin/post.php' );
		$this->assertStringContainsString( 'message=convoca_enroll_date_error', (string) $destino, 'y se avisa' );
	}

	/**
	 * Una fecha de fin válida no se toca ni avisa.
	 */
	public function test_fecha_fin_valida_no_se_toca(): void {
		$this->enviar(
			array(
				'fecha_inicio' => '2026-11-20T10:00',
				'fecha_fin'    => '2026-11-20T12:00',
			)
		);

		$this->cpt->save_metabox( 61 );

		$this->assertSame( '2026-11-20 12:00', $this->meta( 'fecha_fin' ) );
		$destino = apply_filters( 'redirect_post_location', 'https://example.test/wp-admin/post.php' );
		$this->assertStringNotContainsString( 'message=', (string) $destino, 'sin aviso' );
	}
}
