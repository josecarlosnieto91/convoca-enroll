<?php
/**
 * Sin JavaScript, los formularios imprescindibles no pueden ser un callejón sin salida.
 *
 * MEDIDO el 13/10/2026 en el sitio vivo (demo, Playwright con `javaScriptEnabled: false`, 390x844):
 * los formularios públicos no declaraban `method` ni `action`, así que el navegador los enviaba
 * como GET con los campos en la URL y WordPress lo resolvía como una búsqueda vacía («Página no
 * encontrada») después de rellenarlos enteros. Ni un dato guardado ni una frase que lo explicara.
 *
 * Arreglo (degradación declarada): el formulario envía por POST a su propia página con un
 * marcador y, cuando la petición llega SIN JavaScript, el template explica la situación y ofrece
 * el correo de la asociación; el `<noscript>` lo avisa antes de rellenar.
 *
 * Prueba en negativo: con los templates del commit anterior no hay aviso, no hay `<noscript>` y
 * los formularios vuelven a no declarar `method`.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class SinJavaScriptTest extends TestCase
{
	/** @var array<string, array{0: string, 1: string}> ruta => [marcador, nombre legible] */
	private function formularios(): array
	{
		return array(
			'templates/form-inscripcion.php' => array( 'convoca_sin_js_inscripcion', 'la inscripción' ),
			'templates/panel-reservas.php'   => array( 'convoca_sin_js_panel', 'el panel de reservas' ),
		);
	}

	private function raiz(): string
	{
		return dirname( __DIR__, 2 );
	}

	protected function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	public function test_sin_js_avisa_en_vez_de_dejar_al_visitante_tirado(): void
	{
		foreach ( $this->formularios() as $ruta => $datos ) {
			$_POST = array( $datos[0] => '1' );

			ob_start();
			include $this->raiz() . '/' . $ruta;
			$salida = (string) ob_get_clean();

			$this->assertStringContainsString(
				'necesita JavaScript',
				$salida,
				"Sin JavaScript, {$datos[1]} tiene que explicar qué pasa."
			);
			$this->assertStringNotContainsString(
				'<form',
				$salida,
				"Sin JavaScript no puede pintarse el formulario: no hay quien lo envíe."
			);
			$this->assertStringContainsString( 'role="status"', $salida, 'El aviso tiene que ser anunciable.' );
		}
	}

	public function test_los_formularios_declaran_post_marcador_y_aviso_previo(): void
	{
		foreach ( $this->formularios() as $ruta => $datos ) {
			$codigo = (string) file_get_contents( $this->raiz() . '/' . $ruta );
			// La etiqueta del formulario lleva PHP dentro del atributo class, y el patrón `[^>]*`
			// no cruza el cierre de PHP: se quitan los bloques antes de mirar la etiqueta.
			$etiqueta = (string) preg_replace( '/<\?php.*?\?>/s', '', $codigo );

			$this->assertMatchesRegularExpression(
				'/<form[^>]*method="post"/',
				$etiqueta,
				"{$datos[1]}: sin `method=\"post\"` el envío cae en una búsqueda de WordPress."
			);
			$this->assertStringContainsString( 'name="' . $datos[0] . '"', $codigo, "{$datos[1]}: falta el marcador." );
			$this->assertStringContainsString( '<noscript>', $codigo, "{$datos[1]}: hay que avisar antes de rellenar." );
		}
	}
}
