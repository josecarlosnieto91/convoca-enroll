<?php
/**
 * Contrato del QR del correo: se pinta en casa y apunta a donde se atiende.
 *
 * Es un contrato entre ficheros (el correo y el generador) que ningún lint ve, como los
 * de AJAX y las rutas: por eso lo vigila una prueba que lee el fuente.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests del QR y del enlace que viajan en el correo de confirmación.
 */
class QrLocalTest extends TestCase
{
	/**
	 * Fuente de un fichero del plugin.
	 */
	private function fuente( string $relativa ): string
	{
		$ruta = dirname( __DIR__, 2 ) . '/' . $relativa;
		$this->assertFileExists( $ruta );

		return (string) file_get_contents( $ruta );
	}

	/**
	 * El fuente SIN comentarios.
	 *
	 * Un test que busca texto en el fuente no puede leer los comentarios: el que explica
	 * el defecto nombra justo lo que se quiere prohibir (le pasó a este mismo test con
	 * «quickchart» y a CheckinRutasTest con el prefijo viejo). Se comprueba el código.
	 */
	private function codigo( string $relativa ): string
	{
		$limpio = '';
		foreach ( token_get_all( $this->fuente( $relativa ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$limpio .= is_array( $token ) ? $token[1] : $token;
		}

		return $limpio;
	}

	public function test_el_correo_no_delega_el_qr_en_un_tercero(): void
	{
		// La imagen se le pedía a quickchart.io con la URL de check-in dentro: el token
		// del socio —su credencial— viajaba fuera para que nos devolviera un PNG que
		// sabemos pintar aquí.
		$codigo = $this->codigo( 'includes/Email_Automation.php' );

		$this->assertStringNotContainsString( 'quickchart', $codigo );
		$this->assertStringContainsString( 'QR_Generator::url_for', $codigo );
	}

	public function test_el_correo_usa_el_mismo_enlace_que_espera_el_handler(): void
	{
		$codigo = $this->codigo( 'includes/Email_Automation.php' );

		// Si el correo vuelve a montar la URL por su cuenta, volverán a discrepar.
		$this->assertStringContainsString( 'Checkin_Link::url', $codigo );
		$this->assertStringNotContainsString( '/checkin/?token=', $codigo );
	}

	public function test_el_generador_pinta_qr_de_cualquier_url(): void
	{
		$codigo = $this->codigo( 'media/class-qr-generator.php' );

		$this->assertStringContainsString( 'public static function generate_for_url(', $codigo );
		$this->assertStringContainsString( 'public static function url_for(', $codigo );
		// Un solo sitio dibuja códigos: si el render se duplica, se desincronizan.
		$this->assertSame( 1, substr_count( $codigo, 'new QRCode(' ), 'El QR debe pintarse en un único sitio.' );
	}
}
