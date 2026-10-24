<?php
/**
 * Cambiar estado y marcar check-in desde la lista de inscripciones.
 *
 * Son dos botones que se usan en el mostrador: cambiar a «confirmada»/«cancelada» y marcar quién ha venido. Las
 * guardas son lo importante: quien no gestiona el check-in de esa actividad **no puede** tocarla, y no vale con estar
 * dado de alta: se comprueba la actividad concreta de la inscripción.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Page;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-page.php';

/**
 * Ajaxes de la lista.
 */
final class AdminPageAjaxTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Pantalla.
	 *
	 * @var Admin_Page
	 */
	private Admin_Page $pagina;

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
		$GLOBALS['_wp_json']              = array();
		$GLOBALS['nonces_invalidos']      = array();
		$GLOBALS['convoca_test_caps']     = array( 'convoca_manage_checkin' => true, 'manage_options' => true );
		$GLOBALS['convoca_test_user_can'] = array();
		$_POST                            = array();

		// `set_asistencia()` comprueba el tipo de post y **que exista**: se siembra la inscripción.
		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 410;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['_wp_stores']['posts'][410] = $inscripcion;
		$GLOBALS['_wp_stores']['post_meta'][410] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => 'pendiente',
			'_convoca_asistencia'   => '',
		);

		$this->pagina = new Admin_Page();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']            = $this->previo;
		$GLOBALS['_wp_json']              = array();
		$GLOBALS['nonces_invalidos']      = array();
		$GLOBALS['convoca_test_caps']     = array();
		$GLOBALS['convoca_test_user_can'] = array();
		$GLOBALS['convoca_test_post_types'] = array();
		$_POST                            = array();
	}

	/**
	 * Llama un ajax y devuelve la respuesta.
	 *
	 * @param string $metodo Método.
	 * @return array|null
	 */
	private function llamar( string $metodo ): ?array {
		try {
			$this->pagina->{$metodo}();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			return is_array( $cortado->respuesta ) ? $cortado->respuesta : array( 'cortado' => $cortado->respuesta );
		}

		return null;
	}

	/**
	 * El valor de una meta.
	 *
	 * @param int    $id  Inscripción.
	 * @param string $key Clave.
	 * @return string
	 */
	private function meta( int $id, string $key ): string {
		return (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ][ '_convoca_' . $key ] ?? '' );
	}

	/**
	 * Cambiar de estado: se guarda el estado y el pago.
	 */
	public function test_cambiar_estado(): void {
		$_POST = array(
			'inscripcion_id' => '410',
			'estado'         => 'lista_espera',
			'pagado'         => '1',
		);

		$respuesta = $this->llamar( 'ajax_change_state' );

		$this->assertTrue( $respuesta['ok'] );
		$this->assertSame( 'lista_espera', $this->meta( 410, 'estado' ) );
		$this->assertSame( '1', $this->meta( 410, 'pagado' ) );
	}

	/**
	 * Un estado que no existe no se guarda: ni el estado ni el pago.
	 */
	public function test_estado_invalido_no_guarda(): void {
		$_POST = array(
			'inscripcion_id' => '410',
			'estado'         => 'inventado',
		);

		$respuesta = $this->llamar( 'ajax_change_state' );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'Estado no válido', (string) $respuesta['data'] );
		$this->assertSame( 'pendiente', $this->meta( 410, 'estado' ), 'no se toca' );
	}

	/**
	 * Sin capacidad de check-in ni de administración, manda la actividad: si no es suya, no se toca.
	 */
	public function test_sin_permiso_sobre_la_actividad(): void {
		$GLOBALS['convoca_test_caps']           = array( 'convoca_manage_checkin' => false, 'manage_options' => false );
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_responsables' => '7,9' );
		$_POST                                  = array(
			'inscripcion_id' => '410',
			'estado'         => 'lista_espera',
		);

		$respuesta = $this->llamar( 'ajax_change_state' );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'Sin permisos', (string) $respuesta['data'] );
		$this->assertSame( 'pendiente', $this->meta( 410, 'estado' ), 'no se guarda nada' );
	}

	/**
	 * Check-in: alterna entre «si» y «no», y devuelve la etiqueta que ve el monitor.
	 */
	public function test_alternar_checkin(): void {
		$_POST = array( 'inscripcion_id' => '410' );

		$this->llamar( 'ajax_toggle_checkin' );
		$this->assertSame( 'si', $this->meta( 410, 'asistencia' ) );

		$this->llamar( 'ajax_toggle_checkin' );
		$this->assertSame( 'no', $this->meta( 410, 'asistencia' ), 'vuelve a «no»' );
	}

	/**
	 * Check-in: la etiqueta que se devuelve acompaña al estado nuevo.
	 */
	public function test_checkin_devuelve_la_etiqueta(): void {
		$_POST = array( 'inscripcion_id' => '410' );

		$respuesta = $this->llamar( 'ajax_toggle_checkin' );

		$this->assertSame( 'si', $respuesta['data']['asistencia'] );
		$this->assertSame( 'Registrado', $respuesta['data']['label'] );
	}

	/**
	 * Check-in sin id: error claro, no un 200 mudo.
	 */
	public function test_checkin_sin_id(): void {
		$_POST = array();

		$respuesta = $this->llamar( 'ajax_toggle_checkin' );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertStringContainsString( 'no válido', (string) $respuesta['data'] );
	}

	/**
	 * Check-in de una actividad ajena: no se toca.
	 */
	public function test_checkin_sin_permiso(): void {
		$GLOBALS['convoca_test_caps']           = array( 'convoca_manage_checkin' => false, 'manage_options' => false );
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_responsables' => '7,9' );
		$_POST                                  = array( 'inscripcion_id' => '410' );

		$respuesta = $this->llamar( 'ajax_toggle_checkin' );

		$this->assertFalse( $respuesta['ok'] );
		$this->assertSame( '', $this->meta( 410, 'asistencia' ) );
	}
}
