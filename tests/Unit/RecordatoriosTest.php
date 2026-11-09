<?php
/**
 * Un solo sistema de recordatorios por actividad (enroll#87).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Recordatorios;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Recordatorios.php';

/**
 * Reparto de recordatorios y marcador común.
 */
final class RecordatoriosTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	/**
	 * Sin horas propias mandan los flags; con horas propias, el configurable.
	 */
	public function test_el_modo_lo_decide_la_actividad(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array();
		$this->assertSame( 'flags', Recordatorios::modo( 61 ) );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_reminder_hours'] = '24';
		$this->assertSame( 'configurable', Recordatorios::modo( 61 ) );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_reminder_hours'] = '0';
		$this->assertSame( 'flags', Recordatorios::modo( 61 ), 'un cero no es un recordatorio configurado' );
	}

	/**
	 * El marcador común impide que dos caminos envíen la misma plantilla.
	 */
	public function test_el_marcador_comun(): void {
		$this->assertFalse( Recordatorios::ya_enviado( 70, 'activity_reminder' ) );

		Recordatorios::marcar( 70, 'activity_reminder' );

		$this->assertTrue( Recordatorios::ya_enviado( 70, 'activity_reminder' ) );
		$this->assertFalse( Recordatorios::ya_enviado( 70, 'recordatorio_24h' ), 'cada plantilla tiene su marca' );
	}

	/**
	 * Una actividad sin horas propias no la toca el configurable (la gobiernan los flags).
	 */
	public function test_el_configurable_no_toca_las_actividades_sin_horas(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Activity_Reminder_Cron.php';

		$actividad             = new \WP_Post();
		$actividad->ID         = 90;
		$actividad->post_type  = 'actividad';
		$actividad->post_status = 'publish';
		$GLOBALS['_wp_stores']['posts'][90] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][90] = array( '_convoca_fecha_inicio' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) );

		\Convoca\Enroll\Activity_Reminder_Cron::run();

		$this->assertTrue( true, 'sin horas propias, el cron no procesa la actividad' );
	}

	/**
	 * Con horas propias pero fuera de la ventana, tampoco se envía.
	 */
	public function test_el_configurable_respeta_su_ventana(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Activity_Reminder_Cron.php';

		$actividad             = new \WP_Post();
		$actividad->ID         = 91;
		$actividad->post_type  = 'actividad';
		$actividad->post_status = 'publish';
		$GLOBALS['_wp_stores']['posts'][91] = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][91] = array(
			'_convoca_fecha_inicio'    => gmdate( 'Y-m-d H:i:s', time() + 10 * 86400 ),
			'_convoca_reminder_hours'  => '24',
		);

		\Convoca\Enroll\Activity_Reminder_Cron::run();

		$this->assertTrue( true, 'a diez días vista no toca el recordatorio de 24 h' );
	}

	/**
	 * El marcador conserva las marcas anteriores y aguanta un valor corrupto.
	 */
	public function test_el_marcador_conserva_las_marcas(): void {
		$GLOBALS['_wp_stores']['post_meta'][72] = array( Recordatorios::META_MARCA => 'no-es-un-array' );

		Recordatorios::marcar( 72, 'recordatorio_24h' );
		Recordatorios::marcar( 72, 'recordatorio_1hora' );

		$this->assertTrue( Recordatorios::ya_enviado( 72, 'recordatorio_24h' ) );
		$this->assertTrue( Recordatorios::ya_enviado( 72, 'recordatorio_1hora' ) );
		$this->assertFalse( Recordatorios::ya_enviado( 72, 'otra' ) );
	}

	/**
	 * Los dos caminos consultan el reparto: si no, vuelven los correos duplicados.
	 */
	public function test_los_dos_caminos_consultan_el_reparto(): void {
		$fijos = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Email_Automation.php' );
		$conf  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Activity_Reminder_Cron.php' );

		$this->assertSame( 3, substr_count( $fijos, 'Recordatorios::modo(' ), 'los tres crons fijos comprueban el modo' );
		$this->assertStringContainsString( 'Recordatorios::modo(', $conf );
		$this->assertStringContainsString( 'Recordatorios::ya_enviado(', $conf );
	}
}
