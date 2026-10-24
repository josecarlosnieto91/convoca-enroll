<?php
/**
 * Doble de la firma digital de core.
 *
 * Se dobla **la integración externa** (Dompdf, la firma), no la clase del producto: `PDF_Compromiso` se ejecuta tal
 * cual y aquí solo se sustituye quien genera el PDF. `generate_pdf()` escribe un fichero de verdad en la ruta pedida y
 * devuelve esa ruta, que es el contrato que espera el producto.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Core;

/**
 * Firma.
 */
final class Signature {

	/**
	 * Último error.
	 *
	 * @var string
	 */
	private string $error = '';

	/**
	 * Sello de aceptación.
	 *
	 * @param string $nombre   Nombre.
	 * @param string $ip       IP.
	 * @param int    $marca    Marca de tiempo.
	 * @param string $contenido Contenido firmado.
	 * @return string
	 */
	public function get_acceptance_stamp_html( string $nombre, string $ip, int $marca, string $contenido ): string {
		$GLOBALS['__signature'][] = compact( 'nombre', 'ip', 'marca', 'contenido' );

		return '<div class="firma-digital">' . htmlspecialchars( $nombre, ENT_QUOTES, 'UTF-8' ) . ' · ' . htmlspecialchars( $ip, ENT_QUOTES, 'UTF-8' ) . '</div>';
	}

	/**
	 * Hash del contenido firmado.
	 *
	 * @param string $contenido Contenido.
	 * @param string $ip        IP.
	 * @param int    $marca     Marca.
	 * @return string
	 */
	public function create_hash( string $contenido, string $ip, int $marca ): string {
		return hash( 'sha256', $contenido . $ip . $marca );
	}

	/**
	 * Genera el PDF y devuelve la ruta (o `false` si falla).
	 *
	 * @param string $html    HTML.
	 * @param array  $datos   Datos de la plantilla.
	 * @param string $ruta    Ruta de destino.
	 * @return string|false
	 */
	public function generate_pdf( string $html, array $datos, string $ruta ) {
		$GLOBALS['__pdf'][] = compact( 'html', 'datos', 'ruta' );

		if ( ! empty( $GLOBALS['convoca_test_pdf_falla'] ) ) {
			$this->error = 'No se pudo renderizar el PDF.';
			return false;
		}

		if ( ! empty( $GLOBALS['convoca_test_pdf_lanza'] ) ) {
			throw new \RuntimeException( 'fallo del renderizador' );
		}

		file_put_contents( $ruta, "%PDF-1.4 simulado\n" );

		return $ruta;
	}

	/**
	 * Último error.
	 *
	 * @return string
	 */
	public function get_last_error(): string {
		return $this->error;
	}
}
