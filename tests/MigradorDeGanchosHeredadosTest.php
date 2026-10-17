<?php
/**
 * Contrato del migrador de ganchos heredados (enroll#31).
 *
 * Dos comprobaciones de fuente, y las dos importan: que ningún fichero del código actual programe los
 * ganchos heredados (si alguno lo hiciera, el migrador los estaría borrando), y que el migrador esté
 * enganchado al `init`.
 *
 * Los comentarios se quitan antes de buscar: el propio migrador nombra los ganchos en su docblock y,
 * si no se quitan, la prueba se pone roja contra su explicación.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Migrator;
use PHPUnit\Framework\TestCase;

class MigradorDeGanchosHeredadosTest extends TestCase {

	/**
	 * Código de un fichero sin comentarios.
	 */
	private function codigo_sin_comentarios( string $ruta ): string {
		$tokens = token_get_all( (string) file_get_contents( $ruta ) );
		$salida = '';
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					continue;
				}
				$salida .= $token[1];
				continue;
			}
			$salida .= $token;
		}
		return $salida;
	}

	/**
	 * Todos los ficheros PHP del plugin, sin vendor ni pruebas.
	 *
	 * @return string[]
	 */
	private function ficheros_del_plugin(): array {
		$raiz = dirname( __DIR__ );
		$lista = array( $raiz . '/convoca-enroll.php' );
		$iter  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $raiz . '/includes', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iter as $f ) {
			if ( $f->isFile() && 'php' === $f->getExtension() ) {
				$lista[] = $f->getPathname();
			}
		}
		return $lista;
	}

	public function test_ningun_fichero_programa_los_ganchos_heredados(): void {
		$programadores = array( 'wp_schedule_event', 'wp_schedule_single_event', 'as_schedule_recurring_action', 'as_schedule_single_action' );
		$culpables     = array();

		foreach ( $this->ficheros_del_plugin() as $ruta ) {
			$codigo = $this->codigo_sin_comentarios( $ruta );
			foreach ( Migrator::GANCHOS_HEREDADOS as $gancho ) {
				$pos = strpos( $codigo, "'" . $gancho . "'" );
				if ( false !== $pos ) {
					$culpables[] = basename( $ruta ) . ' nombra ' . $gancho;
				}
			}
			// Y que el programador no use una de esas llamadas, por si el nombre viene de una variable.
			foreach ( $programadores as $fn ) {
				if ( false !== strpos( $codigo, $fn ) && false !== strpos( $codigo, 'biodevas_enroll_' ) ) {
					$culpables[] = basename( $ruta ) . ' programa con ' . $fn . ' un gancho heredado';
				}
			}
		}

		$this->assertSame( array(), $culpables, 'Estos ficheros tocan ganchos heredados: ' . implode( '; ', $culpables ) );
	}

	public function test_el_migrador_esta_enganchado_al_init(): void {
		$codigo = $this->codigo_sin_comentarios( dirname( __DIR__ ) . '/includes/Migrator.php' );
		$this->assertStringContainsString( "add_action( 'init'", $codigo );
		$this->assertStringContainsString( 'migrar_ganchos_heredados', $codigo );
	}

	public function test_la_lista_de_heredados_no_esta_vacia_y_son_del_nombre_anterior(): void {
		$this->assertNotEmpty( Migrator::GANCHOS_HEREDADOS );
		foreach ( Migrator::GANCHOS_HEREDADOS as $gancho ) {
			$this->assertStringStartsWith( 'biodevas_enroll_', $gancho, 'Se esperaba un gancho del nombre anterior: ' . $gancho );
		}
	}
}
