<?php
/**
 * Las horas de una actividad salen de la actividad, no de restar las fechas (issue #19).
 *
 * El cálculo viejo era la diferencia entre `fecha_inicio` y `fecha_fin`, en horas. Medido en la demo: una
 * actividad de un solo día sumaba **0 horas** (cuatro de las seis actividades son así) y un taller de tres días
 * sumaba **48**, que son días de calendario y no horas de voluntariado de nadie. Y quien imparte la actividad
 * (monitor o responsable) no sumaba nada si no estaba inscrito, porque nadie leía `_convoca_responsables`.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class HorasActividadTest extends TestCase {

	private function tracker(): string {
		$ruta = dirname( __DIR__, 2 ) . '/includes/Volunteer_Hour_Tracker.php';
		$this->assertFileExists( $ruta );

		return file_get_contents( $ruta );
	}

	/**
	 * Llama a un método privado del tracker (la cuenta de días es lógica pura, sin WordPress).
	 *
	 * @param string $metodo   Método.
	 * @param array  $argumentos Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, array $argumentos ) {
		require_once dirname( __DIR__, 2 ) . '/includes/Volunteer_Hour_Tracker.php';
		$clase = new \ReflectionClass( \Convoca\Enroll\Volunteer_Hour_Tracker::class );
		$m     = $clase->getMethod( $metodo );

		return $m->invokeArgs( null, $argumentos );
	}

	public function test_un_dia_de_actividad_cuenta_como_un_dia(): void {
		// El defecto de origen: mismo día = 0 horas.
		$this->assertSame( 1, $this->privado( 'dias_de_actividad', array( '2026-10-15 09:00', '2026-10-15 20:00' ) ) );
	}

	public function test_un_taller_de_tres_dias_cuenta_tres(): void {
		$this->assertSame( 3, $this->privado( 'dias_de_actividad', array( '2026-09-03 10:00', '2026-09-05 18:00' ) ) );
	}

	public function test_sin_fechas_no_se_inventan_dias(): void {
		$this->assertSame( 0, $this->privado( 'dias_de_actividad', array( '', '' ) ) );
		$this->assertSame( 0, $this->privado( 'dias_de_actividad', array( 'no es una fecha', '' ) ) );
	}

	public function test_una_fecha_fin_anterior_no_rompe_la_cuenta(): void {
		$this->assertSame( 1, $this->privado( 'dias_de_actividad', array( '2026-10-15 09:00', '2026-10-14 09:00' ) ) );
	}

	public function test_el_dato_explicito_de_la_actividad_manda(): void {
		$this->assertStringContainsString(
			"_convoca_horas_actividad",
			$this->tracker(),
			'Sin el dato explícito no se puede fijar cuántas horas son'
		);
		$codigo = $this->tracker();
		$ini    = strpos( $codigo, 'private static function horas_de_actividad' );
		$cuerpo = substr( $codigo, $ini, strpos( $codigo, 'private static function dias_de_actividad' ) - $ini );
		$this->assertStringContainsString( 'return round( $horas, 2 )', $cuerpo, 'El dato explícito se devuelve antes de estimar' );
	}

	public function test_ya_no_se_restan_las_fechas(): void {
		$codigo = $this->tracker();
		$this->assertStringNotContainsString( 'calculate_hours', $codigo, 'El cálculo por calendario tenía que desaparecer' );
		$this->assertStringNotContainsString( '( $end - $start ) / 3600', $codigo );
	}

	public function test_la_asistencia_sincroniza_a_los_responsables(): void {
		$codigo = $this->tracker();
		$this->assertStringContainsString( '_convoca_responsables', $codigo, 'Los responsables viven en esa meta' );
		$this->assertStringContainsString( 'self::sincronizar_responsables( $actividad_id', $codigo );
		$this->assertStringContainsString( 'ORIGEN_RESPONSABLE', $codigo, 'Con su propio origen en el libro de horas' );
		$this->assertStringContainsString( 'asistencias_marcadas', $codigo, 'La señal de que la actividad se ha dado' );
	}

	public function test_la_actividad_tiene_el_campo_de_horas(): void {
		$actividad = file_get_contents( dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php' );
		$this->assertStringContainsString( "'horas_actividad'", $actividad );
		$this->assertStringContainsString( 'name="conv_enroll_horas_actividad"', $actividad );
	}
}
