<?php
/**
 * Tanda de issues de noviembre: ventana de check-in, QR incrustado, cancelación, rango de informes
 * y esquema de la cola offline.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Reports;
use Convoca\Enroll\Checkin;
use Convoca\Enroll\Checkin_Handler;
use Convoca\Enroll\Email_Queue;
use Convoca\Enroll\Motor_Inscripcion;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Estados.php';
require_once dirname( __DIR__, 2 ) . '/includes/Fechas.php';
require_once dirname( __DIR__, 2 ) . '/includes/Checkin.php';
require_once dirname( __DIR__, 2 ) . '/includes/Checkin_Handler.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Queue.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-reports.php';

/**
 * Regresiones de la tanda.
 */
final class TandaIssuesTest extends TestCase {

	/**
	 * Estado previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => true );
	}

	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Siembra una actividad en curso y una inscripción confirmada.
	 *
	 * @param string $estado Estado de la inscripción.
	 * @return void
	 */
	private function sembrar( string $estado = 'confirmada' ): void {
		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Taller';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;

		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => gmdate( 'Y-m-d H:i:s', time() - 600 ),
			'_convoca_fecha_fin'    => gmdate( 'Y-m-d H:i:s', time() + 3000 ),
		);

		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => $estado,
			'_convoca_asistencia'   => 'no_registrada',
		);
	}

	/**
	 * La ventana: 2 h antes del inicio y 2 h después del fin.
	 */
	public function test_la_ventana_de_checkin(): void {
		$this->sembrar();

		$ventana = Checkin::ventana( 61 );

		$inicio = \Convoca\Enroll\Fechas::instante( (string) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_fecha_inicio'] );

		$this->assertNotNull( $ventana );
		$this->assertSame( $inicio - 2 * HOUR_IN_SECONDS, $ventana['desde'], 'abre dos horas antes' );
		$this->assertTrue( Checkin::validar_ventana( 61, time() ), 'en la ventana se puede' );

		$temprano = Checkin::validar_ventana( 61, time() - 10 * HOUR_IN_SECONDS );
		$this->assertInstanceOf( \WP_Error::class, $temprano );
		$this->assertSame( 'checkin_temprano', $temprano->get_error_code() );

		$tarde = Checkin::validar_ventana( 61, time() + 10 * HOUR_IN_SECONDS );
		$this->assertInstanceOf( \WP_Error::class, $tarde );
		$this->assertSame( 'checkin_cerrado', $tarde->get_error_code() );
	}

	/**
	 * Marcar deja hora y usuario, y el segundo intento se distingue.
	 */
	public function test_registrar_checkin_sella_hora_y_detecta_repetido(): void {
		$this->sembrar();

		$primero = Checkin::registrar( 70, 7 );

		$this->assertIsArray( $primero );
		$this->assertFalse( $primero['ya_marcada'] );
		$this->assertSame( 'si', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'] );
		$this->assertNotEmpty( $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_hora'] );
		$this->assertEquals( 7, $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_by'] );

		$segundo = Checkin::registrar( 70, 7 );

		$this->assertTrue( $segundo['ya_marcada'], 'el segundo escaneo lo dice' );
		$this->assertSame( $primero['hora'], $segundo['hora'], 'no reescribe la hora del primero' );
	}

	/**
	 * Una pendiente no se marca desde la puerta.
	 */
	public function test_registrar_rechaza_no_vigente(): void {
		$this->sembrar( 'cancelada' );

		$r = Checkin::registrar( 70, 7 );

		$this->assertInstanceOf( \WP_Error::class, $r );
		$this->assertSame( 'not_confirmed', $r->get_error_code() );
		$this->assertSame( 'no_registrada', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'] );
	}

	/**
	 * Cancelar retira la asistencia y el rastro horario (enroll#77).
	 */
	public function test_cancelar_retira_la_asistencia(): void {
		$this->sembrar();
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia']   = 'si';
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_hora'] = '2026-11-09 10:00:00';
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_by']   = 7;
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_checkin_token'] = 'tok';

		Motor_Inscripcion::cancelar( 70, '', true );

		$meta = $GLOBALS['_wp_stores']['post_meta'][70];
		$this->assertSame( 'cancelada', $meta['_convoca_estado'] );
		$this->assertSame( 'no_registrada', $meta['_convoca_asistencia'], 'una cancelada no cuenta como asistente' );
		$this->assertArrayNotHasKey( '_convoca_checkin_hora', $meta );
		$this->assertArrayNotHasKey( '_convoca_checkin_by', $meta );
	}

	/**
	 * El escáner tiene respaldo manual y no lleva datos de ejemplo.
	 */
	public function test_la_pantalla_del_escaner_sin_datos_de_ejemplo(): void {
		$metodo = new \ReflectionMethod( Checkin_Handler::class, 'render_scanner_page' );
		$metodo->setAccessible( true );

		ob_start();
		try {
			$metodo->invoke( new Checkin_Handler() );
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'id="manual-code"', $html, 'hay entrada manual si la cámara falla' );
		$this->assertStringContainsString( 'wp_footer', $html, 'la plantilla cierra con wp_footer' );
		$this->assertStringNotContainsString( 'Jose Carlos', $html, 'sin nombres de ejemplo' );
		$this->assertStringContainsString( 'tokenFromScan', $html, 'el QR se lee como URL o como token' );
	}

	/**
	 * El esquema de la cola offline sube a v2 y crea el almacén si falta (enroll#82).
	 */
	public function test_el_service_worker_arregla_las_bases_viejas(): void {
		$sw = (string) file_get_contents( dirname( __DIR__, 2 ) . '/public/assets/pwa/sw.js' );

		$this->assertMatchesRegularExpression( '/const DB_VERSION = [2-9][0-9]*;/', $sw );
		$this->assertStringContainsString( 'objectStoreNames.contains', $sw, 'no revienta si el almacén ya existe' );
		$this->assertStringContainsString( "addEventListener('message'", $sw, 'volcado sin Background Sync' );
	}

	/**
	 * Las imágenes inline se añaden con su Content-ID.
	 */
	public function test_el_qr_viaja_como_imagen_inline(): void {
		$ruta = tempnam( sys_get_temp_dir(), 'qr' );
		file_put_contents( $ruta, 'PNG' );

		$mailer = new class() {
			/** @var array<int, array<int, mixed>> */
			public array $adjuntos = array();

			public function addEmbeddedImage( $ruta, $cid, $nombre, $codificacion, $tipo ): void {
				$this->adjuntos[] = array( $ruta, $nombre, $tipo, 'inline', $cid );
			}
		};

		Email_Queue::adjuntar_inline(
			$mailer,
			array(
				array( 'cid' => 'qr-70@convoca', 'path' => $ruta, 'name' => 'qr.png', 'mime' => 'image/png' ),
			)
		);

		$this->assertCount( 1, $mailer->adjuntos );
		$this->assertSame( 'inline', $mailer->adjuntos[0][3] );
		$this->assertSame( 'qr-70@convoca', $mailer->adjuntos[0][4] );

		unlink( $ruta );
	}

	/**
	 * El antiduplicado ya no va sobre postmeta (enroll#85).
	 */
	public function test_el_antiduplicado_usa_la_clave_unica_de_options(): void {
		$fuente = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Email_Automation.php' );

		$this->assertStringContainsString( 'INSERT IGNORE INTO {$wpdb->options}', $fuente );
		$this->assertStringNotContainsString( 'ON DUPLICATE KEY UPDATE meta_value = CASE', $fuente );
	}

	/**
	 * El informe abre en próximas actividades, no en el mes en curso (enroll#83).
	 */
	public function test_el_rango_por_defecto_del_informe(): void {
		$rango = Admin_Reports::calcular_rango( array() );

		$this->assertSame( (string) wp_date( 'Y-m-d' ), $rango['start_date'], 'desde hoy' );
		$this->assertSame( (string) wp_date( 'Y-m-d', strtotime( '+6 months' ) ), $rango['end_date'], 'seis meses' );
		$this->assertSame( '', $rango['rango'] );

		$todas = Admin_Reports::calcular_rango( array( 'rango' => 'todas' ) );
		$this->assertSame( '2000-01-01', $todas['start_date'] );
		$this->assertSame( '2100-12-31', $todas['end_date'] );
	}
}
