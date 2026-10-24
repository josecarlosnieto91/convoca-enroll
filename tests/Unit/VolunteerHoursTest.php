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

require_once dirname( __DIR__, 2 ) . '/tests/dobles/hour-ledger.php';
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
		$GLOBALS['__ledger']           = array( 'logs' => array(), 'llamadas' => array(), 'puede' => array() );
		$GLOBALS['convoca_test_users'] = array(
			'ana@example.test' => (object) array(
				'ID'           => 7,
				'display_name' => 'Ana',
				'user_email'   => 'ana@example.test',
			),
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['__ledger']           = null;
		$GLOBALS['convoca_test_users'] = array();
	}

	/**
	 * Llamadas al libro de horas.
	 *
	 * @return array
	 */
	private function llamadas(): array {
		return (array) ( $GLOBALS['__ledger']['llamadas'] ?? array() );
	}

	/**
	 * La puerta del libro está cerrada por defecto: no se acredita nada.
	 */
	public function test_sin_permiso_de_acreditar(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_email'        => 'ana@example.test',
		);
		// Con horas que acreditar: si la puerta no estuviera, se acreditarían.
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_horas_actividad'] = '6';

		Volunteer_Hour_Tracker::handle_asistencia( 70, 'si' );

		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Con la puerta abierta se acreditan las horas **de la actividad** y se marca la inscripción.
	 */
	public function test_acredita_las_horas(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_email'        => 'ana@example.test',
		);
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_horas_actividad'] = '6';
		$GLOBALS['__ledger']['puede'][7] = true;

		Volunteer_Hour_Tracker::handle_asistencia( 70, 'si' );

		$credit = $this->llamadas()[0];
		$this->assertSame( 'credit', $credit[0] );
		$this->assertSame( 'inscripcion', $credit[1] );
		$this->assertSame( 70, $credit[2] );
		$this->assertSame( 7, $credit[3] );
		$this->assertSame( 6.0, $credit[4], 'las horas que declara la actividad' );

		// El marcador se escribe con SQL directo (`INSERT … ON DUPLICATE KEY`): se afirma la consulta, no el meta.
		$sql = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringContainsString( '_convoca_horas_contadas', $sql, 'la inscripción queda marcada' );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', $sql, 'y marcarla dos veces no duplica' );
	}

	/**
	 * Desmarcar la asistencia invalida el registro (no lo borra) y libera el marcador.
	 */
	public function test_retira_las_horas(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id'    => 61,
			'_convoca_email'           => 'ana@example.test',
			'_convoca_horas_contadas'  => '1',
		);
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_actividad_id'] = 61;
		$GLOBALS['__ledger']['puede'][7] = true;
		$GLOBALS['__ledger']['logs'][900] = array(
			'origen'    => 'inscripcion',
			'origen_id' => 70,
			'user_id'   => 7,
			'horas'     => 6.0,
			'estado'    => 'activo',
		);
		$GLOBALS['_wp_stores']['post_meta'][900]['_convoca_horas'] = '6';

		Volunteer_Hour_Tracker::handle_asistencia( 70, 'no' );

		$revoke = $this->llamadas()[0];
		$this->assertSame( 'revoke', $revoke[0] );
		$this->assertSame( 70, $revoke[2] );
		$this->assertSame( '', $GLOBALS['_wp_stores']['post_meta'][70]['_convoca_horas_contadas'] ?? '' );
	}

	/**
	 * Si nunca se contaron, desmarcar no revoca nada.
	 */
	public function test_desmarcar_sin_horas_contadas(): void {
		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_email'        => 'ana@example.test',
		);

		Volunteer_Hour_Tracker::handle_asistencia( 70, 'no' );

		$tipos = array_column( $this->llamadas(), 0 );
		$this->assertNotContains( 'revoke', $tipos );
	}

	/**
	 * La lista de responsables se limpia: comas, espacios, repetidos y ceros.
	 */
	public function test_los_responsables(): void {
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '7, 9,7 0';

		$this->assertSame( array( 7, 9 ), $this->llamar( 'responsables_de', 61 ) );

		unset( $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] );
		$this->assertSame( array(), $this->llamar( 'responsables_de', 61 ), 'sin responsables, lista vacía' );
	}

	/**
	 * El identificador del hecho «esta persona imparte esta actividad» no colisiona.
	 */
	public function test_el_origen_del_responsable(): void {
		$this->assertSame( 61000007, $this->llamar( 'origen_responsable', 61, 7 ) );
		$this->assertNotSame(
			$this->llamar( 'origen_responsable', 61, 7 ),
			$this->llamar( 'origen_responsable', 62, 7 ),
			'dos actividades no comparten origen'
		);
	}

	/**
	 * Con asistencia marcada, el responsable suma sus horas aunque no esté inscrito.
	 */
	public function test_el_responsable_suma(): void {
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '9';
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_horas_actividad'] = '6';
		$GLOBALS['__ledger']['puede'][9] = true;
		$GLOBALS['convoca_test_posts']   = array( 70 );

		$this->llamar( 'sincronizar_responsables', 61 );

		$credit = $this->llamadas()[0];
		$this->assertSame( 'credit', $credit[0] );
		$this->assertSame( 'responsable', $credit[1] );
		$this->assertSame( 9, $credit[3] );
		$this->assertSame( 6.0, $credit[4] );
	}

	/**
	 * Sin ninguna asistencia marcada, la actividad no se ha dado: sus horas se retiran.
	 */
	public function test_sin_asistencia_se_retiran(): void {
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '9';
		$GLOBALS['convoca_test_posts'] = array();

		$GLOBALS['__ledger']['logs'][901] = array(
			'origen'    => 'responsable',
			'origen_id' => 61000009,
			'user_id'   => 9,
			'horas'     => 6.0,
			'estado'    => 'activo',
		);
		$GLOBALS['_wp_stores']['post_meta'][901]['_convoca_horas'] = '6';

		$this->llamar( 'sincronizar_responsables', 61 );

		$revoke = $this->llamadas()[0];
		$this->assertSame( 'revoke', $revoke[0] );
		$this->assertSame( 'responsable', $revoke[1] );
	}

	/**
	 * Sin la puerta abierta, el responsable no acredita (aunque haya asistencia).
	 */
	public function test_el_responsable_sin_permiso(): void {
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '9';
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_horas_actividad'] = '6';
		$GLOBALS['convoca_test_posts'] = array( 70 );

		$this->llamar( 'sincronizar_responsables', 61 );

		$this->assertSame( array(), $this->llamadas() );
	}

	/**
	 * Quien ya cobró por su inscripción no se cuenta otra vez como responsable.
	 */
	public function test_no_se_cuenta_dos_veces(): void {
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '9';
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_horas_actividad'] = '6';
		$GLOBALS['__ledger']['puede'][9] = true;
		$GLOBALS['convoca_test_posts']   = array( 70 );
		$excepto = (object) array( 'ID' => 9 );

		$this->llamar( 'sincronizar_responsables', 61, $excepto );

		$this->assertSame( array(), $this->llamadas(), 'ya está acreditado por su inscripción' );
	}

	/**
	 * Sin horas en la actividad no se acredita: no se inventan horas.
	 */
	public function test_sin_horas_no_acredita(): void {
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_responsables'] = '9';
		$GLOBALS['__ledger']['puede'][9] = true;
		$GLOBALS['convoca_test_posts']   = array( 70 );
		unset( $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_fecha_inicio'] );

		$this->llamar( 'sincronizar_responsables', 61 );

		$this->assertSame( array(), $this->llamadas() );
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
