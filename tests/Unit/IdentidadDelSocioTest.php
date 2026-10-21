<?php
/**
 * Enroll pregunta por la identidad del socio, no por las clases de Members.
 *
 * @package Convoca\Enroll
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * El punto 4 del refactor (identidad de socio): el núcleo pregunta y quien gestiona los socios contesta.
 *
 * Antes, cada sitio de Enroll que necesitaba saber si había un socio con la sesión abierta llamaba a
 * `\Convoca\Members\Member_Auth` con un `class_exists` delante y su propio camino de reserva detrás, y la
 * prioridad de la inscripción vivía **dentro** de un `class_exists( 'Process_Member' )` con treinta líneas
 * de lógica. Dos caminos que mantener sincronizados y que se comportaban distinto según lo instalado.
 *
 * Estas pruebas son de contrato sobre el fuente (el comportamiento de punta a punta necesita WordPress):
 * lo que se fija es que Enroll **no vuelve a nombrar las clases de nadie** y que usa el contrato.
 */
class IdentidadDelSocioTest extends TestCase {

	/**
	 * Raíz del plugin.
	 *
	 * @return string
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Código de un fichero, sin comentarios.
	 *
	 * @param string $relativo Ruta relativa al plugin.
	 * @return string
	 */
	private function codigo( string $relativo ): string {
		$lineas = file( $this->raiz() . '/' . $relativo, FILE_IGNORE_NEW_LINES );
		$this->assertIsArray( $lineas, "no se puede leer {$relativo}" );

		$salida = array();
		foreach ( $lineas as $linea ) {
			$t = ltrim( $linea );
			if ( '' === $t || 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) {
				continue;
			}
			$salida[] = $linea;
		}

		return implode( "\n", $salida );
	}

	public function test_ningun_fichero_de_enroll_nombra_las_clases_de_members(): void {
		foreach ( array( 'includes/Motor_Inscripcion.php', 'includes/Rest_API.php', 'public/class-form-inscripcion.php' ) as $fichero ) {
			$codigo = $this->codigo( $fichero );
			$this->assertStringNotContainsString( 'Member_Auth', $codigo, "{$fichero} vuelve a llamar a la clase de Members" );
			$this->assertStringNotContainsString( 'Process_Member', $codigo, "{$fichero} vuelve a decidir con la clase de Members" );
		}
	}

	public function test_la_identidad_se_pregunta_al_contrato(): void {
		$motor = $this->codigo( 'includes/Motor_Inscripcion.php' );
		$this->assertStringContainsString( 'Member_Identity::is_authenticated()', $motor );
		$this->assertStringContainsString( 'Member_Identity::current_member_data()', $motor );

		$formulario = $this->codigo( 'public/class-form-inscripcion.php' );
		$this->assertStringContainsString( 'Member_Identity::is_authenticated()', $formulario );
		$this->assertStringContainsString( 'Member_Identity::current_member_id()', $formulario );

		$rest = $this->codigo( 'includes/Rest_API.php' );
		$this->assertStringContainsString( 'Member_Identity::current_member_id()', $rest );
	}

	public function test_la_prioridad_de_socio_se_pregunta_al_contrato(): void {
		$motor = $this->codigo( 'includes/Motor_Inscripcion.php' );

		$this->assertStringContainsString( 'Member_Identity::prioridad_de_socio(', $motor );
		$this->assertStringNotContainsString( '_convoca_estado_cuota', $motor, 'la cuota es de Members: Enroll no la lee' );
		$this->assertStringNotContainsString( "'miembro'", $motor, 'buscar la ficha es de Members' );
	}
}
