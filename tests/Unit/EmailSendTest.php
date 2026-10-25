<?php
/**
 * Envío de los correos automáticos.
 *
 * `send()` es el camino que acaba en el buzón del socio: coge la plantilla, sustituye las variables, la mete en el
 * marco HTML y la **encola** (la cola es la que entrega, con reintentos). Lo que se prueba: que al socio le llega lo
 * suyo (no a otro), que no se manda dos veces el mismo correo, que sin dirección no se encola nada y que el admin
 * recibe su copia.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Automation;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Queue.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';

/**
 * Envío.
 */
final class EmailSendTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Automatización.
	 *
	 * @var Email_Automation
	 */
	private Email_Automation $correos;

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
		$GLOBALS['db_inserts']    = array();
		$GLOBALS['_wp_mail']      = array();
		$GLOBALS['_wp_logs']      = array();
		$GLOBALS['mail_falla']    = false;

		if ( isset( $GLOBALS['_wpdb_doble'] ) ) {
			$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
			$GLOBALS['wpdb']   = $GLOBALS['_wpdb_doble'];
			$GLOBALS['wpdb']->rows_affected = 1;
		}

		$GLOBALS['_wp_stores']['bloginfo']['name'] = 'Convoca';
		$GLOBALS['_wp_stores']['bloginfo']['url']  = 'https://example.test';

		// Inscripción 470, de la actividad 62, con su correo.
		$GLOBALS['convoca_test_post_types'][470] = 'inscripcion';
		$GLOBALS['convoca_test_post_types'][62]  = 'actividad';
		$GLOBALS['_wp_stores']['post_meta'][470] = array(
			'_convoca_actividad_id' => 62,
			'_convoca_nombre'       => 'Ana',
			'_convoca_email'        => 'ana@example.test',
			'_convoca_estado'       => 'confirmada',
		);
		$actividad                        = new \WP_Post();
		$actividad->ID                    = 62;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][62] = $actividad;

		$this->correos = new Email_Automation();
	}

	/**
	 * `$wpdb` anterior.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']  = $this->previo;
		$GLOBALS['db_inserts']  = array();
		$GLOBALS['_wp_mail']    = array();
		$GLOBALS['mail_falla']  = false;
		unset( $GLOBALS['convoca_test_post_types'], $GLOBALS['convoca_test_posts'] );
		if ( null !== $this->wpdb_previo ) {
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
	}

	/**
	 * Llama a `send()` (privado) y devuelve lo encolado.
	 *
	 * @param string $slug    Plantilla.
	 * @param int    $inscripcion Inscripción.
	 * @return array
	 */
	private function enviar( string $slug, int $inscripcion = 470 ): array {
		$metodo = new \ReflectionMethod( Email_Automation::class, 'send' );
		$metodo->invoke( $this->correos, $slug, $inscripcion, 62 );

		$encolados = array();
		foreach ( $GLOBALS['db_inserts'] as $insert ) {
			$encolados[] = $insert['datos'] ?? $insert;
		}

		return $encolados;
	}

	/**
	 * El correo va al socio con su nombre y el de la actividad ya puestos, y con su tipo.
	 */
	public function test_encola_para_el_socio(): void {
		$encolados = $this->enviar( 'recepcion' );

		$this->assertNotEmpty( $encolados, 'se encola' );
		$primero = $encolados[0];

		$this->assertSame( 'ana@example.test', $primero['recipient'], 'al socio, no a otro' );
		$this->assertStringNotContainsString( '{nombre}', (string) $primero['subject'], 'las variables se sustituyen' );
		$this->assertStringContainsString( 'Ana', (string) $primero['body_html'] );
		$this->assertStringContainsString( 'Ruta de otoño', (string) $primero['body_html'], 'y el nombre de la actividad' );
		$this->assertNotEmpty( (string) $primero['body_text'], 'y su versión en texto para los clientes que no pintan HTML' );
	}

	/**
	 * El admin recibe su copia, marcada, para saber qué correo ha salido.
	 */
	public function test_el_admin_recibe_copia(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'admin_email' => 'equipo@example.test' );

		$encolados = $this->enviar( 'recepcion' );

		$this->assertCount( 2, $encolados, 'socio y admin' );
		$this->assertSame( 'equipo@example.test', $encolados[1]['recipient'] );
		$this->assertStringStartsWith( '[Admin]', (string) $encolados[1]['subject'] );
	}

	/**
	 * Si el admin es el propio socio, no se duplica.
	 */
	public function test_no_duplica_si_el_admin_es_el_socio(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'admin_email' => 'ana@example.test' );

		$this->assertCount( 1, $this->enviar( 'recepcion' ) );
	}

	/**
	 * Ya enviado hace menos de cinco minutos: no se repite.
	 */
	public function test_no_repite_el_mismo_correo(): void {
		$GLOBALS['wpdb']->rows_affected = 0;

		$this->assertSame( array(), $this->enviar( 'recepcion' ), 'el cerrojo de la base lo impide' );
	}

	/**
	 * Sin dirección de correo no se encola nada (y no se rompe).
	 */
	public function test_sin_email_no_encola(): void {
		unset( $GLOBALS['_wp_stores']['post_meta'][470]['_convoca_email'] );
		$GLOBALS['_wp_stores']['post_meta'][470]['_convoca_email'] = '';

		$this->assertSame( array(), $this->enviar( 'recepcion' ) );
	}

	/**
	 * Una plantilla que no existe no manda nada.
	 */
	public function test_slug_desconocido_no_encola(): void {
		$this->assertSame( array(), $this->enviar( 'inventado' ) );
	}

	/**
	 * Los cuatro recordatorios programados envían a quien está confirmado.
	 *
	 * El cron busca las actividades de la ventana (con su marca de recordatorio) y manda a cada confirmado. Se prueban
	 * los cuatro: si uno deja de engancharse o cambia de plantilla, el socio no recibe el aviso.
	 *
	 * @dataProvider crons
	 *
	 * @param string $metodo Método del cron.
	 * @param string $slug   Plantilla que debe salir.
	 * @return void
	 */
	public function test_los_crons_de_recordatorio( string $metodo, string $slug, string $reminder_key, string $campo, string $fecha ): void {
		$GLOBALS['db_inserts']           = array();
		$GLOBALS['convoca_test_posts']   = array( $GLOBALS['_wp_stores']['posts'][62], (object) array( 'ID' => 470 ) );
		$GLOBALS['wpdb']->rows_affected  = 1;

		// El cron busca las actividades por dos metas: la marca de ese recordatorio y la fecha en su ventana. Sin
		// ellas el doble estricto no devuelve la actividad y no se manda nada (enroll#44).
		$GLOBALS['_wp_stores']['post_meta'][62][ '_convoca_' . $reminder_key ] = '1';
		$GLOBALS['_wp_stores']['post_meta'][62][ $campo ]                     = wp_date( 'Y-m-d\TH:i', strtotime( $fecha ) );

		$this->correos->{$metodo}();

		$asuntos = array_map( static fn( $i ) => (string) ( $i['datos']['subject'] ?? '' ), $GLOBALS['db_inserts'] );
		$this->assertNotEmpty( $asuntos, 'se manda el recordatorio' );
		$this->assertStringNotContainsString( '{', implode( ' ', $asuntos ), 'sin variables sin sustituir' );
	}

	/**
	 * Los crons que se prueban.
	 *
	 * @return array
	 */
	public static function crons(): array {
		// El cuarto y el quinto son la marca que el cron exige y la fecha que tiene que caer en su ventana.
		return array(
			'7 días'   => array( 'cron_reminder_7dias', 'recordatorio_7dias', 'reminder_7dias', '_convoca_fecha_inicio', '+7 days' ),
			'24 horas' => array( 'cron_reminder_24h', 'recordatorio_24h', 'reminder_1dia', '_convoca_fecha_inicio', '+24 hours' ),
			'1 hora'   => array( 'cron_reminder_1hora', 'recordatorio_1hora', 'reminder_1hora', '_convoca_fecha_inicio', '+1 hour' ),
			'encuesta' => array( 'cron_feedback', 'feedback_post', 'reminder_post_evento', '_convoca_fecha_fin', '-7 days' ),
		);
	}
}
