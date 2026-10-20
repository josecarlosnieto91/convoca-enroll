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

	public function test_ningun_fichero_programa_ganchos_de_instalacion(): void {
		$programadores = array( 'wp_schedule_event', 'wp_schedule_single_event', 'as_schedule_recurring_action', 'as_schedule_single_action' );
		$culpables     = array();

		// El nombre anterior del plugin llevaba dentro el nombre de una instalación real:
		// no puede aparecer en NINGÚN fichero del paquete, ni como gancho ni como prefijo.
		// (La lista de heredados ya no vive en el código: la aporta el sitio por filtro.)
		foreach ( $this->ficheros_del_plugin() as $ruta ) {
			$codigo = $this->codigo_sin_comentarios( $ruta );
			if ( false !== stripos( $codigo, 'biodevas' ) ) { // convoca-hygiene-ignore: la prueba existe para prohibir ese prefijo; el literal es el patrón que se busca.
				$culpables[] = basename( $ruta ) . ' nombra el prefijo viejo';
			}
			foreach ( $programadores as $fn ) {
				if ( false !== strpos( $codigo, $fn ) && false !== stripos( $codigo, 'biodevas_enroll_' ) ) { // convoca-hygiene-ignore: patrón buscado por la prueba, no un gancho del código.
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

	public function test_los_heredados_los_aporta_el_sitio_por_filtro(): void {
		// Sin filtro no hay nada que limpiar: el producto no trae nombres de instalaciones.
		$this->assertSame( array(), Migrator::ganchos_heredados() );

		$filtro = static function ( $ganchos ) {
			return array_merge( (array) $ganchos, array( 'prefijo_viejo_cola', 'prefijo_viejo_avisos' ) );
		};
		add_filter( 'convoca_enroll_ganchos_heredados', $filtro );
		$this->assertSame( array( 'prefijo_viejo_cola', 'prefijo_viejo_avisos' ), Migrator::ganchos_heredados() );
		remove_filter( 'convoca_enroll_ganchos_heredados', $filtro );

		// Basura del filtro: ni ganchos vacíos ni valores que no son cadenas.
		$basura = static function () {
			return array( 'gancho_valido', '', null, array( 'no' ), 0 );
		};
		add_filter( 'convoca_enroll_ganchos_heredados', $basura );
		$this->assertSame( array( 'gancho_valido', '0' ), Migrator::ganchos_heredados() );
		remove_filter( 'convoca_enroll_ganchos_heredados', $basura );

		// Y si alguien devuelve algo que no es una lista, no se limpia nada.
		$roto = static function () {
			return 'no-es-una-lista';
		};
		add_filter( 'convoca_enroll_ganchos_heredados', $roto );
		$this->assertSame( array(), Migrator::ganchos_heredados() );
		remove_filter( 'convoca_enroll_ganchos_heredados', $roto );
	}
}
