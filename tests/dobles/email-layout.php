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
	 * Botón.
	 *
	 * @param string $url    Destino.
	 * @param string $texto  Texto.
	 * @return string
	 */
	public static function button_html( $url, $texto ) {
		return '<a href="' . $url . '">' . $texto . '</a>';
	}
}
