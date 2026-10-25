<?php
/**
 * El procesador de la cola de correo.
 *
 * `Email_Queue::process_queue()` no lo había ejecutado **nadie** hasta ahora: es la pieza que decide si un correo de
 * inscripción sale, si se reintenta y cuándo se da por perdido. Cuando falla, no hay error visible — el socio
 * simplemente no recibe la confirmación.
 *
 * Lo que se mide aquí y por qué:
 *
 *  - **El límite horario**, porque un pico de inscripciones puede tumbar el correo del sitio entero.
 *  - **El reintento con backoff**, porque un SMTP caído 10 minutos no puede costar la inscripción.
 *  - **El paso a `failed`**, porque una cola que reintenta para siempre es una cola que no termina nunca.
 *  - **El cerrojo**, porque dos procesos a la vez mandan el mismo correo dos veces.
 *  - **La copia a monitores**, porque el aviso al responsable es parte del servicio.
 *
 * Las ramas que dependen del **reloj** (corte por tiempo límite y heartbeat del cerrojo) quedan fuera y están
 * declaradas: haría falta inyectar `microtime()` y el código no lo permite. No se simulan para que la cifra suba.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Queue;
use PHPUnit\Framework\TestCase;

/**
 * Cola de correo.
 */
final class EmailQueueProcesoTest extends TestCase {

	/**
	 * Limpia el escenario.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Email_Queue.php';

		// Se guardan SOLO las claves que este caso usa, y se restauran en el `tearDown`.
		//
		// El primer intento hacía `$GLOBALS['_wp_stores'] = array()`: eso borra también el registro de ganchos y de
		// comandos WP-CLI que deja el arranque del plugin, así que otras suites (`PiezasDeArranqueTest`,
		// `MigracionYEstilosTest`) se quedaban sin el sujeto cuando este fichero corría antes. Reventó 7 de 8
		// semillas. Una prueba no puede vaciar el mundo para aislarse: aparta lo suyo y lo devuelve.
		// Copia PROFUNDA del store entero y se restaura tal cual en el `tearDown`. Restaurar clave a clave dejando
		// `unset` cuando antes no existía producía un hueco que `get_option()` convertía en `null` al tomar la
		// referencia, y el siguiente `array_key_exists()` reventaba con TypeError en OTRA suite.
		$this->previo = $GLOBALS['_wp_stores'] ?? array();
		foreach ( $this->claves as $clave ) {
			$GLOBALS['_wp_stores'][ $clave ] = array();
		}

		$GLOBALS['_wp_logs']                = array();
		$GLOBALS['_convoca_copias']         = array();
		$GLOBALS['_convoca_locks_pedidos']  = array();
		$GLOBALS['_convoca_locks_soltados'] = array();
		$GLOBALS['db_updates']              = array();
		$GLOBALS['db_inserts']              = array();
		$GLOBALS['db_prepares']             = array();
		unset( $GLOBALS['convoca_test_mail_falla'], $GLOBALS['convoca_test_lock'], $GLOBALS['convoca_test_query_retorno'] );
	}

	/**
	 * Devuelve el escenario a como estaba.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
		unset( $GLOBALS['convoca_test_mail_falla'], $GLOBALS['convoca_test_lock'], $GLOBALS['convoca_test_query_retorno'] );
		unset( $GLOBALS['_convoca_copias'] );
	}

	/**
	 * Claves de `_wp_stores` que este caso toca.
	 *
	 * @var string[]
	 */
	private array $claves = array( 'options', 'col', 'results', 'sent_mail', 'post_meta', 'transients', 'next_scheduled' );

	/**
	 * Valores que había antes de tocarlas.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Un correo de mentira de la cola.
	 *
	 * @param int    $id            ID.
	 * @param int    $retries       Reintentos.
	 * @param string $recipient     Destinatario.
	 * @param int    $inscripcion   Inscripción.
	 * @return object
	 */
	private function correo( int $id = 1, int $retries = 0, string $recipient = 'socia@example.test', int $inscripcion = 55 ): object {
		return (object) array(
			'id'             => $id,
			'recipient'      => $recipient,
			'subject'        => 'Confirmación',
			'body_html'      => '<p>Gracias</p>',
			'body_text'      => 'Gracias',
			'attachments'    => wp_json_encode( array() ),
			'inscripcion_id' => $inscripcion,
			'status'         => 'sending',
			'retries'        => $retries,
			'created_at'     => '2026-10-25 10:00:00',
		);
	}

	/**
	 * `enqueue()` guarda lo que hay que enviar, avisa en el registro y programa el envío.
	 *
	 * Lo de programar el evento no es un detalle: sin eso el correo esperaba al cron del minuto y quien se
	 * inscribía no recibía nada hasta entonces.
	 */
	public function test_encolar_guarda_avisa_y_programa(): void {
		$id = Email_Queue::enqueue(
			array(
				'to'             => 'socia@example.test',
				'subject'        => 'Confirmación',
				'body'           => '<p>Gracias</p>',
				'inscripcion_id' => 55,
			)
		);

		$this->assertSame( 42, $id, 'devuelve el id del registro' );

		$fila = $GLOBALS['db_inserts'][0]['datos'];
		$this->assertSame( 'socia@example.test', $fila['recipient'], 'a quién' );
		$this->assertSame( 'pending', $fila['status'], 'entra como pendiente' );
		$this->assertSame( 55, $fila['inscripcion_id'] );
		$this->assertSame( 'Gracias', $fila['body_text'], 'el texto plano se deriva del HTML' );
		$this->assertSame( '[]', $fila['attachments'] );

		$niveles = array_column( $GLOBALS['_wp_logs'], 'level' );
		$this->assertContains( 'info', $niveles, 'se anota en el registro' );
	}

	/**
	 * Y si el `insert` falla, `enqueue()` devuelve false en vez de un id inventado.
	 */
	public function test_encolar_si_el_insert_falla_devuelve_false(): void {
		$wpdb             = $GLOBALS['wpdb'];
		$GLOBALS['wpdb']  = new class() {
			/**
			 * Prefijo.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';

			/**
			 * Fallo de inserción.
			 *
			 * @return int
			 */
			public function insert( $t, $d, $f = array() ) {
				unset( $t, $d, $f );
				return 0;
			}
		};

		$resultado = Email_Queue::enqueue( array( 'to' => 'a@example.test', 'subject' => 'x', 'body' => 'y' ) );

		$GLOBALS['wpdb'] = $wpdb;

		$this->assertFalse( $resultado, 'sin fila no hay id: devolver 42 sería mentir' );
	}

	/**
	 * Si ya hay un envío programado para dentro de más de 5 s, no se programa otro.
	 */
	public function test_no_programa_de_mas(): void {
		$GLOBALS['_wp_stores']['next_scheduled'] = array( 'convoca_enroll_process_email_queue' => time() + 600 );

		$antes = $GLOBALS['_wp_stores']['next_scheduled'];

		Email_Queue::enqueue( array( 'to' => 'a@example.test', 'subject' => 'x', 'body' => 'y' ) );

		$this->assertSame( $antes, $GLOBALS['_wp_stores']['next_scheduled'], 'ya había uno válido' );
	}

	/**
	 * Sin cerrojo no se procesa nada: dos procesos a la vez mandarían el mismo correo dos veces.
	 */
	public function test_sin_cerrojo_no_se_procesa(): void {
		$GLOBALS['convoca_test_lock']     = false;
		$GLOBALS['_wp_stores']['col']     = array( 1 );
		$GLOBALS['_wp_stores']['results'] = array( $this->correo() );

		( new Email_Queue() )->process_queue();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['sent_mail'], 'no se manda nada' );
		$this->assertContains( 'convoca_enroll_email_queue_lock', $GLOBALS['_convoca_locks_pedidos'] );
	}

	/**
	 * Con el límite horario agotado se suspende y se avisa, en vez de seguir mandando.
	 */
	public function test_el_limite_horario_suspende_el_envio(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'email_hourly_limit' => 2 );
		$GLOBALS['_wp_stores']['transients'][ 'convoca_enroll_email_hourly_count_' . gmdate( 'YmdH' ) ] = 2;
		$GLOBALS['_wp_stores']['col']     = array( 1 );
		$GLOBALS['_wp_stores']['results'] = array( $this->correo() );

		( new Email_Queue() )->process_queue();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['sent_mail'], 'no sale ni uno' );
		$avisos = array_filter( $GLOBALS['_wp_logs'], static fn( $l ) => 'warning' === $l['level'] );
		$this->assertNotEmpty( $avisos, 'y hay que enterarse: un límite silencioso es un correo que no llega' );
		$this->assertStringContainsString( 'Límite horario', implode( ' ', array_column( $avisos, 'message' ) ) );
	}

	/**
	 * Cerca del límite, el lote se recorta a lo que queda: ni un correo más del cupo.
	 */
	public function test_el_lote_se_recorta_al_cupo_que_queda(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array(
			'email_hourly_limit' => 10,
			'email_batch_size'   => 20,
		);
		$GLOBALS['_wp_stores']['transients'][ 'convoca_enroll_email_hourly_count_' . gmdate( 'YmdH' ) ] = 8;

		( new Email_Queue() )->process_queue();

		// El `LIMIT` va en la consulta de selección: se mira la preparada que lo lleva.
		$preparadas = implode( "\n", $GLOBALS['db_prepares'] );
		$this->assertStringContainsString( 'LIMIT', $preparadas );
	}

	/**
	 * Un envío correcto marca el correo como enviado, cuenta para el límite y avisa a los responsables.
	 */
	public function test_un_envio_correcto(): void {
		$GLOBALS['_wp_stores']['col']     = array( 7 );
		$GLOBALS['_wp_stores']['results'] = array( $this->correo( 7 ) );
		$GLOBALS['_wp_stores']['post_meta'] = array( 55 => array( '_convoca_actividad_id' => '900' ) );

		( new Email_Queue() )->process_queue();

		$this->assertCount( 1, $GLOBALS['_wp_stores']['sent_mail'], 'salió el correo' );
		$this->assertSame( 'socia@example.test', $GLOBALS['_wp_stores']['sent_mail'][0]['to'] );

		$estados = array_column( array_column( $GLOBALS['db_updates'], 'datos' ), 'status' );
		$this->assertContains( 'sent', $estados, 'queda como enviado' );

		$this->assertNotEmpty( $GLOBALS['_convoca_copias'], 'se pide la copia a monitores' );
		$copia = $GLOBALS['_convoca_copias'][0];
		$this->assertSame( 55, $copia['inscripcion_id'] );
		$this->assertSame( 900, $copia['actividad_id'], 'la actividad sale de la inscripción, no se inventa' );
		$this->assertSame( 'convoca-enroll', $copia['plugin'] );

		$this->assertContains( 'convoca_enroll_email_queue_lock', $GLOBALS['_convoca_locks_soltados'], 'el cerrojo se suelta' );
	}

	/**
	 * Un envío fallido vuelve a `pending` con un reintento programado, no se pierde.
	 */
	public function test_un_fallo_se_reintenta_con_espera(): void {
		$GLOBALS['convoca_test_mail_falla'] = true;
		$GLOBALS['_wp_stores']['col']       = array( 9 );
		$GLOBALS['_wp_stores']['results']   = array( $this->correo( 9 ) );

		( new Email_Queue() )->process_queue();

		$datos = array_column( $GLOBALS['db_updates'], 'datos' );
		$fila  = end( $datos );

		$this->assertSame( 1, $fila['retries'], 'cuenta el intento' );
		$this->assertSame( 'pending', $fila['status'], 'se reintenta, no se da por perdido' );
		$this->assertNotNull( $fila['next_retry_at'], 'y con una espera: sin ella se reintenta en bucle' );
	}

	/**
	 * Al llegar al máximo de intentos el correo se marca `failed`: una cola que reintenta siempre no termina nunca.
	 */
	public function test_al_agotar_los_intentos_se_marca_fallido(): void {
		$GLOBALS['convoca_test_mail_falla']                         = true;
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'email_max_retries' => 3 );
		$GLOBALS['_wp_stores']['col']                               = array( 11 );
		$GLOBALS['_wp_stores']['results']                           = array( $this->correo( 11, 2 ) );

		( new Email_Queue() )->process_queue();

		$datos = array_column( $GLOBALS['db_updates'], 'datos' );
		$fila  = end( $datos );

		$this->assertSame( 3, $fila['retries'] );
		$this->assertSame( 'failed', $fila['status'], 'intento 3 de 3: se deja de intentar' );
		$this->assertNull( $fila['next_retry_at'], 'sin fecha de reintento: no vuelve a la cola' );

		$niveles = array_column( $GLOBALS['_wp_logs'], 'level' );
		$this->assertContains( 'error', $niveles, 'un correo perdido se registra como error, no como aviso' );
	}

	/**
	 * Sin correos pendientes no se toca la base ni se manda nada.
	 */
	public function test_sin_pendientes_no_hace_nada(): void {
		$GLOBALS['_wp_stores']['col'] = array();

		( new Email_Queue() )->process_queue();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['sent_mail'] );
		$this->assertSame( array(), $GLOBALS['db_updates'], 'no se marca nada como enviado' );
	}

	/**
	 * Los adjuntos viajan al correo: una inscripción con justificante no puede salir sin él.
	 */
	public function test_los_adjuntos_llegan_al_correo(): void {
		$correo              = $this->correo( 13 );
		$correo->attachments = wp_json_encode( array( '/tmp/justificante.pdf' ) );
		$GLOBALS['_wp_stores']['col']     = array( 13 );
		$GLOBALS['_wp_stores']['results'] = array( $correo );

		( new Email_Queue() )->process_queue();

		$this->assertCount( 1, $GLOBALS['_wp_stores']['sent_mail'] );
		$this->assertSame(
			array( '/tmp/justificante.pdf' ),
			$GLOBALS['_wp_stores']['sent_mail'][0]['attachments'],
			'el justificante tiene que llegar al correo, no solo guardarse en la fila'
		);
	}

	/**
	 * Un `attachments` corrupto no revienta el envío: `json_decode` devuelve null y se manda sin adjuntos.
	 */
	public function test_unos_adjuntos_corruptos_no_rompen_el_envio(): void {
		$correo              = $this->correo( 15 );
		$correo->attachments = '{esto no es json';
		$GLOBALS['_wp_stores']['col']     = array( 15 );
		$GLOBALS['_wp_stores']['results'] = array( $correo );

		( new Email_Queue() )->process_queue();

		$this->assertCount( 1, $GLOBALS['_wp_stores']['sent_mail'], 'el correo sale igual' );
	}

	/**
	 * Si la selección devuelve ids pero las filas ya no están, no se manda nada y no se rompe.
	 *
	 * Pasa de verdad: otro proceso borró de la cola entre el `SELECT` y el `get_results`.
	 */
	public function test_si_las_filas_ya_no_estan_no_se_manda_nada(): void {
		$GLOBALS['_wp_stores']['col']     = array( 21 );
		$GLOBALS['_wp_stores']['results'] = array();

		( new Email_Queue() )->process_queue();

		$this->assertSame( array(), $GLOBALS['_wp_stores']['sent_mail'] );
		$this->assertSame( array(), $GLOBALS['db_updates'] );
	}

	/**
	 * El contador horario se crea si la fila del transient no existía todavía.
	 *
	 * Es el «primer correo de la hora»: si no se crea, el contador se queda a 0 y el límite no frena nunca.
	 */
	public function test_el_contador_horario_se_crea_si_no_existia(): void {
		$GLOBALS['_wp_stores']['col']     = array( 23 );
		$GLOBALS['_wp_stores']['results'] = array( $this->correo( 23 ) );
		// El `UPDATE` del contador no afecta a ninguna fila: es la primera de la hora.
		$GLOBALS['convoca_test_query_retorno'] = array( 'option_value' => 0 );

		( new Email_Queue() )->process_queue();

		$clave = 'convoca_enroll_email_hourly_count_' . gmdate( 'YmdH' );
		$this->assertSame( 1, $GLOBALS['_wp_stores']['transients'][ $clave ] ?? null, 'el contador arranca en 1' );
	}

	/**
	 * La función de envío que se le pasa a la copia manda de verdad (y devuelve si salió).
	 *
	 * Se ejerce la closure porque es el camino por el que el núcleo manda la copia: si devolviera siempre true sin
	 * llamar a `wp_mail`, la copia a monitores no se enviaría nunca y el registro diría que sí.
	 */
	public function test_la_funcion_de_envio_de_la_copia_manda(): void {
		$GLOBALS['_wp_stores']['col']     = array( 25 );
		$GLOBALS['_wp_stores']['results'] = array( $this->correo( 25 ) );

		( new Email_Queue() )->process_queue();

		$this->assertNotEmpty( $GLOBALS['_convoca_copias'] );
		$enviar = $GLOBALS['_convoca_copias'][0]['send'];

		$antes    = count( $GLOBALS['_wp_stores']['sent_mail'] );
		$resultado = $enviar( array( 'monitor@example.test' ), 'Copia', '<p>Copia</p>', array() );

		$this->assertTrue( $resultado );
		$this->assertCount( $antes + 1, $GLOBALS['_wp_stores']['sent_mail'], 'la copia sale por wp_mail' );
		// La copia se manda con la lista de destinatarios tal cual, como hace el núcleo.
		$this->assertSame( array( 'monitor@example.test' ), end( $GLOBALS['_wp_stores']['sent_mail'] )['to'] );
	}

	/**
	 * Y esa misma función devuelve false cuando el transporte falla: la copia no puede mentir.
	 */
	public function test_la_funcion_de_envio_propaga_el_fallo(): void {
		$GLOBALS['_wp_stores']['col']     = array( 27 );
		$GLOBALS['_wp_stores']['results'] = array( $this->correo( 27 ) );

		( new Email_Queue() )->process_queue();

		$enviar = $GLOBALS['_convoca_copias'][0]['send'];

		$GLOBALS['convoca_test_mail_falla'] = true;
		$this->assertFalse( $enviar( array( 'monitor@example.test' ), 'Copia', '<p>Copia</p>', array() ) );
	}
}
