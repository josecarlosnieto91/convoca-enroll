<?php
/**
 * El comando de migración de precios (WP-CLI) y el registro de estilos de actividad.
 *
 * `includes/WP_CLI_Migration.php` (41 statements) y `media/class-event-style-registry.php` (41) estaban al 0 %.
 * La migración **modifica y borra metadatos en producción**, así que lo que se prueba aquí es su orden, que no
 * pise un precio bueno y que borre solo lo que dice borrar.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Event_Style_Registry;
use Convoca\Enroll\WP_CLI_Migration;
use PHPUnit\Framework\TestCase;

class MigracionYEstilosTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']                  = array( 'options' => array(), 'post_meta' => array() );
		// `_wp_cli_comandos` NO se vacía: lo llena el producto con un `add_command` de nivel de fichero detrás de un
		// `require_once` que no se repite. Vaciarlo aquí dejaba a esta prueba sin comando en cuanto otra suite
		// hubiera cargado la clase antes (enroll#49). El registro es del producto, no del caso.
		$GLOBALS['_wp_cli_salida']              = array();
		$GLOBALS['convoca_test_get_posts_cola'] = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		// El registro de comandos tampoco se borra al salir, por el mismo motivo.
		unset( $GLOBALS['_wp_cli_salida'], $GLOBALS['convoca_test_get_posts_cola'], $GLOBALS['convoca_test_get_posts_args'] );
	}

	/**
	 * Al cargarse, el comando queda registrado en WP-CLI.
	 */
	public function test_el_comando_queda_registrado(): void {
		// Referenciar la clase la autoloada, y el fichero registra el comando al cargarse.
		$this->assertTrue( class_exists( WP_CLI_Migration::class ) );

		$this->assertContains( 'conv migrate', (array) ( $GLOBALS['_wp_cli_comandos'] ?? array() ) );
	}

	/**
	 * La migración: primero las inscripciones, luego los precios y al final la limpieza.
	 */
	public function test_la_migracion_completa(): void {
		$GLOBALS['convoca_test_get_posts_cola'] = array( array() );

		( new WP_CLI_Migration() )->rgpd_prices( array(), array() );

		$consultas = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringContainsString( 'UPDATE wp_postmeta', $consultas, 'primero migra las inscripciones' );
		$this->assertStringContainsString( "SET meta_value = 'socio_dia'", $consultas );
		$this->assertStringContainsString( "meta_value = 'general'", $consultas, 'solo las que están en «general»' );
		$this->assertStringContainsString( 'DELETE FROM wp_postmeta', $consultas, 'y al final limpia el metadato viejo' );
		// El borrado va POR CLAVE: sin esto, un `WHERE` mal puesto se lleva por delante todo el postmeta.
		$this->assertStringContainsString(
			"WHERE meta_key = '_convoca_precio_general'",
			$consultas,
			'borra solo el precio viejo, no todo el postmeta del sitio'
		);
		$preparadas = implode( "\n", (array) ( $GLOBALS['db_prepares'] ?? array() ) );
		$this->assertStringContainsString( 'WHERE meta_key = %s', $preparadas, 'y la consulta va preparada' );

		$salida = implode( "\n", (array) ( $GLOBALS['_wp_cli_salida'] ?? array() ) );
		$this->assertStringContainsString( 'Iniciando migración', $salida );
		$this->assertStringContainsString( 'Inscripciones migradas', $salida );
		$this->assertStringContainsString( 'Actividades migradas', $salida );
		$this->assertStringContainsString( 'completada con éxito', $salida );
	}

	/**
	 * Copia el precio general al de socio/día SOLO si el de socio/día está vacío.
	 */
	public function test_no_pisa_un_precio_de_socio_ya_puesto(): void {
		$GLOBALS['convoca_test_get_posts_cola'] = array(
			array( (object) array( 'ID' => 11 ), (object) array( 'ID' => 12 ) ),
		);
		$GLOBALS['_wp_stores']['post_meta'][11] = array(
			'_convoca_precio_general'   => '5',
			'_convoca_precio_socio_dia' => '',
		);
		$GLOBALS['_wp_stores']['post_meta'][12] = array(
			'_convoca_precio_general'   => '5',
			'_convoca_precio_socio_dia' => '3',
		);

		( new WP_CLI_Migration() )->rgpd_prices( array(), array() );

		$this->assertSame( '5', $GLOBALS['_wp_stores']['post_meta'][11]['_convoca_precio_socio_dia'], 'la que no tenía precio, lo recibe' );
		$this->assertSame( '3', $GLOBALS['_wp_stores']['post_meta'][12]['_convoca_precio_socio_dia'], 'la que YA tenía precio de socio no se toca' );
	}

	/**
	 * Recorre todas las actividades, sin límite de página.
	 */
	public function test_recorre_todas_las_actividades(): void {
		$GLOBALS['convoca_test_get_posts_cola'] = array( array() );

		( new WP_CLI_Migration() )->rgpd_prices( array(), array() );

		$args = (array) $GLOBALS['convoca_test_get_posts_args'][0];
		$this->assertSame( 'actividad', $args['post_type'] );
		$this->assertSame( -1, $args['posts_per_page'] );
		$this->assertSame( 'any', $args['post_status'], 'también las que están en borrador' );
	}

	/**
	 * Los estilos: once tipos, todos con lo que necesita un cartel.
	 */
	public function test_los_estilos_registrados(): void {
		$estilos = Event_Style_Registry::all();

		$this->assertCount( 11, $estilos );
		foreach ( $estilos as $tipo => $estilo ) {
			foreach ( array( 'label', 'color', 'text_color', 'icon', 'accent' ) as $clave ) {
				$this->assertArrayHasKey( $clave, $estilo, "el estilo {$tipo} no tiene {$clave}" );
				$this->assertNotSame( '', (string) $estilo[ $clave ] );
			}
			$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', $estilo['color'], "el color de {$tipo} no es un hex" );
			$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', $estilo['accent'] );
		}
	}

	/**
	 * Un tipo conocido devuelve SU estilo; uno inventado, el de por defecto (nunca un error).
	 */
	public function test_tipo_conocido_y_desconocido(): void {
		$naturaleza = Event_Style_Registry::get( 'naturaleza' );
		$this->assertSame( '🌿', $naturaleza['icon'] );
		$this->assertSame( '#4caf50', $naturaleza['color'] );

		$inventado = Event_Style_Registry::get( 'lo_que_sea' );
		$this->assertSame( Event_Style_Registry::DEFAULT_STYLE, $inventado, 'un tipo que no existe no puede romper un cartel' );

		$this->assertSame( '#ff8700', Event_Style_Registry::color( 'lo_que_sea' ) );
		$this->assertSame( '📌', Event_Style_Registry::icon( 'lo_que_sea' ) );
		$this->assertSame( '#4caf50', Event_Style_Registry::color( 'naturaleza' ) );
		$this->assertSame( '🌿', Event_Style_Registry::icon( 'naturaleza' ) );
	}
}
