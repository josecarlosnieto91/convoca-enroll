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

	/**
	 * Todos los ficheros JS del plugin, no solo los de `assets/js/`.
	 *
	 * `blocks/` y `public/assets/pwa/` también hablan con WordPress: mirar solo un directorio deja
	 * fuera justo lo que nadie revisa.
	 *
	 * @return array<int, string>
	 */
	private function ficheros_js(): array {
		$fuera = array( 'vendor', 'tests', 'node_modules', '.git', 'languages' );
		$lista = array();
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->raiz() ) ) as $f ) {
			$ruta = $f->getPathname();
			foreach ( $fuera as $carpeta ) {
				if ( str_contains( $ruta, '/' . $carpeta . '/' ) ) {
					continue 2;
				}
			}
			if ( str_ends_with( $ruta, '.js' ) ) {
				$lista[] = $ruta;
			}
		}
		return $lista;
	}

	public function test_toda_accion_publicada_desde_el_js_esta_registrada_en_php(): void {
		// Lo que publica el JavaScript. Se miran DOS formas de llamar, porque la primera versión de
		// esta prueba solo veía la primera y por ahí se colaron dos ficheros que publicaban acciones
		// inexistentes durante meses sin que nadie se enterara:
		//   1. el ayudante del plugin:  ajaxPost('convoca_accion', …)
		//   2. jQuery/fetch a pelo:      $.post(url, { action: 'convoca_accion', … })
		$publicadas = array();
		foreach ( $this->ficheros_js() as $js ) {
			$contenido = (string) file_get_contents( $js );
			$origen    = basename( $js );

			// TODAS las formas de publicar una acción, no dos. La versión anterior reconocía
			// `action: 'convoca_…'` y descartaba cualquier otro nombre **a propósito**, así que
			// `action: 'conv_eval_get_nonce'` —con el prefijo viejo— se le colaba: la llamada no
			// tenía manejador y el formulario de evaluación no se podía enviar. Aquí vale cualquier
			// literal que empiece por `conv`, con el prefijo que sea.
			$patrones = array(
				"/ajaxPost\\(\\s*['\"](conv[a-z0-9_]*)['\"]/",
				"/\\baction\\s*[:=]\\s*['\"](conv[a-z0-9_]*)['\"]/",
				"/\\baction=(conv[a-z0-9_]+)/",
			);
			foreach ( $patrones as $patron ) {
				if ( preg_match_all( $patron, $contenido, $m ) ) {
					foreach ( $m[1] as $accion ) {
						$publicadas[ $accion ] = $origen;
					}
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

	public function test_todo_objeto_de_configuracion_que_lee_el_js_lo_publica_el_php(): void {
		// El otro contrato del mismo tipo, que nadie cruzaba: el PHP publica variables con
		// `wp_localize_script()` y el JS las lee por su nombre. Si los dos nombres no coinciden, el
		// JS revienta con `ReferenceError` en la primera línea que las usa y **la función entera
		// deja de existir** — medido en el formulario de evaluación: el PHP publicaba
		// `convoca_eval_ajax` y el JS leía `conv_eval_ajax`.
		$publicados = array();
		foreach ( $this->ficheros_php() as $php ) {
			if ( preg_match_all( "/wp_localize_script\\s*\\([^,]+,\\s*['\"]([A-Za-z_$][\\w$]*)['\"]/", (string) file_get_contents( $php ), $m ) ) {
				foreach ( $m[1] as $nombre ) {
					$publicados[ $nombre ] = true;
				}
			}
		}

		$leidos = array();
		foreach ( $this->ficheros_js() as $js ) {
			$contenido = (string) file_get_contents( $js );
			if ( preg_match_all( "/\\b(conv[oa-z_]*_(?:ajax|config|data|i18n|vars))/", $contenido, $m ) ) {
				foreach ( $m[1] as $nombre ) {
					$leidos[ $nombre ] = basename( $js );
				}
			}
		}

		$this->assertNotEmpty( $publicados, 'El PHP no publica ninguna variable para el JS.' );
		foreach ( $leidos as $nombre => $origen ) {
			$this->assertArrayHasKey(
				$nombre,
				$publicados,
				"{$origen} lee «{$nombre}», que el PHP no publica con wp_localize_script(): el JS morirá con un ReferenceError."
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
