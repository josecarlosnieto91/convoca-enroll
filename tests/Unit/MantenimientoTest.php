<?php
/**
 * El mantenimiento diario: validar la integridad y repararla.
 *
 * `includes/Maintenance.php` estaba al 0 % (120 statements) y es el único sitio del plugin que **borra** datos
 * de socios en automático. Lo que se fija aquí, por orden de gravedad:
 *
 * 1. Solo se borra una inscripción huérfana (sin actividad válida). Una inscripción con actividad NO se toca,
 *    ni aunque su contador de plazas esté mal: el contador se recalcula, la inscripción no se borra.
 * 2. El recuento real manda sobre la meta: si la meta dice 5 y hay 3, se escribe 3.
 * 3. Los logs se limpian SOLO los de Enroll (`%Enroll%`) y con la retención configurada: la tabla es compartida.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Maintenance;
use PHPUnit\Framework\TestCase;

class MantenimientoTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
		require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Maintenance.php';

		$GLOBALS['_wp_stores']         = array( 'options' => array(), 'post_meta' => array(), 'queries' => array(), 'get_var_mapa' => array() );
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['_wp_stores']['posts'] = array();
		$GLOBALS['_convoca_acciones']  = array();
		$GLOBALS['_wp_posts_borrados'] = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = array( 'options' => array() );
		$GLOBALS['convoca_test_posts'] = array();
		unset( $GLOBALS['_convoca_acciones'], $GLOBALS['_wp_posts_borrados'] );
	}

	/**
	 * Da de alta un post en los dos almacenes del arnés.
	 *
	 * @param int    $id    Identificador.
	 * @param string $tipo  Tipo de contenido.
	 * @param array  $meta  Meta.
	 */
	private function post( int $id, string $tipo, array $meta = array() ): void {
		$post              = new \stdClass();
		$post->ID          = $id;
		$post->post_type   = $tipo;
		$post->post_status = 'publish';
		$post->post_title  = 'Post ' . $id;

		$GLOBALS['convoca_test_posts'][ $id ]  = $post;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;

		foreach ( $meta as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][ $id ][ '_convoca_' . $clave ] = $valor;
		}
	}

	/**
	 * El constructor engancha el mantenimiento y programa el cron si no estaba.
	 */
	public function test_el_constructor_programa_el_mantenimiento(): void {
		new Maintenance();

		$this->assertNotEmpty( $GLOBALS['_convoca_acciones']['convoca_enroll_daily_maintenance'] ?? array() );
		$this->assertCount( 2, $GLOBALS['_convoca_acciones']['convoca_enroll_daily_maintenance'], 'valida y repara' );
	}

	/**
	 * Una inscripción sin actividad válida se cuenta como huérfana y se explica cuál.
	 */
	public function test_validar_detecta_las_huerfanas(): void {
		$this->post( 10, 'inscripcion', array( 'actividad_id' => '999' ) );

		$resultado = Maintenance::validar_integridad();

		$this->assertSame( 1, $resultado['orphans'] );
		$this->assertNotEmpty( $resultado['errors'] );
		$this->assertStringContainsString( '#10', $resultado['errors'][0] );
	}

	/**
	 * El recuento real manda: si la meta dice otra cosa, se detecta la discrepancia.
	 */
	public function test_validar_detecta_el_descuadre_de_plazas(): void {
		$this->post( 20, 'actividad', array( 'plazas_ocupadas' => '5' ) );
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SELECT COUNT(*) FROM' => '3' );

		$resultado = Maintenance::validar_integridad();

		$this->assertSame( 1, $resultado['capacity_mismatch'] );
		$this->assertStringContainsString( 'recuento real es 3', $resultado['errors'][0] );
	}

	/**
	 * Sin problemas no hay avisos: ni errores ni ruido en el registro.
	 */
	public function test_sin_problemas_no_hay_errores(): void {
		$this->post( 30, 'actividad', array( 'plazas_ocupadas' => '2' ) );
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SELECT COUNT(*) FROM' => '2' );

		$resultado = Maintenance::validar_integridad();

		$this->assertSame( 0, $resultado['orphans'] );
		$this->assertSame( 0, $resultado['capacity_mismatch'] );
		$this->assertSame( array(), $resultado['errors'] );
	}

	/**
	 * Reparar borra SOLO las huérfanas: una inscripción con actividad válida no se toca aunque el contador esté mal.
	 */
	public function test_reparar_solo_borra_las_huerfanas(): void {
		$this->post( 40, 'actividad', array( 'plazas_ocupadas' => '9' ) );
		$this->post( 41, 'inscripcion', array( 'actividad_id' => '40' ) ); // Buena: su actividad existe.
		$this->post( 42, 'inscripcion', array( 'actividad_id' => '777' ) ); // Huérfana.
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SELECT COUNT(*) FROM' => '1' );

		$stats = Maintenance::reparar_integridad();

		$this->assertSame( 1, $stats['orphans_deleted'], 'solo la huérfana' );
		$this->assertContains( 42, (array) $GLOBALS['_wp_posts_borrados'], 'la huérfana se borra de verdad' );
		$this->assertNotContains( 41, (array) $GLOBALS['_wp_posts_borrados'], 'la inscripción con actividad NO se borra' );
		$this->assertNotContains( 40, (array) $GLOBALS['_wp_posts_borrados'], 'la actividad tampoco se toca' );
	}

	/**
	 * El contador se reescribe con el recuento real.
	 */
	public function test_reparar_reescribe_el_contador_con_el_recuento_real(): void {
		$this->post( 50, 'actividad', array( 'plazas_ocupadas' => '9' ) );
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SELECT COUNT(*) FROM' => '4' );

		$stats = Maintenance::reparar_integridad();

		$this->assertSame( 1, $stats['activities_recounted'] );
		$this->assertSame( '4', (string) $GLOBALS['_wp_stores']['post_meta'][50]['_convoca_plazas_ocupadas'], 'manda el recuento real' );
	}

	/**
	 * Los logs se limpian solo los de Enroll y con la retención configurada.
	 */
	public function test_los_logs_se_limpian_solo_los_de_enroll(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'log_retention_days' => 90 );
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SHOW TABLES' => 'wp_convoca_logs' );
		$GLOBALS['convoca_test_query_retorno'] = array( 'DELETE FROM wp_convoca_logs' => 7 );

		$stats = Maintenance::reparar_integridad();

		$delete = array_values(
			array_filter(
				(array) $GLOBALS['_wp_stores']['queries'],
				static fn( $q ) => false !== strpos( (string) $q, 'DELETE FROM wp_convoca_logs' )
			)
		);

		$this->assertNotEmpty( $delete, 'se lanza la limpieza' );
		$this->assertStringContainsString( "'%Enroll%'", $delete[0], 'solo los registros de Enroll' );
		$this->assertStringContainsString( 'INTERVAL 90 DAY', $delete[0], 'con la retención configurada, no con el valor por defecto' );
		$this->assertSame( 7, $stats['logs_cleaned'], 'se informa de cuántos se borraron' );
	}

	/**
	 * Sin tabla de logs no se intenta borrar nada.
	 */
	public function test_sin_tabla_de_logs_no_se_borra(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'SHOW TABLES' => 'otra_tabla' );

		$stats = Maintenance::reparar_integridad();

		foreach ( (array) $GLOBALS['_wp_stores']['queries'] as $q ) {
			$this->assertStringNotContainsString( 'DELETE FROM', (string) $q );
		}
		$this->assertSame( 0, $stats['logs_cleaned'] );
	}
}
