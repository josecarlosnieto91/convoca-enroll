<?php
/**
 * Guardar los ajustes.
 *
 * Lo que se escribe aquí lo usa el resto del plugin: el email al que llegan los avisos, las plazas por omisión, si se
 * permite reservar dos veces, el texto de las normas que el socio acepta… Un guardado que se salta una pestaña deja
 * el ajuste viejo, y uno demasiado generoso deja pasar lo que no debe. Por eso se prueba la guarda (nonce y
 * capacidad), el merge con lo ya guardado y la limpieza de cada campo.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Settings;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-settings.php';

/**
 * Guardado de ajustes.
 */
final class AdminSettingsSaveTest extends TestCase {

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
		$this->previo                  = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores']         = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['nonce_invalido']     = false;
		$GLOBALS['convoca_test_caps']  = array( 'manage_options' => true );
		$GLOBALS['_convoca_redirects'] = array();
		$_POST                         = array();
		if ( ! defined( 'CONVOCA_IMAGES_URL' ) ) {
			define( 'CONVOCA_IMAGES_URL', 'https://example.test/i/' );
		}
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['nonce_invalido']     = false;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['_convoca_redirects'] = array();
		$_POST                         = array();
	}

	/**
	 * Los ajustes guardados.
	 *
	 * @return array
	 */
	private function guardados(): array {
		return (array) ( $GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] ?? array() );
	}

	/**
	 * Ejecuta el guardado.
	 *
	 * @return void
	 */
	private function guardar(): void {
		try {
			( new Admin_Settings() )->maybe_save();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			unset( $cortado );
		}
	}

	/**
	 * Siembra el POST de una pestaña.
	 *
	 * @param string $tab  Pestaña.
	 * @param array  $conv Campos.
	 * @return void
	 */
	private function enviar( string $tab, array $conv ): void {
		$_POST = array(
			'convoca_enroll_settings_nonce' => 'nonce',
			'convoca_enroll_active_tab'     => $tab,
			'conv'                          => $conv,
		);
	}

	/**
	 * Sin nonce no se guarda nada.
	 */
	public function test_sin_nonce_no_guarda(): void {
		unset( $_POST['convoca_enroll_settings_nonce'] );
		$_POST = array(
			'convoca_enroll_active_tab' => 'general',
			'conv'                      => array( 'admin_email' => 'malo@example.test' ),
		);

		$this->guardar();

		$this->assertSame( array(), $this->guardados() );
	}

	/**
	 * Con nonce caducado tampoco.
	 */
	public function test_nonce_invalido_no_guarda(): void {
		$GLOBALS['nonce_invalido'] = true;
		$this->enviar( 'general', array( 'admin_email' => 'malo@example.test' ) );

		$this->guardar();

		$this->assertSame( array(), $this->guardados() );
	}

	/**
	 * Sin capacidad, tampoco: los ajustes son de administración.
	 */
	public function test_sin_capacidad_no_guarda(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_options' => false );
		$this->enviar( 'general', array( 'admin_email' => 'malo@example.test' ) );

		$this->guardar();

		$this->assertSame( array(), $this->guardados() );
	}

	/**
	 * General: se limpia cada campo y los interruptores quedan en 0/1 (no «on», no ausente).
	 */
	public function test_guarda_general(): void {
		$this->enviar(
			'general',
			array(
				'admin_email'            => 'avisos@example.test',
				'plazas_por_defecto'     => '35',
				'cancel_window_hours'    => '48',
				'limite_una_reserva'     => 'on',
				'url_panel_reservas'     => 'https://example.test/mis-inscripciones',
				'webhook_url'            => 'javascript:alert(1)',
				'log_retention_days'     => '90',
			)
		);

		$this->guardar();
		$g = $this->guardados();

		$this->assertSame( 'avisos@example.test', $g['admin_email'] );
		$this->assertSame( 35, $g['plazas_por_defecto'] );
		$this->assertSame( 48, $g['cancel_window_hours'] );
		$this->assertSame( 1, $g['limite_una_reserva'], 'marcado' );
		$this->assertSame( 0, $g['permitir_menores'], 'no marcado, pero guardado como 0' );
		$this->assertSame( 'https://example.test/mis-inscripciones', $g['url_panel_reservas'] );
		$this->assertStringNotContainsString( 'javascript:', (string) $g['webhook_url'], 'la URL se limpia' );
	}

	/**
	 * Guardar una pestaña no borra lo guardado por otra: se mezcla.
	 */
	public function test_no_borra_otras_pestanas(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array(
			'admin_email'      => 'antes@example.test',
			'normas_inscripcion' => 'Normas anteriores',
		);

		$this->enviar( 'rgpd', array( 'url_privacidad' => 'https://example.test/privacidad' ) );
		$this->guardar();
		$g = $this->guardados();

		$this->assertSame( 'https://example.test/privacidad', $g['url_privacidad'] );
		$this->assertSame( 'antes@example.test', $g['admin_email'], 'lo de General sigue' );
		$this->assertSame( 'Normas anteriores', $g['normas_inscripcion'], 'y lo de Normas también' );
	}

	/**
	 * Normas: el HTML permitido se queda; un script, no.
	 */
	public function test_normas_limpia_el_html(): void {
		$this->enviar(
			'normas',
			array(
				'normas_inscripcion' => '<p>Llegar puntual</p><script>alert(1)</script>',
				'texto_introduccion' => 'Gracias por venir',
			)
		);

		$this->guardar();
		$g = $this->guardados();

		$this->assertStringContainsString( 'Llegar puntual', $g['normas_inscripcion'] );
		$this->assertStringNotContainsString( '<script', $g['normas_inscripcion'] );
		$this->assertSame( 'Gracias por venir', $g['texto_introduccion'] );
	}

	/**
	 * Mantenimiento: reparar avisa del resultado; cualquier otro valor devuelve al formulario.
	 */
	public function test_mantenimiento(): void {
		$this->enviar( 'general', array( 'admin_email' => 'avisos@example.test' ) );

		$_POST['convoca_enroll_run_maintenance'] = 'otro';
		$this->guardar();

		$this->assertCount( 1, $GLOBALS['_convoca_redirects'], 'vuelve al formulario' );
		$this->assertStringContainsString( 'maint_res=1', (string) $GLOBALS['_convoca_redirects'][0][0] );
	}
}
