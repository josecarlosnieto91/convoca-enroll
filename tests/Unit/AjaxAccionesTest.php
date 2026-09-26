<?php
/**
 * Toda acción AJAX que publica el JavaScript tiene que existir en el PHP.
 *
 * Defecto real del 2026-09-26, encontrado recorriendo el alta como una persona: el panel de
 * reservas respondía «Error desconocido.» al consultar una reserva recién confirmada. El JS
 * publicaba a `conv_enroll_panel_login` y el PHP registraba `convoca_panel_login`. No era solo
 * esa: otras cuatro acciones tenían el mismo desajuste y dejaban muertas la cancelación desde
 * el panel, el cambio de estado, el check-in y el reenvío de correo en la administración.
 *
 * Un desajuste así no lo ve el CI (cada lado es válido por separado) ni un lint: solo se ve
 * cruzando los dos.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests del contrato JS ↔ PHP de las acciones AJAX.
 */
class AjaxAccionesTest extends TestCase
{
	/**
	 * Raíz del plugin.
	 *
	 * @return string
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Ficheros PHP del plugin (sin dependencias ni pruebas).
	 *
	 * @return array<int, string>
	 */
	private function ficheros_php(): array {
		$fuera = array( 'vendor', 'tests', 'node_modules', 'social', '.git', 'languages' );
		$lista = array();
		$raiz  = $this->raiz();

		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $raiz ) ) as $f ) {
			$ruta = $f->getPathname();
			foreach ( $fuera as $carpeta ) {
				if ( str_contains( $ruta, '/' . $carpeta . '/' ) ) {
					continue 2;
				}
			}
			if ( str_ends_with( $ruta, '.php' ) ) {
				$lista[] = $ruta;
			}
		}

		return $lista;
	}

	public function test_toda_accion_publicada_desde_el_js_esta_registrada_en_php(): void {
		// Lo que publica el JavaScript.
		$publicadas = array();
		foreach ( (array) glob( $this->raiz() . '/assets/js/*.js' ) as $js ) {
			if ( preg_match_all( "/ajaxPost\(\s*'([a-z0-9_]+)'/", (string) file_get_contents( $js ), $m ) ) {
				foreach ( $m[1] as $accion ) {
					$publicadas[ $accion ] = basename( $js );
				}
			}
		}

		$this->assertNotEmpty( $publicadas, 'Debería haber acciones publicadas desde el JS.' );

		// Lo que registra el PHP.
		$registradas = array();
		foreach ( $this->ficheros_php() as $php ) {
			if ( preg_match_all( '/wp_ajax(?:_nopriv)?_([a-z0-9_]+)/', (string) file_get_contents( $php ), $m ) ) {
				foreach ( $m[1] as $accion ) {
					$registradas[ $accion ] = true;
				}
			}
		}

		$this->assertNotEmpty( $registradas, 'Debería haber acciones registradas en el PHP.' );

		foreach ( $publicadas as $accion => $origen ) {
			$this->assertArrayHasKey(
				$accion,
				$registradas,
				"{$origen} publica «{$accion}» pero el plugin no registra esa acción (wp_ajax_{$accion}): la llamada fallará y el usuario verá «Error desconocido.»"
			);
		}
	}

	public function test_ningun_nombre_de_accion_queda_del_prefijo_viejo(): void {
		// `conv_enroll_*` es el prefijo viejo: el plugin registra `convoca_*`.
		foreach ( (array) glob( $this->raiz() . '/assets/js/*.js' ) as $js ) {
			$this->assertStringNotContainsString(
				"ajaxPost('conv_enroll_",
				(string) file_get_contents( $js ),
				basename( $js ) . ' publica una acción con el prefijo viejo.'
			);
		}
	}
}
