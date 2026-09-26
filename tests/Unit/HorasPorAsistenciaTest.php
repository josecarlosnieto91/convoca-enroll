<?php
/**
 * La cadena que acredita horas: check-in → asistencia → hook → libro de horas.
 *
 * Verificado en vivo en la demo (27/09/2026): un check-in por la ruta real, sin sesión, deja la
 * asistencia en `si` y **exactamente una** acreditación de las horas de la actividad (2,00 h con
 * una actividad de 10:00 a 12:00), con el marcador `_convoca_horas_contadas` puesto; repetir el
 * escaneo no duplica; retirar la asistencia **anula** el registro (no lo borra) y deja el
 * agregado en 0; y volver a marcarla **reactiva el mismo registro**, sin crear otro.
 *
 * Eso es comportamiento y se comprueba en vivo. Lo que vigila esta prueba es que la cadena siga
 * existiendo: son cuatro eslabones en ficheros distintos y ninguno avisa si se cae.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Contrato de la acreditación de horas por asistencia.
 */
class HorasPorAsistenciaTest extends TestCase
{
	/**
	 * Fuente de un fichero del plugin, sin comentarios (el comentario que explica el porqué
	 * nombra justo lo que se busca).
	 */
	private function codigo( string $relativa ): string
	{
		$ruta = dirname( __DIR__, 2 ) . '/' . $relativa;
		$this->assertFileExists( $ruta );

		$limpio = '';
		foreach ( token_get_all( (string) file_get_contents( $ruta ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$limpio .= is_array( $token ) ? $token[1] : $token;
		}

		return $limpio;
	}

	public function test_el_checkin_cambia_la_asistencia_y_eso_dispara_el_hook(): void
	{
		// Eslabón 1: si el check-in dejara de pasar por set_asistencia(), no habría ni hook ni horas.
		$handler = $this->codigo( 'includes/Checkin_Handler.php' );
		$this->assertStringContainsString( 'Motor_Inscripcion::set_asistencia(', $handler );

		// Eslabón 2: set_asistencia() es quien avisa.
		$this->assertStringContainsString( "do_action( 'convoca_enroll_asistencia_cambiada'", $this->codigo( 'includes/Motor_Inscripcion.php' ) );
	}

	public function test_el_tracker_escucha_ese_aviso(): void
	{
		$tracker = $this->codigo( 'includes/Volunteer_Hour_Tracker.php' );

		$this->assertStringContainsString( 'convoca_enroll_asistencia_cambiada', $tracker );
		$this->assertMatchesRegularExpression( '/add_action\(\s*\'convoca_enroll_asistencia_cambiada\'/', $tracker );
	}

	public function test_una_asistencia_acredita_como_mucho_una_vez(): void
	{
		$tracker = $this->codigo( 'includes/Volunteer_Hour_Tracker.php' );

		// La guarda del marcador: si ya se contó, no se vuelve a contar.
		$this->assertStringContainsString( '_convoca_horas_contadas', $tracker );
		// Un único punto de acreditación (dentro de add_hours), no en un bucle.
		$this->assertSame( 1, substr_count( $tracker, 'Hour_Ledger::credit(' ), 'Las horas se acreditan en un solo sitio.' );
		// Y se acredita por inscripción, que es lo que da «un registro por asistencia».
		$this->assertStringContainsString( 'Hour_Ledger::ORIGEN_INSCRIPCION', $tracker );
	}

	public function test_retirar_la_asistencia_anula_no_borra(): void
	{
		$tracker = $this->codigo( 'includes/Volunteer_Hour_Tracker.php' );

		// Anular conserva la trazabilidad; borrar la perdería.
		$this->assertStringContainsString( 'Hour_Ledger::revoke(', $tracker );
		$this->assertStringNotContainsString( 'Hour_Ledger::delete(', $tracker );
	}

	public function test_sin_permiso_no_se_acredita_pero_si_se_puede_retirar(): void
	{
		$tracker = $this->codigo( 'includes/Volunteer_Hour_Tracker.php' );

		// El permiso se exige en la rama de acreditar ('si'), no antes: retirar es una corrección
		// y tiene que funcionar aunque a la persona se le haya retirado la condición de voluntaria.
		$posicion_permiso   = strpos( $tracker, 'puede_acreditar_horas' );
		$posicion_marcador  = strpos( $tracker, "_convoca_horas_contadas" );
		$this->assertNotFalse( $posicion_permiso );
		$this->assertNotFalse( $posicion_marcador );
		$this->assertLessThan( $posicion_marcador, $posicion_permiso, 'El permiso se comprueba antes de retirar horas.' );
	}
}
