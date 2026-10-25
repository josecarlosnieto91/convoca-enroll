<?php
/**
 * Las migraciones de datos de Enroll.
 *
 * `includes/Enroll_Upgrade_Manager.php` estaba al 0 % (108 statements) y es lo que se ejecuta **una vez** sobre
 * la base de un centro que lleva años funcionando: unifica valores, normaliza claves y enlaza registros de horas
 * con su inscripción. Una migración mal hecha no se nota al día siguiente: se nota cuando faltan horas o cuando
 * dos socios comparten un registro.
 *
 * La migración 1.3.0 (crear la tabla de códigos de reserva) NO se mide aquí: hace `require_once
 * ABSPATH . 'wp-admin/includes/upgrade.php'` y ese fichero solo existe en un WordPress real, no en el arnés —
 * es la excepción escrita del goal (DDL que depende de ABSPATH del runner).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Enroll_Upgrade_Manager;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MigracionesTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Enroll_Upgrade_Manager.php';

		$GLOBALS['_wp_stores']             = array( 'options' => array(), 'post_meta' => array(), 'queries' => array(), 'get_var_mapa' => array() );
		$GLOBALS['_test_users']            = array();
		$GLOBALS['_wp_posts_borrados']     = array();
		$GLOBALS['_convoca_logs']          = array();
		$GLOBALS['_wp_stores']['results_mapa'] = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_test_users'], $GLOBALS['_wp_posts_borrados'], $GLOBALS['_convoca_logs'] );
	}

	/**
	 * Llama a un método protegido del gestor de migraciones.
	 *
	 * @param string $metodo Método.
	 * @return void
	 */
	private function migrar( string $metodo ): void {
		$reflejo = new ReflectionMethod( Enroll_Upgrade_Manager::class, $metodo );
		$reflejo->invoke( $this->gestor() );
	}

	/**
	 * Instancia el gestor sin pasar por el constructor (que crea la tabla de reservas y engancha el cron).
	 *
	 * @return Enroll_Upgrade_Manager
	 */
	private function gestor(): Enroll_Upgrade_Manager {
		$reflejo = new \ReflectionClass( Enroll_Upgrade_Manager::class );
		return $reflejo->newInstanceWithoutConstructor();
	}

	/**
	 * Devuelve el valor de un método protegido.
	 *
	 * @param string $metodo Método.
	 * @return mixed
	 */
	private function valor( string $metodo ) {
		$reflejo = new ReflectionMethod( Enroll_Upgrade_Manager::class, $metodo );
		return $reflejo->invoke( $this->gestor() );
	}

	/**
	 * Las cuatro migraciones están declaradas, en orden, y con su opción de versión.
	 */
	public function test_el_contrato_del_gestor(): void {
		$callbacks = $this->valor( 'get_upgrade_callbacks' );

		$this->assertSame( array( '1.2.0', '1.3.0', '1.4.0', '1.5.0' ), array_keys( $callbacks ), 'las migraciones van en orden de versión' );
		$reflejo = new \ReflectionClass( Enroll_Upgrade_Manager::class );
		foreach ( $callbacks as $version => $metodo ) {
			// `is_callable` da false sobre un método protegido desde fuera; lo que importa es que EXISTA.
			$this->assertTrue(
				$reflejo->hasMethod( is_array( $metodo ) ? (string) $metodo[1] : (string) $metodo ),
				"la migración {$version} apunta a un método que existe"
			);
		}

		$this->assertSame( 'convoca_enroll_db_version', $this->valor( 'get_option_name' ), 'la versión se guarda en su opción' );
		$this->assertNotEmpty( $this->valor( 'get_transient_prefix' ) );
	}

	/**
	 * 1.2.0 unifica la asistencia: 1 pasa a «si» y 0 a «no».
	 */
	public function test_la_migracion_de_asistencia(): void {
		$this->migrar( 'upgrade_to_1_2_0' );

		$updates = array_values(
			array_filter(
				(array) $GLOBALS['_wp_stores']['queries'],
				static fn( $q ) => false !== strpos( (string) $q, 'UPDATE' )
			)
		);

		$this->assertCount( 2, $updates, 'una consulta por cada valor antiguo' );
		$this->assertStringContainsString( "meta_value = 'si'", $updates[0] );
		$this->assertStringContainsString( "meta_value = '1'", $updates[0] );
		$this->assertStringContainsString( "meta_value = 'no'", $updates[1] );
		$this->assertStringContainsString( "meta_value = '0'", $updates[1] );
	}

	/**
	 * 1.4.0 normaliza la clave del socio y NO pisa la que ya está bien.
	 */
	public function test_la_migracion_de_la_clave_de_socio_no_duplica(): void {
		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'_convoca_miembro_id' => array(
				// La clave buena ya vale 55 y la vieja dice 99: si la migración pisara, se vería en el valor.
				(object) array( 'post_id' => '10', 'meta_value' => '99' ),
				(object) array( 'post_id' => '11', 'meta_value' => '66' ), // Le falta: se copia.
			),
		);
		$GLOBALS['_wp_stores']['post_meta'][10]['_convoca_member_id'] = '55';

		$this->migrar( 'upgrade_to_1_4_0' );

		$this->assertSame( '55', $GLOBALS['_wp_stores']['post_meta'][10]['_convoca_member_id'], 'la que ya estaba bien no se toca' );
		$this->assertSame( '66', $GLOBALS['_wp_stores']['post_meta'][11]['_convoca_member_id'], 'la que faltaba se rellena' );

		foreach ( array( 10, 11 ) as $id ) {
			$this->assertArrayNotHasKey( '_convoca_miembro_id', $GLOBALS['_wp_stores']['post_meta'][ $id ] ?? array(), 'la clave antigua se retira' );
			$this->assertArrayNotHasKey( ' _convoca_miembro_id', $GLOBALS['_wp_stores']['post_meta'][ $id ] ?? array(), 'y también la que tenía un espacio delante' );
		}
	}

	/**
	 * 1.5.0 enlaza un registro de horas con su inscripción… solo si hay UNA sola coincidencia.
	 */
	public function test_la_migracion_de_horas_enlaza_solo_si_no_hay_ambiguedad(): void {
		$GLOBALS['_test_users'][7] = (object) array( 'ID' => 7, 'user_email' => 'ana@example.test', 'display_name' => 'Ana' );
		$GLOBALS['_test_users'][8] = (object) array( 'ID' => 8, 'user_email' => 'luis@example.test', 'display_name' => 'Luis' );

		$GLOBALS['_wp_stores']['results_mapa'] = array(
			'registro_hora' => array(
				(object) array( 'ID' => '100', 'actividad_id' => '20', 'usuario_id' => '7' ), // Una inscripción: enlaza.
				(object) array( 'ID' => '101', 'actividad_id' => '20', 'usuario_id' => '8' ), // Dos: ambiguo, no enlaza.
				(object) array( 'ID' => '102', 'actividad_id' => '20', 'usuario_id' => '99' ), // Sin usuario: no enlaza.
			),
		);

		// Se responde por orden a las consultas de inscripciones: 1 para el primero, 2 para el segundo, 0 el tercero.
		$GLOBALS['_wp_stores']['col_cola'] = array( array( '501' ), array( '502', '503' ), array() );

		$this->migrar( 'upgrade_to_1_5_0' );

		$this->assertSame( '501', (string) $GLOBALS['_wp_stores']['post_meta'][100]['_convoca_origen_id'], 'con una sola coincidencia, se enlaza' );
		$this->assertArrayHasKey( '_convoca_origen', $GLOBALS['_wp_stores']['post_meta'][100] );

		$this->assertArrayNotHasKey( '_convoca_origen_id', $GLOBALS['_wp_stores']['post_meta'][101] ?? array(), 'con dos candidatas NO se adivina' );
		$this->assertArrayNotHasKey( '_convoca_origen_id', $GLOBALS['_wp_stores']['post_meta'][102] ?? array(), 'sin usuario no hay enlace' );
	}
}
