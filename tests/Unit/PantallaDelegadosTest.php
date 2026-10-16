<?php
/**
 * La pantalla de Delegados existe, lista a los voluntarios y no es código muerto (enroll#26).
 *
 * MEDIDO EN EL CÓDIGO DESPLEGADO: `render_delegados()` estaba escrita entera —con su formulario y su
 * «Guardar Asignaciones»— y **ningún** `add_submenu_page` la registraba: no había forma de llegar a ella
 * (`admin.php?page=convoca-enroll-delegados` redirigía). Y su lista se construía con
 * `role__in => administrator, editor, author`, así que un voluntario o un monitor —justo quien hace el
 * check-in en la puerta— **no aparecía**. Con la opción vacía, un monitor recibe
 * «No tienes permiso para ver inscripciones de esta actividad».
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class PantallaDelegadosTest extends TestCase {

	private function leer( string $rel ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	}

	/** La pantalla se puede alcanzar desde el menú. */
	public function test_la_pantalla_esta_en_el_menu(): void {
		$fuente = $this->leer( 'admin/class-admin-page.php' );

		$this->assertStringContainsString( "add_submenu_page(", $fuente );
		$this->assertMatchesRegularExpression(
			"/add_submenu_page\([^;]*'convoca-enroll-delegados'[^;]*render_delegados/s",
			$fuente,
			'La pantalla de Delegados tiene que estar registrada: si no, es código muerto al que nadie llega'
		);
	}

	/** Y lista a quien de verdad reparte las actividades. */
	public function test_la_lista_incluye_a_voluntarios_y_monitores(): void {
		$fuente = $this->leer( 'admin/class-admin-page.php' );

		$this->assertStringContainsString( "'voluntario_aprobado'", $fuente, 'Un voluntario puede ser delegado' );
		$this->assertStringContainsString( "'monitor_actividad'", $fuente, 'Y un monitor también' );
		$this->assertStringNotContainsString(
			"get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) )",
			$fuente,
			'La lista vieja dejaba fuera a quien hace el check-in'
		);
	}

	/** Y guarda las asignaciones que lee el check-in. */
	public function test_guarda_las_asignaciones_que_lee_el_checkin(): void {
		$page = $this->leer( 'admin/class-admin-page.php' );
		$cpt  = $this->leer( 'includes/CPT_Actividad.php' );

		$this->assertStringContainsString( "update_option( 'convoca_enroll_delegados_actividades'", $page );
		// La opción la lee `CPT_Actividad::get_allowed_activities_ids()`, que es quien decide si un monitor
		// puede ver su actividad. Se comprueba en los dos sentidos: si los nombres se separan, el monitor
		// vuelve a comerse un 403 y la pantalla se queda escribiendo en el vacío.
		$this->assertStringContainsString( "get_option( 'convoca_enroll_delegados_actividades'", $cpt );
		$this->assertMatchesRegularExpression(
			"/function get_allowed_activities_ids\(\).*?convoca_enroll_delegados_actividades/s",
			$cpt,
			'Es esta función la que reparte el permiso del check-in'
		);
	}
}
