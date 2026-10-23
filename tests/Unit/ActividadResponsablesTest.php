<?php
/**
 * Quién es responsable de una actividad.
 *
 * `CPT_Actividad::is_user_responsible()` decide quién puede tocar una actividad: el listado, sus
 * inscripciones, el check-in. Tres caminos, y cada uno es una puerta distinta:
 *
 * 1. quien administra el sitio,
 * 2. quien está en la lista de responsables de esa actividad (`_convoca_responsables`, separada por comas),
 * 3. un voluntario aprobado con inscripción **confirmada** en esa actividad.
 *
 * El caso que más importa: la lista se guarda como texto libre —«5, 7,12»— así que los espacios sobran y
 * hay que limpiarlos, y un número no puede confundirse con otro (`7` no es responsable de `70`).
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll\Tests\Unit;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Responsabilidad sobre una actividad.
 */
final class ActividadResponsablesTest extends TestCase {

	/**
	 * Cada prueba parte sin permisos concedidos de más, sin roles y sin inscripciones.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['convoca_test_user_can']   = array();
		$GLOBALS['convoca_test_roles']      = array();
		$GLOBALS['convoca_test_posts']      = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	/**
	 * Y no deja restos.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['convoca_test_user_can']   = array();
		$GLOBALS['convoca_test_roles']      = array();
		$GLOBALS['convoca_test_posts']      = array();
		$GLOBALS['_wp_stores']['post_meta'] = array();
	}

	/**
	 * Pone la lista de responsables de una actividad.
	 *
	 * @param int    $actividad Identificador de la actividad.
	 * @param string $lista     Lista tal cual se guarda.
	 * @return void
	 */
	private function responsables( int $actividad, string $lista ): void {
		$GLOBALS['_wp_stores']['post_meta'][ $actividad ]['_convoca_responsables'] = $lista;
	}

	// ── administración ─────────────────────────────────────────────────────────────────

	/**
	 * Quien administra el sitio es responsable de todo, sin figurar en ninguna lista.
	 *
	 * @return void
	 */
	public function test_quien_administra_es_responsable_de_todo(): void {
		$this->assertTrue( CPT_Actividad::is_user_responsible( 1, 200 ) );
	}

	/**
	 * Sin `manage_options`, administrar deja de valer.
	 *
	 * @return void
	 */
	public function test_sin_manage_options_no_basta_con_ser_admin(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$GLOBALS['convoca_test_roles'][ 1 ]                 = array( 'administrator' );

		$this->assertFalse(
			CPT_Actividad::is_user_responsible( 1, 200 ),
			'ser administrador no da acceso por sí solo: manda la capacidad, no el nombre del rol'
		);
	}

	// ── lista de responsables ─────────────────────────────────────────────────────────

	/**
	 * Un usuario de la lista es responsable.
	 *
	 * @return void
	 */
	public function test_un_usuario_de_la_lista_es_responsable(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$this->responsables( 200, '5,7,12' );

		$this->assertTrue( CPT_Actividad::is_user_responsible( 7, 200 ) );
	}

	/**
	 * Con espacios alrededor, también: la lista se escribe a mano.
	 *
	 * @return void
	 */
	public function test_los_espacios_de_la_lista_no_estorban(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$this->responsables( 200, ' 5 , 7 , 12 ' );

		$this->assertTrue( CPT_Actividad::is_user_responsible( 7, 200 ), 'sin trim, « 7» no coincide con 7' );
	}

	/**
	 * Un número no se confunde con otro que lo contenga.
	 *
	 * @return void
	 */
	public function test_un_numero_no_arrastra_a_otro(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$this->responsables( 200, '70,700' );

		$this->assertFalse( CPT_Actividad::is_user_responsible( 7, 200 ), '7 no es responsable de una actividad de 70' );
	}

	/**
	 * Quien no está en la lista y no es administrador, no.
	 *
	 * @return void
	 */
	public function test_quien_no_esta_en_la_lista_no_es_responsable(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$this->responsables( 200, '5,7' );

		$this->assertFalse( CPT_Actividad::is_user_responsible( 9, 200 ) );
	}

	// ── voluntario aprobado con inscripción confirmada ────────────────────────────────

	/**
	 * Un voluntario aprobado con inscripción confirmada es responsable.
	 *
	 * @return void
	 */
	public function test_un_voluntario_aprobado_con_inscripcion_confirmada(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$GLOBALS['convoca_test_roles'][ 33 ]                = array( 'voluntario_aprobado' );
		$GLOBALS['convoca_test_posts']                      = array( 501 );

		$this->assertTrue( CPT_Actividad::is_user_responsible( 33, 200 ) );
	}

	/**
	 * Un voluntario aprobado **sin** inscripción no lo es.
	 *
	 * @return void
	 */
	public function test_un_voluntario_aprobado_sin_inscripcion_no_lo_es(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$GLOBALS['convoca_test_roles'][ 33 ]                = array( 'voluntario_aprobado' );
		$GLOBALS['convoca_test_posts']                      = array();

		$this->assertFalse( CPT_Actividad::is_user_responsible( 33, 200 ), 'aprobado no es apuntado' );
	}

	/**
	 * Con inscripción pero sin el rol aprobado, tampoco.
	 *
	 * @return void
	 */
	public function test_con_inscripcion_pero_sin_el_rol_aprobado_no_lo_es(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$GLOBALS['convoca_test_roles'][ 33 ]                = array( 'suscriptor' );
		$GLOBALS['convoca_test_posts']                      = array( 501 );

		$this->assertFalse(
			CPT_Actividad::is_user_responsible( 33, 200 ),
			'una inscripción sin aprobación no da acceso'
		);
	}

	// ── recordatorios ──────────────────────────────────────────────────────────────────

	/**
	 * Los cuatro recordatorios son los que son.
	 *
	 * @return void
	 */
	public function test_los_recordatorios_son_cuatro(): void {
		$tipos = CPT_Actividad::reminder_types();

		$this->assertSame(
			array( 'reminder_7dias', 'reminder_1dia', 'reminder_1hora', 'reminder_post_evento' ),
			array_keys( $tipos ),
			'si desaparece uno, los avisos programados dejan de salir sin que nadie se entere'
		);
	}
}
