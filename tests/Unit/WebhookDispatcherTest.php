<?php
/**
 * Webhooks: lo que sale del sistema hacia fuera.
 *
 * Un webhook que no se entrega no da error en ninguna pantalla: se queda en la cola y el sistema de fuera nunca se
 * entera. Por eso lo que se mide aquí es la cola —qué se encola, con qué payload— y su procesado: los que van bien se
 * marcan `sent`, los que fallan vuelven a `pending` y al tercer intento se rinden como `failed`.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Webhook_Dispatcher;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Webhook_Dispatcher.php';

/**
 * Webhooks.
 */
final class WebhookDispatcherTest extends TestCase {

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
		$GLOBALS['convoca_test_http']     = array( 'code' => 200 );
		$GLOBALS['convoca_test_lock']     = true;
		$GLOBALS['_wp_http_posts']        = array();
		$GLOBALS['db_inserts']            = array();
		$GLOBALS['db_updates']            = array();
		$GLOBALS['_convoca_acciones']     = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']        = $this->previo;
		$GLOBALS['convoca_test_http'] = null;
		$GLOBALS['convoca_test_lock'] = null;
		$GLOBALS['_wp_http_posts']    = array();
		$GLOBALS['db_inserts']        = array();
		$GLOBALS['db_updates']        = array();
		$GLOBALS['_convoca_acciones'] = array();
	}

	/**
	 * Pone la URL del webhook.
	 *
	 * @param string $url URL.
	 * @param string $secreto Secreto de firma.
	 * @return void
	 */
	private function con_webhook( string $url = 'https://destino.example.test/hook', string $secreto = '' ): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array(
			'webhook_url'    => $url,
			'webhook_secret' => $secreto,
		);
	}

	/**
	 * Siembra una inscripción.
	 *
	 * @return void
	 */
	private function inscripcion(): void {
		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;

		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;

		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_email'        => 'ana@example.test',
			'_convoca_nombre'       => 'Ana',
			'_convoca_estado'       => 'confirmada',
			'_convoca_asistencia'   => 'no_registrada',
			'_convoca_es_socio'     => '1',
		);
	}

	/**
	 * Al construirse se engancha a la cola y a los cambios de estado.
	 */
	public function test_los_hooks(): void {
		new Webhook_Dispatcher();

		$this->assertArrayHasKey( 'convoca_enroll_process_webhook_queue', $GLOBALS['_convoca_acciones'] );
		$this->assertArrayHasKey( 'convoca_enroll_inscripcion_nueva', $GLOBALS['_convoca_acciones'] );
		$this->assertArrayHasKey( 'convoca_enroll_asistencia_cambiada', $GLOBALS['_convoca_acciones'] );
	}

	/**
	 * Sin URL configurada no se encola nada.
	 */
	public function test_sin_url_no_encola(): void {
		$this->assertFalse( Webhook_Dispatcher::enqueue( 'prueba', array( 'a' => 1 ) ) );
		$this->assertSame( array(), $GLOBALS['db_inserts'] );
	}

	/**
	 * Una URL que no es una URL tampoco: no se encola basura.
	 */
	public function test_url_invalida(): void {
		$this->con_webhook( 'no-es-una-url' );

		$this->assertFalse( Webhook_Dispatcher::enqueue( 'prueba', array( 'a' => 1 ) ) );
		$this->assertSame( array(), $GLOBALS['db_inserts'] );
	}

	/**
	 * Con URL válida se encola con el payload en JSON y estado `pending`.
	 */
	public function test_encola(): void {
		$this->con_webhook();

		Webhook_Dispatcher::enqueue( 'state_change', array( 'evento' => 'alta' ), 70 );

		$fila = $GLOBALS['db_inserts'][0]['datos'] ?? array();
		$this->assertSame( 'pending', $fila['status'] );
		$this->assertSame( 'state_change', $fila['event_type'] );
		$this->assertSame( 70, $fila['inscripcion_id'] );
		$this->assertSame( 'https://destino.example.test/hook', $fila['url'] );
		$this->assertStringContainsString( '"evento":"alta"', $fila['payload'] );
	}

	/**
	 * Un cambio de estado encola el payload con los datos de la persona y la actividad.
	 */
	public function test_el_payload_del_cambio_de_estado(): void {
		$this->con_webhook();
		$this->inscripcion();

		( new Webhook_Dispatcher() )->on_inscripcion_state_change( 70, 61 );

		$payload = json_decode( $GLOBALS['db_inserts'][0]['datos']['payload'], true );
		$this->assertSame( 'state_change', $payload['event'] );
		$this->assertSame( 'ana@example.test', $payload['email'] );
		$this->assertSame( 'Ana', $payload['nombre'] );
		$this->assertSame( 'Ruta de otoño', $payload['actividad_name'] );
	}

	/**
	 * Una inscripción que no existe no genera webhook.
	 */
	public function test_inscripcion_inexistente(): void {
		$this->con_webhook();

		( new Webhook_Dispatcher() )->on_inscripcion_state_change( 999, 61 );

		$this->assertSame( array(), $GLOBALS['db_inserts'] );
	}

	/**
	 * Un cambio de asistencia sale con su propio evento.
	 */
	public function test_el_cambio_de_asistencia(): void {
		$this->con_webhook();
		$this->inscripcion();

		( new Webhook_Dispatcher() )->on_asistencia_cambiada( 70, 'si' );

		$payload = json_decode( $GLOBALS['db_inserts'][0]['datos']['payload'], true );
		$this->assertSame( 'attendance_change', $payload['event'] );
	}

	/**
	 * Sin el cerrojo no se procesa la cola: dos procesos a la vez enviarían dos veces.
	 */
	public function test_sin_cerrojo_no_procesa(): void {
		$this->con_webhook();
		$GLOBALS['convoca_test_lock'] = false;

		( new Webhook_Dispatcher() )->process_queue();

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
	}

	/**
	 * Cola vacía: no se envía nada y se suelta el cerrojo.
	 */
	public function test_cola_vacia(): void {
		$this->con_webhook();
		$GLOBALS['_wp_stores']['col_cola'] = array( array() );

		( new Webhook_Dispatcher() )->process_queue();

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
		$this->assertNotEmpty( $GLOBALS['_convoca_locks_soltados'], 'el cerrojo se suelta' );
	}

	/**
	 * Un envío correcto se marca `sent`.
	 */
	public function test_envia_y_marca(): void {
		$this->con_webhook();
		$this->preparar_cola( array( $this->item( 1, 0 ) ) );

		( new Webhook_Dispatcher() )->process_queue();

		$this->assertCount( 1, $GLOBALS['_wp_http_posts'] );
		$this->assertSame( 'https://destino.example.test/hook', $GLOBALS['_wp_http_posts'][0]['url'] );
		$this->assertSame( 'sent', $this->estado_del_ultimo_update() );
	}

	/**
	 * Con secreto, la petición lleva la firma del cuerpo.
	 */
	public function test_firma_el_payload(): void {
		$this->con_webhook( 'https://destino.example.test/hook', 'secreto' );
		$this->preparar_cola( array( $this->item( 1, 0 ) ) );

		( new Webhook_Dispatcher() )->process_queue();

		$cabeceras = $GLOBALS['_wp_http_posts'][0]['args']['headers'] ?? array();
		$this->assertArrayHasKey( 'X-Convoca-Signature', $cabeceras );
		$this->assertSame( hash_hmac( 'sha256', '{"a":1}', 'secreto' ), $cabeceras['X-Convoca-Signature'] );
	}

	/**
	 * Un fallo devuelve el webhook a `pending` con un intento más.
	 */
	public function test_un_fallo_reintenta(): void {
		$this->con_webhook();
		$GLOBALS['convoca_test_http'] = array( 'code' => 500 );
		$this->preparar_cola( array( $this->item( 1, 0 ) ) );

		( new Webhook_Dispatcher() )->process_queue();

		$update = end( $GLOBALS['db_updates'] );
		$this->assertSame( 'pending', $update['datos']['status'] );
		$this->assertSame( 1, $update['datos']['retries'] );
	}

	/**
	 * Al tercer intento se rinde: `failed`, para que no se reintente para siempre.
	 */
	public function test_se_rinde_al_tercer_intento(): void {
		$this->con_webhook();
		$GLOBALS['convoca_test_http'] = array( 'code' => 500 );
		$this->preparar_cola( array( $this->item( 1, 2 ) ) );

		( new Webhook_Dispatcher() )->process_queue();

		$this->assertSame( 'failed', $this->estado_del_ultimo_update() );
	}

	/**
	 * Un error de red también reintenta, no se da por enviado.
	 */
	public function test_error_de_red(): void {
		$this->con_webhook();
		$GLOBALS['convoca_test_http'] = array( 'error' => 'sin ruta al host' );
		$this->preparar_cola( array( $this->item( 1, 0 ) ) );

		( new Webhook_Dispatcher() )->process_queue();

		$this->assertSame( 'pending', $this->estado_del_ultimo_update() );
	}

	/**
	 * Prepara la cola con sus filas.
	 *
	 * @param array $filas Filas.
	 * @return void
	 */
	private function preparar_cola( array $filas ): void {
		$GLOBALS['_wp_stores']['col_cola']     = array( array_column( $filas, 'id' ) );
		$GLOBALS['_wp_stores']['results_mapa'] = array( 'convoca_enroll_webhook_queue' => $filas );
	}

	/**
	 * Una fila de la cola.
	 *
	 * @param int $id      Id.
	 * @param int $retries Intentos.
	 * @return object
	 */
	private function item( int $id, int $retries ): object {
		return (object) array(
			'id'             => $id,
			'url'            => 'https://destino.example.test/hook',
			'payload'        => '{"a":1}',
			'event_type'     => 'state_change',
			'inscripcion_id' => 70,
			'status'         => 'sending',
			'retries'        => $retries,
			'created_at'     => '2026-10-01 10:00:00',
		);
	}

	/**
	 * Estado del último `update`.
	 *
	 * @return string
	 */
	private function estado_del_ultimo_update(): string {
		$update = end( $GLOBALS['db_updates'] );

		return (string) ( $update['datos']['status'] ?? '' );
	}
}
