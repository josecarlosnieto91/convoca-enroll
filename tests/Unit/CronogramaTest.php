<?php
/**
 * Los eventos de cron del plugin se programan también fuera de la activación (issue #62).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Cronograma;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Cronograma.php';

/**
 * Cronograma.
 */
final class CronogramaTest extends TestCase {

	/**
	 * Estado previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el almacén.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
	}

	/**
	 * Restaura.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * El recordatorio configurable está en la lista: era el que faltaba y hacía inerte el campo
	 * «Recordatorio X horas antes».
	 */
	public function test_el_recordatorio_configurable_esta_programado(): void {
		$eventos = Cronograma::eventos();

		$this->assertArrayHasKey( 'convoca_enroll_activity_reminder', $eventos, 'sin este evento el campo «X horas antes» no se envía nunca' );
		$this->assertSame( 'hourly', $eventos['convoca_enroll_activity_reminder'] );
	}

	/**
	 * La lista cubre los ocho eventos que limpia `uninstall.php` más los dos que se añadieron después.
	 */
	public function test_la_lista_cubre_los_eventos_del_plugin(): void {
		$eventos = array_keys( Cronograma::eventos() );

		foreach ( array(
			'convoca_enroll_reminder_7dias',
			'convoca_enroll_reminder_24h',
			'convoca_enroll_reminder_1hora',
			'convoca_enroll_feedback',
			'convoca_enroll_google_photos_share',
			'convoca_social_token_healthcheck',
			'convoca_enroll_process_email_queue',
			'convoca_enroll_process_webhook_queue',
			'convoca_enroll_cleanup_orphan_codes',
			'convoca_enroll_daily_maintenance',
		) as $hook ) {
			$this->assertContains( $hook, $eventos, 'falta ' . $hook );
		}
	}

	/**
	 * La encuesta solo se programa si está activa en Ajustes.
	 */
	public function test_la_encuesta_solo_si_esta_activa(): void {
		$this->assertArrayNotHasKey( 'convoca_enroll_eval_reminder', Cronograma::eventos() );

		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'eval_reminder' => array( 'active' => 1 ) );

		$this->assertArrayHasKey( 'convoca_enroll_eval_reminder', Cronograma::eventos() );
	}

	/**
	 * El plugin lo asegura en cada carga, no solo al activarse.
	 */
	public function test_se_asegura_en_init_y_en_la_activacion(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/convoca-enroll.php' );

		$plano = (string) preg_replace( '/\s+/', ' ', $src );

		$this->assertStringContainsString(
			"add_action( 'init', array( \\Convoca\\Enroll\\Cronograma::class, 'asegurar' ) )",
			$plano,
			'los eventos deben asegurarse en init'
		);
		$this->assertStringContainsString( 'Cronograma::asegurar()', $src, 'y también en la activación' );
	}

	/**
	 * `asegurar()` no duplica lo ya programado.
	 */
	public function test_no_reprograma_lo_que_ya_existe(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Cronograma.php' );

		$this->assertStringContainsString( 'wp_next_scheduled( $hook )', $src );
		$this->assertStringContainsString( 'wp_schedule_event( time(), $recurrence, $hook )', $src );
	}
}
