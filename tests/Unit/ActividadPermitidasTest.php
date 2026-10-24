<?php
/**
 * Qué actividades ve cada persona.
 *
 * `get_allowed_activities_ids()` decide el filtro de tres pantallas: el listado, el panel del monitor y los informes.
 * Si devolviera una lista vacía en vez de `[0]`, WordPress interpretaría «sin filtro» y un monitor sin actividades
 * asignadas **vería todas** —con sus inscripciones y datos personales—. Es la diferencia entre no ver nada y verlo todo.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Actividades permitidas.
 */
final class ActividadPermitidasTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'results'      => array(),
			'results_mapa' => array(),
		);
		$GLOBALS['convoca_test_caps']           = array( 'manage_options' => false );
		$GLOBALS['convoca_test_roles']          = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		// `get_current_user_id()` del arnés devuelve 1.
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['convoca_test_roles']          = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();

	}

	/**
	 * Un administrador no lleva filtro: ve todo.
	 */
	public function test_el_admin_ve_todo(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_options' => true );

		$this->assertNull( CPT_Actividad::get_allowed_activities_ids(), 'null = sin filtro' );
	}

	/**
	 * Sin asignaciones, ni responsables ni inscripción: `[0]`, que no casa con ninguna actividad.
	 *
	 * Una lista vacía sería «sin filtro» y el monitor vería todas.
	 */
	public function test_sin_nada_devuelve_cero(): void {
		$this->assertSame( array( 0 ), CPT_Actividad::get_allowed_activities_ids() );
	}

	/**
	 * Las actividades repartidas en Delegados.
	 */
	public function test_las_asignadas(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_delegados_actividades'] = array( '1' => array( 61, 62 ) );

		$this->assertSame( array( 61, 62 ), CPT_Actividad::get_allowed_activities_ids() );
	}

	/**
	 * Las que tiene como responsable en la ficha (aunque no se le hayan repartido).
	 */
	public function test_las_de_responsable(): void {
		$actividad                        = new \WP_Post();
		$actividad->ID                    = 63;
		$actividad->post_type             = 'actividad';
		$GLOBALS['convoca_test_posts']    = array( $actividad );

		$this->assertSame( array( 63 ), CPT_Actividad::get_allowed_activities_ids() );
	}

	/**
	 * Un voluntario aprobado suma la actividad de su inscripción confirmada.
	 */
	public function test_el_voluntario_suma_su_actividad(): void {
		$GLOBALS['convoca_test_roles'][1] = array( 'voluntario_aprobado' );

		// Un usuario de verdad, con su correo.
		$usuario                         = new \WP_User();
		$usuario->ID                     = 1;
		$usuario->user_email             = 'user1@test.com';
		$usuario->roles                  = array( 'voluntario_aprobado' );
		$GLOBALS['convoca_test_usuario'] = $usuario;

		$inscripcion                        = new \WP_Post();
		$inscripcion->ID                    = 70;
		$inscripcion->post_type             = 'inscripcion';
		$GLOBALS['convoca_test_posts']      = array( $inscripcion );
		$GLOBALS['_wp_stores']['post_meta'][70] = array( '_convoca_actividad_id' => 64 );

		$this->assertContains( 64, CPT_Actividad::get_allowed_activities_ids(), 'la actividad de su inscripción' );
	}
}
