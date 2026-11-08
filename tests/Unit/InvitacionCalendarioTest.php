<?php
/**
 * La invitación de calendario del correo de plaza confirmada (issue #57).
 *
 * Un `.ics` adjunto sin `METHOD:REQUEST` es un fichero cualquiera: Gmail no ofrece «añadir al
 * calendario». Aquí se comprueba que el mensaje es una invitación iTIP de verdad y que el correo la
 * manda como `text/calendar; method=REQUEST`.
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
 * Invitación iTIP.
 */
final class InvitacionCalendarioTest extends TestCase {

	/**
	 * Prepara la actividad de prueba.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);

		$GLOBALS['_wp_stores']['posts'][70] = (object) array(
			'ID'           => 70,
			'post_type'    => 'actividad',
			'post_status'  => 'publish',
			'post_title'   => 'Ruta de otoño',
			'post_content' => '<p>Salida al monte, con merienda; y algo más.</p>',
		);
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_fecha_inicio' => '2026-11-15 11:00:00',
			'_convoca_fecha_fin'    => '2026-11-15 15:00:00',
			'_convoca_ubicacion'    => 'Centro Social, La Cebera',
		);
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'admin_email' => 'coordinacion@example.test' );
	}

	/**
	 * La invitación lleva lo que un cliente necesita para pintar el evento y el botón.
	 */
	public function test_la_invitacion_es_itip(): void {
		$ruta = ( new Google_Calendar() )->generate_invite_ics( 70, 'Ada Lovelace', 'ada@example.test' );

		$this->assertNotNull( $ruta );
		$ics = (string) file_get_contents( $ruta );
		wp_delete_file( $ruta );

		$this->assertStringContainsString( 'METHOD:REQUEST', $ics, 'sin METHOD no hay invitación' );
		$this->assertStringContainsString( 'ORGANIZER;CN=', $ics );
		$this->assertStringContainsString( 'mailto:coordinacion@example.test', $ics );
		$plano = str_replace( "\r\n ", '', $ics ); // las líneas largas vienen plegadas
		$this->assertStringContainsString( 'ATTENDEE;CN=Ada Lovelace;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:ada@example.test', $plano );
		$this->assertStringContainsString( 'UID:actividad-70-', $ics, 'UID estable para que se actualice, no se duplique' );
		$this->assertStringContainsString( 'SEQUENCE:0', $ics );
		$this->assertStringContainsString( 'STATUS:CONFIRMED', $ics );
		$this->assertStringContainsString( 'DTSTART;TZID=', $ics );
		$this->assertStringContainsString( 'DTEND;TZID=', $ics );
		$this->assertStringContainsString( 'SUMMARY:Ruta de otoño', $ics );
		$this->assertStringNotContainsString( '<p>', $ics, 'el HTML no viaja al calendario' );
	}

	/**
	 * El texto se escapa: comas, puntos y coma y saltos de línea rompen el formato si van crudos.
	 */
	public function test_el_texto_se_escapa(): void {
		$ruta = ( new Google_Calendar() )->generate_invite_ics( 70, 'Ada, Lovelace; II', 'ada@example.test' );
		$ics  = (string) file_get_contents( $ruta );
		wp_delete_file( $ruta );

		$plano = str_replace( "\r\n ", '', $ics );
		$this->assertStringContainsString( 'ATTENDEE;CN=Ada\\, Lovelace\\; II;', $plano );
		$this->assertStringContainsString( 'LOCATION:Centro Social\\, La Cebera', $ics );
	}

	/**
	 * Las líneas largas se pliegan a 75 octetos y no se parte un acento por la mitad.
	 */
	public function test_las_lineas_largas_se_pliegan(): void {
		$GLOBALS['_wp_stores']['posts'][70]->post_content = str_repeat( 'Ñandú y perdiz. ', 40 );

		$ruta = ( new Google_Calendar() )->generate_invite_ics( 70, 'Ada', 'ada@example.test' );
		$ics  = (string) file_get_contents( $ruta );
		wp_delete_file( $ruta );

		foreach ( explode( "\r\n", $ics ) as $linea ) {
			$this->assertLessThanOrEqual( 76, strlen( $linea ), 'ninguna línea pasa de 75 octetos (+1 del pliegue)' );
		}
		$this->assertStringContainsString( "\r\n ", $ics, 'hay al menos una continuación' );
		$this->assertSame( 1, preg_match( '//u', $ics ), 'el texto sigue siendo UTF-8 válido' );
	}

	/**
	 * El adjunto se añade con su subtipo de invitación y el contenido del fichero.
	 */
	public function test_el_adjunto_va_con_su_subtipo(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Email_Queue.php';

		$ruta = ( new Google_Calendar() )->generate_invite_ics( 70, 'Ada', 'ada@example.test' );
		$falso = new class() {
			/** @var array<int, array<string, string>> */
			public array $adjuntos = array();

			/**
			 * Guarda lo que se le pide añadir.
			 *
			 * @param string $contenido Contenido.
			 * @param string $nombre    Nombre.
			 * @param string $codifica  Codificación.
			 * @param string $tipo      Tipo MIME.
			 * @return void
			 */
			public function addStringAttachment( $contenido, $nombre, $codifica, $tipo ): void {
				$this->adjuntos[] = compact( 'nombre', 'codifica', 'tipo', 'contenido' );
			}
		};

		\Convoca\Enroll\Email_Queue::adjuntar_invitacion( $falso, array( $ruta, '/no/existe.ics' ) );
		wp_delete_file( $ruta );

		$this->assertCount( 1, $falso->adjuntos, 'el que no existe se ignora' );
		$this->assertSame( 'text/calendar; charset=UTF-8; method=REQUEST', $falso->adjuntos[0]['tipo'] );
		$this->assertSame( 'base64', $falso->adjuntos[0]['codifica'] );
		$this->assertStringContainsString( 'METHOD:REQUEST', $falso->adjuntos[0]['contenido'] );
	}

	/**
	 * El correo manda el .ics como `text/calendar; method=REQUEST`, no como adjunto cualquiera.
	 *
	 * Es la otra mitad del issue: con el texto correcto dentro pero un `Content-Type: text/calendar` a
	 * secas, Gmail sigue sin ofrecer el botón.
	 */
	public function test_el_correo_lo_manda_como_invitacion(): void {
		$cola = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/Email_Queue.php' );

		$this->assertStringContainsString( "add_action( 'phpmailer_init', \$adjuntar_invitacion )", $cola );
		$this->assertStringContainsString( 'text/calendar; charset=UTF-8; method=REQUEST', $cola );
		$this->assertStringContainsString( "remove_action( 'phpmailer_init', \$adjuntar_invitacion )", $cola, 'el gancho no se queda pegado al siguiente correo' );
	}

	/**
	 * Si el sitio no tiene un identificador de zona, la hora va en UTC y no con un `TZID=+00:00` que no
	 * existe. Pasaba en la demo el 08/11/2026 y el evento habría salido sin hora interpretable.
	 */
	public function test_sin_identificador_de_zona_la_hora_va_en_utc(): void {
		$GLOBALS['convoca_tz_string'] = '+00:00';

		$ruta = ( new Google_Calendar() )->generate_invite_ics( 70, 'Ada', 'ada@example.test' );
		$ics  = (string) file_get_contents( $ruta );
		wp_delete_file( $ruta );
		unset( $GLOBALS['convoca_tz_string'] );

		$this->assertStringContainsString( 'DTSTART:20261115T110000Z', $ics );
		$this->assertStringContainsString( 'DTEND:20261115T150000Z', $ics );
		$this->assertStringNotContainsString( 'TZID=+00:00', $ics );
	}

	/**
	 * Sin correo o sin actividad no hay invitación: mejor no adjuntar nada que adjuntar algo roto.
	 */
	public function test_sin_datos_no_hay_invitacion(): void {
		$this->assertNull( ( new Google_Calendar() )->generate_invite_ics( 70, 'Ada', '' ) );
		$this->assertNull( ( new Google_Calendar() )->generate_invite_ics( 999, 'Ada', 'ada@example.test' ) );
	}
}
