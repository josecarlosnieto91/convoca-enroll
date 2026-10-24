<?php
/**
 * Google Calendar: sincronizar el evento y generar el ICS.
 *
 * Dos caminos que se usan de verdad: al publicar una actividad se crea o actualiza su evento (sin duplicarlo), y
 * cualquiera puede bajarse el `.ics` para ponerlo en su calendario. Las guardas importan: un borrador, un autoguardado
 * o una actividad ajena **no** pueden tocar el calendario de la organización.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Google_Calendar;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-client.php';
require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-sdk.php';
require_once dirname( __DIR__, 2 ) . '/includes/Google_Calendar.php';

/**
 * Calendario.
 */
final class GoogleCalendarTest extends TestCase {

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
		$GLOBALS['convoca_test_caps']      = array( 'edit_post' => true );
		$GLOBALS['__sdk']                  = array();
		$GLOBALS['__sdk_llamadas']         = array();

		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$actividad->post_content            = '<p>Salida al monte</p>';
		$actividad->post_status             = 'publish';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'    => '2026-11-14 20:00:00',
			'_convoca_ubicacion'    => 'Centro Social',
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['__sdk']              = null;
		$GLOBALS['__sdk_llamadas']     = array();
	}

	/**
	 * Credenciales del calendario.
	 *
	 * @return void
	 */
	private function configurar(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array(
			'google_calendar_client_id'     => 'cliente',
			'google_calendar_client_secret' => 'secreto',
			'google_calendar_refresh_token' => 'refresco',
			'google_calendar_id'            => 'cal@example.test',
		);
	}

	/**
	 * Llamadas hechas al SDK.
	 *
	 * @return array
	 */
	private function llamadas(): array {
		return array_column( (array) $GLOBALS['__sdk_llamadas'], 0 );
	}

	/**
	 * Sin credenciales no hay enlace de autorización.
	 */
	public function test_sin_credenciales_no_hay_url(): void {
		$this->assertSame( '', ( new Google_Calendar() )->get_auth_url() );
	}

	/**
	 * Con credenciales hay URL y se guarda el `state` de la sesión.
	 */
	public function test_la_url_de_autorizacion(): void {
		$this->configurar();

		$url = ( new Google_Calendar() )->get_auth_url();

		$this->assertStringContainsString( 'accounts.google.com', $url );
		$this->assertNotEmpty( $GLOBALS['_wp_stores']['transients']['convoca_enroll_oauth_state_1'] ?? null, 'se guarda el state' );
	}

	/**
	 * Un borrador no se publica en el calendario.
	 */
	public function test_el_borrador_no_sincroniza(): void {
		$this->configurar();
		$post               = $GLOBALS['_wp_stores']['posts'][61];
		$post->post_status  = 'draft';

		( new Google_Calendar() )->sync_on_save( 61, $post, true );

		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Sin permiso sobre la actividad, no se toca el calendario.
	 */
	public function test_sin_permiso_no_sincroniza(): void {
		$this->configurar();
		$GLOBALS['convoca_test_caps']['edit_post'] = false;

		( new Google_Calendar() )->sync_on_save( 61, $GLOBALS['_wp_stores']['posts'][61], true );

		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Con la sincronización apagada en la ficha, no se hace nada.
	 */
	public function test_apagada_en_la_ficha(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_calendar_sync'] = '0';

		( new Google_Calendar() )->sync_on_save( 61, $GLOBALS['_wp_stores']['posts'][61], true );

		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Sin ajuste global de sincronización automática, tampoco.
	 */
	public function test_sin_sincronizacion_automatica(): void {
		$this->configurar();

		( new Google_Calendar() )->sync_on_save( 61, $GLOBALS['_wp_stores']['posts'][61], true );

		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Con todo en orden, el evento se crea.
	 */
	public function test_sincroniza_al_guardar(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings']['google_calendar_auto_sync'] = 1;

		( new Google_Calendar() )->sync_on_save( 61, $GLOBALS['_wp_stores']['posts'][61], true );

		$this->assertContains( 'evento_crear', $this->llamadas() );
		$this->assertSame( 'EV-1', $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_event_id'] );
	}

	/**
	 * Si ya tiene evento, se actualiza en vez de crear otro.
	 */
	public function test_actualiza_el_evento_existente(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_event_id'] = 'EV-VIEJO';

		( new Google_Calendar() )->sync_event( 61 );

		$this->assertContains( 'evento_actualizar', $this->llamadas() );
		$this->assertNotContains( 'evento_crear', $this->llamadas() );
	}

	/**
	 * El evento lleva el título, el lugar y la hora: sin fechas no se sincroniza.
	 */
	public function test_el_evento_lleva_los_datos(): void {
		$this->configurar();

		( new Google_Calendar() )->sync_event( 61 );

		$creado = $GLOBALS['__sdk_llamadas'][0] ?? array();
		$this->assertSame( 'evento_crear', $creado[0] );
		$this->assertSame( 'Ruta de otoño', $creado[2], 'el título del evento' );

		unset( $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_fecha_inicio'] );
		$GLOBALS['__sdk_llamadas'] = array();

		$this->assertNull( ( new Google_Calendar() )->sync_event( 61 ), 'sin fecha no hay evento' );
		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Sin configurar no se sincroniza.
	 */
	public function test_sin_configurar_no_sincroniza(): void {
		$this->assertNull( ( new Google_Calendar() )->sync_event( 61 ) );
		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Si Google falla, se devuelve null y no se guarda un id inventado.
	 */
	public function test_si_falla_la_sincronizacion(): void {
		$this->configurar();
		$GLOBALS['__sdk']['falla_evento'] = true;

		$this->assertNull( ( new Google_Calendar() )->sync_event( 61 ) );
		$this->assertArrayNotHasKey( '_convoca_google_event_id', $GLOBALS['_wp_stores']['post_meta'][61] );
	}

	/**
	 * Al borrar: solo se llama a Google si hay evento y está configurado.
	 */
	public function test_borrar_el_evento(): void {
		$this->configurar();
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_event_id'] = 'EV-1';

		( new Google_Calendar() )->sync_on_delete( 61, $GLOBALS['_wp_stores']['posts'][61] );

		$this->assertContains( 'evento_borrar', $this->llamadas() );

		unset( $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_event_id'] );
		$GLOBALS['__sdk_llamadas'] = array();

		( new Google_Calendar() )->sync_on_delete( 61, $GLOBALS['_wp_stores']['posts'][61] );
		$this->assertSame( array(), $this->llamadas(), 'sin evento no se llama' );
	}

	/**
	 * El ICS se genera de verdad y lleva lo que tiene que llevar.
	 */
	public function test_el_ics(): void {
		$ics = ( new Google_Calendar() )->generate_ics( 61 );

		$this->assertNotNull( $ics );
		$this->assertStringContainsString( 'BEGIN:VCALENDAR', $ics );
		$this->assertStringContainsString( 'SUMMARY:Ruta de otoño', $ics );
		$this->assertStringContainsString( 'LOCATION:Centro Social', $ics );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $ics );
	}

	/**
	 * Una actividad sin fecha no genera ICS; una que no es actividad, tampoco.
	 */
	public function test_sin_fecha_no_hay_ics(): void {
		unset( $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_fecha_inicio'] );
		$this->assertNull( ( new Google_Calendar() )->generate_ics( 61 ) );

		$GLOBALS['_wp_stores']['posts'][61]->post_type = 'post';
		$this->assertNull( ( new Google_Calendar() )->generate_ics( 61 ) );
	}

	/**
	 * El fichero `.ics` se escribe y se borra el viejo de la misma actividad.
	 */
	public function test_el_fichero_ics(): void {
		$ruta = ( new Google_Calendar() )->generate_ics_file( 61 );

		$this->assertNotNull( $ruta );
		$this->assertFileExists( $ruta );
		$this->assertStringContainsString( 'actividad-61-', $ruta );

		@unlink( $ruta );
	}
}
