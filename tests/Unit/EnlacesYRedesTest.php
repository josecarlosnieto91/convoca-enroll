<?php
/**
 * Reglas de los enlaces de correo y de las llamadas de red (issues #66, #67 y #68).
 *
 * - **#66**: sin página de panel configurada, el correo enlazaba a `home_url( '/panel-reservas/' )`, una
 *   ruta inventada que en algún sitio da 404. Ahora se enlaza la ficha de la actividad.
 * - **#67**: ninguna llamada `wp_remote_*` puede quedarse sin `timeout`: un extremo lento bloquea la
 *   petición que la dispara.
 * - **#68**: en modo simulación no se escribe el payload (textos, enlaces, imágenes) en el log.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Email_Automation;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';

final class EnlacesYRedesTest extends TestCase {

	/**
	 * Estado limpio.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array(), 'actions' => array() );
		unset( $GLOBALS['_wp_pages'] );
	}

	/**
	 * Sin página de panel, el enlace es la ficha de la actividad.
	 */
	public function test_sin_pagina_de_panel_el_enlace_es_la_actividad(): void {
		$url = Email_Automation::panel_url( 4242 );

		$this->assertStringContainsString( '?p=4242', $url, 'tiene que apuntar a la actividad' );
		$this->assertStringNotContainsString( '/panel-reservas/', $url, 'y no a una ruta inventada' );
	}

	/**
	 * Con página de panel configurada, manda esa.
	 */
	public function test_con_pagina_de_panel_manda_la_pagina(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_panel_reservas_page_id'] = 77;

		$url = Email_Automation::panel_url( 4242 );

		$this->assertStringContainsString( '?p=77', $url );
	}

	/**
	 * Ninguna llamada de red sin `timeout`.
	 */
	public function test_ninguna_llamada_de_red_sin_timeout(): void {
		$sin_timeout = array();

		foreach ( $this->ficheros( 'php' ) as $archivo ) {
			$codigo = (string) file_get_contents( $archivo );
			foreach ( $this->llamadas_http( $codigo ) as $bloque ) {
				if ( ! str_contains( $bloque, 'timeout' ) ) {
					$sin_timeout[] = $archivo;
					break;
				}
			}
		}

		$this->assertSame( array(), $sin_timeout, 'Estas llamadas no llevan timeout: ' . implode( ', ', $sin_timeout ) );
	}

	/**
	 * En simulación no se registra el payload.
	 */
	public function test_la_simulacion_no_escribe_el_payload_en_el_log(): void {
		foreach ( $this->ficheros( 'php' ) as $archivo ) {
			if ( ! str_contains( $archivo, '/social/' ) ) {
				continue;
			}

			$codigo = (string) file_get_contents( $archivo );

			$this->assertStringNotContainsString(
				'error_log( \'[CONVOCA DRY-RUN]',
				$codigo,
				'El payload de simulación no puede ir al log: ' . $archivo
			);
		}
	}

	/**
	 * Ficheros del plugin, sin vendor ni pruebas.
	 *
	 * @param string $extension Extensión.
	 * @return array<int, string>
	 */
	private function ficheros( string $extension ): array {
		$raiz = dirname( __DIR__, 2 );
		$out  = array();

		$iterador = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $raiz, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterador as $fichero ) {
			$ruta = $fichero->getPathname();
			if ( ! str_ends_with( $ruta, '.' . $extension ) ) {
				continue;
			}
			if ( str_contains( $ruta, '/vendor/' ) || str_contains( $ruta, '/tests/' ) || str_contains( $ruta, '/node_modules/' ) ) {
				continue;
			}
			$out[] = $ruta;
		}

		return $out;
	}

	/**
	 * Bloques de llamadas HTTP del código.
	 *
	 * @param string $codigo Código PHP.
	 * @return array<int, string>
	 */
	private function llamadas_http( string $codigo ): array {
		$out = array();

		if ( ! preg_match_all( '/wp_remote_(?:get|post|request)\s*\(/', $codigo, $m, PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}

		foreach ( $m[0] as $encontrado ) {
			$inicio = strpos( $codigo, '(', (int) $encontrado[1] );
			$nivel  = 0;
			for ( $i = (int) $inicio; $i < strlen( $codigo ); $i++ ) {
				if ( '(' === $codigo[ $i ] ) {
					++$nivel;
				} elseif ( ')' === $codigo[ $i ] ) {
					--$nivel;
					if ( 0 === $nivel ) {
						$out[] = substr( $codigo, (int) $inicio, $i - (int) $inicio );
						break;
					}
				}
			}
		}

		return $out;
	}
}
