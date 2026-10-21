<?php
/**
 * Enroll no administra roles ni decide quién acredita horas.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Dos reglas de producto (JC, 21/10/2026), sobre el fuente y a propósito:
 *
 *  1. **El vocabulario de personas es del producto**: los roles y sus capacidades los crea y los mantiene
 *     Convoca Core. Enroll llegó a tener un segundo mapa de capacidades que había que ir sincronizando a
 *     mano con el del núcleo, y creaba `monitor_actividad` por su cuenta.
 *  2. **Quién acredita horas lo dice la puerta del libro** (`Hour_Ledger::puede_acreditar_horas()`), no una
 *     clase de otro plugin ni un rol leído aquí. El fallback anterior acreditaba horas en sitios donde no
 *     hay gestión de personas.
 *
 * El comportamiento de punta a punta exige WordPress de verdad (transacción, `get_userdata`), así que lo
 * que se blinda aquí es lo que se rompió: que Enroll vuelva a tocar roles o a decidir por su cuenta.
 */
class EnrollNoAdministraRolesTest extends TestCase {

	/**
	 * Raíz del plugin.
	 *
	 * @return string
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	public function test_enroll_no_crea_ni_toca_roles(): void {
		$principal = (string) file_get_contents( $this->raiz() . '/convoca-enroll.php' );

		$this->assertStringNotContainsString( 'add_role(', $principal, 'Enroll vuelve a crear roles: eso es de Convoca Core.' );
		$this->assertStringNotContainsString( 'add_cap(', $principal, 'Enroll vuelve a añadir capacidades a roles.' );
		$this->assertStringNotContainsString( 'get_role(', $principal, 'Enroll vuelve a leer roles para decidir permisos.' );
	}

	public function test_las_horas_se_deciden_por_la_puerta_del_libro(): void {
		$fuente = (string) file_get_contents( $this->raiz() . '/includes/Volunteer_Hour_Tracker.php' );

		$this->assertStringContainsString(
			'Hour_Ledger::puede_acreditar_horas(',
			$fuente,
			'El tracker de horas no consulta la puerta del libro: acreditaría donde no hay gestión de personas.'
		);
		$this->assertStringNotContainsString(
			'Admin_Voluntariado',
			$fuente,
			'Enroll vuelve a nombrar una clase de Convoca Members: el núcleo no debe conocer a los addons.'
		);
		$this->assertStringNotContainsString(
			"'voluntario_aprobado'",
			$fuente,
			'Enroll vuelve a decidir por el rol de voluntario en vez de por la puerta.'
		);
	}

	public function test_los_dos_caminos_de_horas_pasan_por_la_puerta(): void {
		// Inscripción y responsables: si uno de los dos se salta la puerta, se acreditan horas por la
		// puerta de atrás. Se cuentan las llamadas, no las menciones en comentarios.
		$fuente = (string) file_get_contents( $this->raiz() . '/includes/Volunteer_Hour_Tracker.php' );
		$lineas = array_filter(
			explode( "\n", $fuente ),
			static function ( $linea ) {
				$t = ltrim( $linea );
				return 0 !== strpos( $t, '*' ) && 0 !== strpos( $t, '//' ) && 0 !== strpos( $t, '/*' );
			}
		);

		$llamadas = 0;
		foreach ( $lineas as $linea ) {
			if ( false !== strpos( $linea, 'puede_acreditar_horas(' ) ) {
				++$llamadas;
			}
		}

		$this->assertSame( 2, $llamadas, 'Se esperan dos puertas: la de la inscripción y la de los responsables.' );
	}
}
