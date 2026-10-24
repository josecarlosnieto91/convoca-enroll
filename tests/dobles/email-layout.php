<?php
/**
 * Doble del maquetador de correos del núcleo.
 *
 * `Email_Automation::get_default_templates_array()` compone sus plantillas por omisión con estas piezas. El núcleo no
 * se clona en el CI, así que se doblan: lo que importa de la pantalla es el texto de la plantilla, no el HTML del
 * marco.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Core;

if ( class_exists( 'Convoca\Core\Email_Layout', false ) ) {
	return;
}

/**
 * Piezas de maquetación.
 */
class Email_Layout {

	/**
	 * Tabla de datos.
	 *
	 * @param array $filas Pares etiqueta/valor.
	 * @return string
	 */
	public static function meta_table( $filas ) {
		$html = '<table>';
		foreach ( (array) $filas as $fila ) {
			$html .= '<tr><th>' . ( $fila['label'] ?? '' ) . '</th><td>' . ( $fila['value'] ?? '' ) . '</td></tr>';
		}

		return $html . '</table>';
	}

	/**
	 * Marco del correo: devuelve el cuerpo dentro de un documento con el asunto.
	 *
	 * @param string $cuerpo     Cuerpo.
	 * @param string $asunto     Asunto.
	 * @param array  $opciones   Opciones (botón, pie).
	 * @return string
	 */
	public static function render( $cuerpo, $asunto = '', $opciones = array() ) {
		$html = '<html><head><title>' . $asunto . '</title></head><body>' . $cuerpo;
		if ( ! empty( $opciones['button_url'] ) ) {
			$html .= self::button_html( $opciones['button_url'], $opciones['button_text'] ?? '' );
		}
		if ( ! empty( $opciones['footer_text'] ) ) {
			$html .= '<footer>' . $opciones['footer_text'] . '</footer>';
		}

		return $html . '</body></html>';
	}

	/**
	 * Botón.
	 *
	 * @param string $url    Destino.
	 * @param string $texto  Texto.
	 * @return string
	 */
	public static function button_html( $url, $texto ) {
		// La clase es la que usa el producto para saber si el cuerpo ya trae su propio boton
		// (`preview_html` busca `email-btn`): sin ella, un CTA duplicado pasaba inadvertido.
		return '<a class="email-btn" href="' . $url . '">' . $texto . '</a>';
	}
}
