<?php
/**
 * Horas de voluntariado que acredita una actividad.
 *
 * De aquí salen las horas que se certifican para una subvención o para el currículum de una persona voluntaria. Una
 * actividad de un día que acredita 0 h, o un taller de tres días que acredita 48, es un documento mal emitido: las
 * horas salen de lo que diga la actividad y, si no lo dice, se estiman por **días completos** (nunca restando dos
 * instantes, que en un taller de 09:00 a 20:00 daría fracciones de día).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Volunteer_Hour_Tracker;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Volunteer_Hour_Tracker.php';

/**
 * Horas.
 */
final class VolunteerHoursTest extends TestCase {

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
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, ...$args ) {
		$reflejo = new \ReflectionMethod( Volunteer_Hour_Tracker::class, $metodo );
		return $reflejo->invoke( null, ...$args );
	}

	/**
	 * Días: el de inicio cuenta; un taller de un día es **un** día, no una fracción.
	 */
	public function test_los_dias(): void {
		$this->assertSame(
			1,
			$this->llamar( 'dias_de_actividad', '2026-11-14 09:00:00', '2026-11-14 20:00:00' ),
			'el mismo día es un día (no 0,45)'
		);
		$this->assertSame( 2, $this->llamar( 'dias_de_actividad', '2026-11-14 09:00:00', '2026-11-15 14:00:00' ) );
		$this->assertSame( 3, $this->llamar( 'dias_de_actividad', '2026-11-14 09:00:00', '2026-11-16 10:00:00' ) );

		$this->assertSame( 0, $this->llamar( 'dias_de_actividad', '', '' ), 'sin inicio, ningún día' );
		$this->assertSame( 1, $this->llamar( 'dias_de_actividad', '2026-11-14 09:00:00', '2026-11-13 09:00:00' ), 'fin antes que inicio: un día' );
	}

	/**
	 * Horas: las de la actividad si las dice; si no, días × horas por día.
	 */
	public function test_las_horas(): void {
		// El campo es `type=number`, así que llega con punto. Nota: si llegara con coma («2,5»), `(float)` se queda
		// en 2 —a diferencia de los precios, que sí convierten la coma—; se anota, no se fija como correcto.
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_horas_actividad' => '2.5' );
		$this->assertSame( 2.5, $this->llamar( 'horas_de_actividad', 61 ), 'las horas que declara la actividad' );

		// Sin horas declaradas: dos días × 4 h por omisión.
		$GLOBALS['_wp_stores']['post_meta'][62] = array(
			'_convoca_fecha_inicio' => '2026-11-14 09:00:00',
			'_convoca_fecha_fin'    => '2026-11-15 20:00:00',
		);
		$this->assertSame( 8.0, $this->llamar( 'horas_de_actividad', 62 ) );

		// El ajuste del sitio manda.
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'horas_por_dia' => '6' );
		$this->assertSame( 12.0, $this->llamar( 'horas_de_actividad', 62 ), 'días × horas por día del ajuste' );
	}

	/**
	 * Un `horas_por_dia` absurdo (0 o negativo) no deja la acreditación en cero: se usa 4.
	 */
	public function test_horas_por_dia_invalido(): void {
		$GLOBALS['_wp_stores']['post_meta'][62] = array(
			'_convoca_fecha_inicio' => '2026-11-14 09:00:00',
			'_convoca_fecha_fin'    => '2026-11-14 20:00:00',
		);
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'horas_por_dia' => '0' );

		$this->assertSame( 4.0, $this->llamar( 'horas_de_actividad', 62 ) );
	}

	/**
	 * Sin fecha no hay horas que acreditar.
	 */
	public function test_sin_fecha_no_hay_horas(): void {
		$this->assertSame( 0.0, $this->llamar( 'horas_de_actividad', 63 ) );
	}

	/**
	 * Marcar asistencia de una inscripción sin correo no hace nada (no revienta).
	 */
	public function test_asistencia_sin_email(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array( '_convoca_estado' => 'confirmada' );
		$GLOBALS['_wp_logs']                    = array();

		Volunteer_Hour_Tracker::handle_asistencia( 70, 'si' );

		$this->assertSame( array(), $GLOBALS['_wp_logs'] );
	}

	/**
	 * Con correo pero sin usuario en el sitio, tampoco: no hay a quién acreditar.
	 */
	public function test_asistencia_sin_usuario(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array( '_convoca_email' => 'nadie@example.test' );
		$GLOBALS['_wp_logs']                    = array();

		Volunteer_Hour_Tracker::handle_asistencia( 70, 'si' );

		$this->assertSame( array(), $GLOBALS['_wp_logs'] );
	}
}
