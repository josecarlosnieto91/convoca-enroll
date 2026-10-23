<?php
/**
 * Marcar asistencia desde el panel del monitor (ajax).
 *
 * Es el gesto que más se repite el día de la actividad: el monitor toca el botón y la asistencia queda guardada. Lo
 * que se prueba aquí son las guardas, porque un fallo deja la asistencia mal para siempre: sin permiso no se escribe,
 * y **no ser responsable de esa actividad concreta** tampoco basta con estar dado de alta.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Monitor_CRM;
use Convoca\Enroll\CPT_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-monitor-crm.php';

/**
 * Ajax de asistencia.
 */
final class MonitorCrmAsistenciaTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Panel.
	 *
	 * @var Admin_Monitor_CRM
	 */
	private Admin_Monitor_CRM $panel;

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo        = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['_wp_json']              = array();
		$GLOBALS['nonces_invalidos']      = array();
		$GLOBALS['convoca_test_caps']     = array( 'manage_inscripciones' => true );
		$GLOBALS['convoca_test_user_can'] = array();
		$_POST                            = array();

		// La inscripción 410 pertenece a la actividad 61; la 411 a la 62.
		$GLOBALS['_wp_stores']['post_meta'][410] = array( '_convoca_actividad_id' => 61 );
		$GLOBALS['_wp_stores']['post_meta'][411] = array( '_convoca_actividad_id' => 62 );

		$this->panel = new Admin_Monitor_CRM();
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
		$_POST                            = array();
	}

	/**
	 * Llama al ajax y devuelve lo que respondió (o `null` si no llegó a responder).
	 *
	 * @return array|null
	 */
	private function marcar(): ?array {
		try {
			$this->panel->ajax_mark_attendance();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			return is_array( $cortado->respuesta ) ? $cortado->respuesta : array( 'cortado' => $cortado->respuesta );
		}

		return null;
	}

	/**
	 * La asistencia guardada para una inscripción.
	 *
	 * @param int $id Inscripción.
	 * @return string
	 */
	private function asistencia( int $id ): string {
		return (string) ( $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_asistencia'] ?? '' );
	}

	/**
	 * Con permiso y responsabilidad, se guarda y se responde con el estado.
	 */
	public function test_marcar_asistencia(): void {
		$_POST = array(
			'id'     => '410',
			'status' => 'si',
		);

		$respuesta = $this->marcar();

		$this->assertSame( 'si', $this->asistencia( 410 ), 'la asistencia queda guardada' );
		$this->assertTrue( $respuesta['ok'] );
		$this->assertSame( 'si', $respuesta['data']['status'] );
	}

	/**
	 * Sin la capacidad no se guarda nada: ni el monitor ni un admin sin ese permiso.
	 */
	public function test_sin_permiso_no_escribe(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => false );
		$_POST                        = array(
			'id'     => '410',
			'status' => 'si',
		);

		$respuesta = $this->marcar();

		$this->assertSame( '', $this->asistencia( 410 ), 'no se toca la asistencia' );
		$this->assertFalse( $respuesta['ok'] );
	}

	/**
	 * Ni con la capacidad: si no es responsable **de esa** actividad, no se escribe.
	 *
	 * El monitor de la actividad 61 no puede marcar en la 62.
	 */
	public function test_no_responsable_no_escribe(): void {
		$GLOBALS['convoca_test_user_can'] = array( 'manage_options' => false );
		$GLOBALS['_wp_stores']['post_meta'][62] = array(
			// Los responsables se guardan como lista separada por comas, no como array.
			'_convoca_responsables' => '7,9',
		);
		// El usuario de la sesión es el 1: no está entre los responsables de la 62.
		$GLOBALS['_wp_stores']['usuarios'] = array( 1 => 'monitor' );
		$_POST                            = array(
			'id'     => '411',
			'status' => 'no',
		);

		$respuesta = $this->marcar();

		$this->assertSame( '', $this->asistencia( 411 ), 'no se toca la asistencia de otra actividad' );
		$this->assertFalse( $respuesta['ok'] );
	}

	/**
	 * Sin nonce válido no se ejecuta nada.
	 */
	public function test_sin_nonce_no_escribe(): void {
		$GLOBALS['nonces_invalidos']['convoca_enroll_attendance_nonce'] = true;
		$_POST = array(
			'id'     => '410',
			'status' => 'si',
		);

		$respuesta = $this->marcar();

		$this->assertSame( '', $this->asistencia( 410 ) );
		$this->assertSame( 'nonce', $respuesta['cortado'] ?? null );
	}

	/**
	 * Quien vuelve a marcar sobrescribe: se puede corregir el error del día anterior.
	 */
	public function test_se_puede_corregir(): void {
		$_POST = array(
			'id'     => '410',
			'status' => 'si',
		);
		$this->marcar();

		$_POST = array(
			'id'     => '410',
			'status' => 'no',
		);
		$this->marcar();

		$this->assertSame( 'no', $this->asistencia( 410 ) );
	}
}
