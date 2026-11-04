<?php
/**
 * Los ganchos heredados salen de una opción del sitio, no de un mu-plugin.
 *
 * Se prueba la mezcla de las dos fuentes (opción y filtro), que no se dupliquen, que un nombre que no
 * puede existir en WordPress se descarte y que una opción corrupta no reviente el migrador.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * Fuente de los ganchos heredados.
 */
class MigratorOpcionTest extends TestCase {

	/**
	 * Carga la clase y limpia opciones y filtros.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']['options'] = array();
		$GLOBALS['_wp_filters']           = array();

		require_once dirname( __DIR__, 2 ) . '/includes/Migrator.php';
	}

	/**
	 * Limpia.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']['options'] = array();
		$GLOBALS['_wp_filters']           = array();
	}

	/**
	 * Sin opción ni filtro no hay nada que limpiar.
	 */
	public function test_sin_opcion_ni_filtro_no_hay_ganchos(): void {
		$this->assertSame( array(), Migrator::ganchos_heredados() );
	}

	/**
	 * La opción y el filtro se suman, y lo repetido entra una sola vez.
	 */
	public function test_la_opcion_y_el_filtro_se_suman_sin_duplicar(): void {
		update_option( Migrator::OPTION_HEREDADOS, array( 'viejo_a', 'viejo_b' ) );
		add_filter(
			'convoca_enroll_ganchos_heredados',
			static function ( $ganchos ) {
				return array_merge( (array) $ganchos, array( 'viejo_b', 'viejo_c' ) );
			}
		);

		$this->assertSame( array( 'viejo_a', 'viejo_b', 'viejo_c' ), Migrator::ganchos_heredados() );
	}

	/**
	 * Un nombre que no puede ser un gancho de WordPress se descarta.
	 */
	public function test_descarta_nombres_invalidos(): void {
		update_option(
			Migrator::OPTION_HEREDADOS,
			array( 'bien_1', 'mal nombre', 'MAYUS', '', 'con-guion', 123, array( 'no' ) )
		);

		$this->assertSame( array( 'bien_1' ), Migrator::ganchos_heredados() );
	}

	/**
	 * Una opción corrupta no revienta: se ignora y manda el filtro.
	 */
	public function test_una_opcion_corrupta_no_revienta(): void {
		update_option( Migrator::OPTION_HEREDADOS, 'esto-no-es-una-lista' );
		add_filter(
			'convoca_enroll_ganchos_heredados',
			static function () {
				return array( 'del_filtro' );
			}
		);

		$this->assertSame( array( 'del_filtro' ), Migrator::ganchos_heredados() );
	}

	/**
	 * El comando queda registrado: si desaparece, el sitio vuelve a necesitar el mu-plugin.
	 */
	public function test_el_comando_de_ganchos_se_registra(): void {
		$raiz = dirname( __DIR__, 2 );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lectura de un fichero del propio plugin.
		$plugin = (string) file_get_contents( $raiz . '/convoca-enroll.php' );

		$this->assertStringContainsString( "'bde ganchos'", $plugin );
		$this->assertFileExists( $raiz . '/includes/CLI_Ganchos.php' );
	}
}
