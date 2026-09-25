<?php
/**
 * Regresión del contrato de claves entre Enroll y Members en `registro_hora`.
 *
 * Bug real (E2E 2026-09-25): el tracker escribía `' _convoca_miembro_id'` (con
 * espacio inicial y en español) mientras Members lee `_convoca_member_id`.
 * Las horas de Enroll no contaban para la renovación ni para los certificados.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class VolunteerHourKeyTest extends TestCase {

	private function fuente( string $rel ): string {
		$path = dirname( __DIR__, 2 ) . '/' . $rel;
		$this->assertFileExists( $path, "Falta {$rel}" );
		return (string) file_get_contents( $path );
	}

	/** El tracker NO debe escribir la clave con espacio inicial ni la española. */
	public function test_el_tracker_no_escribe_la_clave_corrupta(): void {
		$src = $this->fuente( 'includes/Volunteer_Hour_Tracker.php' );
		$this->assertStringNotContainsString(
			"update_post_meta( \$log_id, ' _convoca_miembro_id'",
			$src,
			'Volvió la clave con espacio inicial: las horas no llegarán a Members.'
		);
		$this->assertStringNotContainsString(
			"update_post_meta( \$log_id, '_convoca_miembro_id'",
			$src,
			'Volvió la clave en español: Members lee la inglesa.'
		);
	}

	/**
	 * El tracker delega el ciclo de la acreditación en el libro de horas común: escribir las
	 * metas por su cuenta fue justo lo que hizo que la retirada no pudiera deshacer nada.
	 */
	public function test_el_tracker_delega_en_el_libro_de_horas(): void {
		$src = $this->fuente( 'includes/Volunteer_Hour_Tracker.php' );
		$this->assertStringContainsString(
			'Hour_Ledger::credit',
			$src,
			'La acreditación de una asistencia debe registrarse por el libro común.'
		);
		$this->assertStringContainsString(
			'Hour_Ledger::revoke',
			$src,
			'Retirar la asistencia debe invalidar su acreditación en el libro común.'
		);
	}

	/** El libro de horas es quien escribe la clave que Members consulta. */
	public function test_el_libro_de_horas_escribe_la_clave_que_members_lee(): void {
		$core = dirname( __DIR__, 3 ) . '/convoca-core/includes/Hour_Ledger.php';
		if ( ! file_exists( $core ) ) {
			$this->markTestSkipped( 'convoca-core no está junto a este repo.' );
		}
		$this->assertStringContainsString(
			"'_convoca_member_id'",
			(string) file_get_contents( $core ),
			'El vínculo con el socio debe usar la clave que Members lee.'
		);
	}

	/** Members consulta esa misma clave (el otro extremo del contrato). */
	public function test_members_consulta_la_misma_clave(): void {
		$members = dirname( __DIR__, 3 ) . '/convoca-members/includes/Voluntariado_Manager.php';
		if ( ! file_exists( $members ) ) {
			$this->markTestSkipped( 'convoca-members no está junto a este repo.' );
		}
		$this->assertStringContainsString(
			"'_convoca_member_id'",
			(string) file_get_contents( $members ),
			'Members debe seguir leyendo _convoca_member_id.'
		);
	}

	/** La migración de las filas antiguas está registrada. */
	public function test_la_migracion_esta_registrada(): void {
		$src = $this->fuente( 'includes/Enroll_Upgrade_Manager.php' );
		$this->assertStringContainsString( "'1.4.0'", $src, 'Falta el callback de migración 1.4.0.' );
		$this->assertStringContainsString( 'upgrade_to_1_4_0', $src, 'Falta el método de migración.' );
		$this->assertStringContainsString(
			"' _convoca_miembro_id'",
			$src,
			'La migración debe contemplar la clave con espacio inicial.'
		);
	}
}
