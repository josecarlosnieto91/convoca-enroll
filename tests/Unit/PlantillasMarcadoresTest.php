<?php
/**
 * Plantillas guardadas que prometen lo que no envían.
 *
 * El fallo medido: 11 plantillas guardadas desde septiembre, ninguna con `{qr_code}`, y el texto decía
 * «Adjuntamos tu código QR». El mecanismo del QR funcionaba; la plantilla no lo usaba. Y el correo
 * salía con dos invitaciones de calendario idénticas.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Automation;
use Convoca\Enroll\Plantillas;
use Convoca\Enroll\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Plantillas.php';
require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';

/**
 * Fusión de marcadores y adjuntos.
 */
final class PlantillasMarcadoresTest extends TestCase {

	/**
	 * La guardada manda: si no tiene el marcador, se le añade el párrafo de serie.
	 */
	public function test_completa_el_marcador_que_falta(): void {
		$guardado = '<h1>Hola {nombre}</h1><p>Tu plaza está confirmada.</p><p>Adjuntamos tu código QR.</p>';
		$defecto  = '<h1>Hola {nombre}</h1><p>Tu plaza está confirmada.</p><p><img src="{qr_code}" alt="QR"></p><p><a href="{url_checkin}">Check-in</a></p>';

		$fusionado = Plantillas::fusionar( $guardado, $defecto );

		$this->assertStringContainsString( '{qr_code}', $fusionado );
		$this->assertStringContainsString( '{url_checkin}', $fusionado );
		$this->assertStringContainsString( 'Adjuntamos tu código QR.', $fusionado, 'el texto del usuario no se toca' );
		$this->assertStringStartsWith( $guardado, $fusionado, 'lo guardado va primero y entero' );
	}

	/**
	 * Si ya está, no se duplica.
	 */
	public function test_no_duplica_el_marcador_que_ya_esta(): void {
		$cuerpo = '<p>Tu QR:</p><p><img src="{qr_code}"></p><p><a href="{url_checkin}">Check-in</a></p>';

		$this->assertSame( 1, substr_count( Plantillas::fusionar( $cuerpo, $cuerpo ), '{qr_code}' ) );
	}

	/**
	 * Nunca al revés: un marcador que solo está en la guardada no se pierde.
	 */
	public function test_conserva_lo_que_solo_esta_en_la_guardada(): void {
		$guardado = '<p>{album_url}</p>';
		$defecto  = '<p><img src="{qr_code}"></p>';

		$fusionado = Plantillas::fusionar( $guardado, $defecto );

		$this->assertStringContainsString( '{album_url}', $fusionado );
	}

	/**
	 * La plantilla de serie de confirmación pinta el QR de verdad (antes solo lo prometía).
	 */
	public function test_la_plantilla_de_serie_pinta_el_qr(): void {
		$defectos = Email_Automation::get_default_templates_array();
		$cuerpo   = (string) $defectos['confirmacion_plaza']['body'];

		$this->assertStringContainsString( '<img src="{qr_code}"', $cuerpo, 'el QR va como imagen, no solo como promesa' );
		$this->assertStringContainsString( '{url_checkin}', $cuerpo, 'y hay salida si el cliente no pinta la imagen' );
	}

	/**
	 * La promesa no puede viajar sin su marcador: es la prueba en negativo del issue.
	 */
	public function test_prometer_qr_obliga_a_llevar_el_marcador(): void {
		$guardado = '<p>Adjuntamos tu código QR y un enlace al calendario.</p>';
		$defectos = Email_Automation::get_default_templates_array();

		$fusionado = Plantillas::fusionar( $guardado, (string) $defectos['confirmacion_plaza']['body'] );

		$this->assertStringContainsString( 'código QR', $fusionado, 'el texto guardado sigue ahí' );
		$this->assertStringContainsString( '{qr_code}', $fusionado, 'si promete QR, tiene que pintarlo' );
	}

	/**
	 * Un solo .ics: si ya hay uno entre los adjuntos, no se añade el segundo.
	 */
	public function test_no_se_adjuntan_dos_ics(): void {
		$adjuntos = array( '/tmp/invite.ics' );

		$this->assertSame( $adjuntos, Utils::adjuntar_ics( $adjuntos, '/tmp/actividad-1-abc.ics' ) );
	}

	/**
	 * Sin .ics previo sí se adjunta.
	 */
	public function test_se_adjunta_el_ics_cuando_no_hay_ninguno(): void {
		$adjuntos = array( '/tmp/otra-cosa.pdf' );

		$this->assertSame(
			array( '/tmp/otra-cosa.pdf', '/tmp/actividad-1-abc.ics' ),
			Utils::adjuntar_ics( $adjuntos, '/tmp/actividad-1-abc.ics' )
		);
	}

	/**
	 * El filtro permite apagar el adjunto y dejar solo el enlace del cuerpo.
	 */
	public function test_el_filtro_puede_apagar_el_adjunto(): void {
		add_filter( 'convoca_enroll_adjuntar_ics', '__return_false' );

		$this->assertSame( array(), Utils::adjuntar_ics( array(), '/tmp/actividad-1-abc.ics' ) );

		// El arnés no trae `remove_filter`: se vacía el registro para no dejar el filtro a los demás.
		$GLOBALS['_wp_filters'] = array();
	}
}
