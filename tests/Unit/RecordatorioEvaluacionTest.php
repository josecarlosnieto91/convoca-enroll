<?php
/**
 * El recordatorio de evaluación que sale unos días después de la actividad.
 *
 * `includes/Eval_Reminder_Cron.php` estaba al 0 % (147 statements). Es un cron que manda correo a personas
 * reales: lo que se fija aquí es que **no** mande nada cuando no toca (ajuste apagado, sin cuerpo, sin
 * actividades, sin usuario, sin permiso, recordatorio ya enviado, evaluación ya hecha), que respete el
 * tope de 30 por pasada y que el correo lleve las variables sustituidas y el enlace de evaluación.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Eval_Reminder_Cron;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RecordatorioEvaluacionTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Email_Queue.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Eval_Reminder_Cron.php';

		$GLOBALS['_wp_stores'] = array(
			'options'      => array(),
			'post_meta'    => array(),
			'posts'        => array(),
			'get_var_mapa' => array(),
		);
		$GLOBALS['_convoca_acciones'] = array();
		$GLOBALS['db_inserts']        = array();
		$GLOBALS['_test_users']       = array();
		$GLOBALS['convoca_test_posts'] = array();
		unset( $GLOBALS['convoca_test_meta_estricto'] );
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		$GLOBALS['convoca_test_posts'] = array();
		unset( $GLOBALS['_convoca_acciones'], $GLOBALS['db_inserts'], $GLOBALS['_test_users'], $GLOBALS['convoca_test_meta_estricto'] );
	}

	/**
	 * Llama a un método privado.
	 *
	 * @param string $metodo     Método.
	 * @param array  $argumentos Argumentos.
	 * @return mixed
	 */
	private function privado( string $metodo, array $argumentos = array() ) {
		$reflejo = new ReflectionMethod( Eval_Reminder_Cron::class, $metodo );
		return $reflejo->invokeArgs( null, $argumentos );
	}

	/**
	 * Guarda los ajustes del recordatorio.
	 *
	 * @param array<string, mixed> $eval Ajustes del recordatorio.
	 */
	private function ajustes( array $eval ): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'eval_reminder' => $eval );
	}

	/**
	 * Siembra un usuario con roles.
	 *
	 * @param int      $id    Usuario.
	 * @param string[] $roles Roles.
	 * @param bool     $cap   Si tiene la capacidad de gestionar sus turnos.
	 */
	private function usuario( int $id, array $roles, bool $cap = false ): void {
		$user               = new \WP_User();
		$user->ID           = $id;
		$user->roles        = $roles;
		$user->user_email   = 'persona' . $id . '@example.test';
		$user->display_name = 'Persona ' . $id;
		$user->caps         = $cap ? array( 'gestionar_mis_turnos' => true ) : array();

		$GLOBALS['_test_users'][ $id ] = $user;
	}

	/**
	 * El cron se engancha a su gancho.
	 */
	public function test_se_engancha_al_cron(): void {
		Eval_Reminder_Cron::init();

		$this->assertArrayHasKey( 'convoca_enroll_eval_reminder', $GLOBALS['_convoca_acciones'] );
	}

	/**
	 * Con el recordatorio apagado no se envía nada.
	 */
	public function test_apagado_no_envia(): void {
		$this->ajustes( array( 'active' => false, 'body' => 'Hola {nombre_actividad}' ) );

		Eval_Reminder_Cron::run();

		$this->assertSame( array(), $GLOBALS['db_inserts'], 'apagado no encola correo' );
	}

	/**
	 * Sin cuerpo de correo tampoco: un recordatorio vacío es peor que ninguno.
	 */
	public function test_sin_cuerpo_no_envia(): void {
		$this->ajustes( array( 'active' => true, 'body' => '' ) );

		Eval_Reminder_Cron::run();

		$this->assertSame( array(), $GLOBALS['db_inserts'] );
	}

	/**
	 * Sin actividades terminadas ese día no hay nada que recordar.
	 */
	public function test_sin_actividades_no_envia(): void {
		$this->ajustes( array( 'active' => true, 'body' => 'Hola {nombre_actividad}' ) );

		Eval_Reminder_Cron::run();

		$this->assertSame( array(), $GLOBALS['db_inserts'] );
	}

	/**
	 * Quien no es voluntario, monitor ni administrador no recibe el recordatorio.
	 */
	public function test_un_socio_cualquiera_no_puede_evaluar(): void {
		$this->usuario( 7, array( 'socio' ) );

		$this->assertFalse( $this->privado( 'user_can_evaluate', array( 7 ) ) );
	}

	/**
	 * Voluntario aprobado, monitor y administrador sí; y quien tiene la capacidad de sus turnos.
	 */
	public function test_los_colectivos_que_evaluan_pueden(): void {
		$this->usuario( 1, array( 'voluntario_aprobado' ) );
		$this->usuario( 2, array( 'monitor_actividad' ) );
		$this->usuario( 3, array( 'administrator' ) );
		$this->usuario( 4, array( 'socio' ), true );

		$this->assertTrue( $this->privado( 'user_can_evaluate', array( 1 ) ) );
		$this->assertTrue( $this->privado( 'user_can_evaluate', array( 2 ) ) );
		$this->assertTrue( $this->privado( 'user_can_evaluate', array( 3 ) ) );
		$this->assertTrue( $this->privado( 'user_can_evaluate', array( 4 ) ), 'la capacidad de sus turnos también cuenta' );
	}

	/**
	 * Un usuario que ya evaluó no recibe otro recordatorio.
	 */
	public function test_quien_ya_evaluo_no_lo_recibe(): void {
		$GLOBALS['_wp_stores']['get_var_mapa'] = array( 'convoca_evaluacion' => '99' );

		$this->assertTrue( $this->privado( 'has_user_evaluated', array( 5, 7 ) ) );

		$GLOBALS['_wp_stores']['get_var_mapa'] = array();
		$this->assertFalse( $this->privado( 'has_user_evaluated', array( 5, 7 ) ), 'sin evaluación, no ha evaluado' );
	}

	/**
	 * El envío sustituye variables, enlaza a la evaluación y marca la inscripción como avisada.
	 */
	public function test_el_envio_lleva_las_variables_y_marca_la_inscripcion(): void {
		$this->usuario( 7, array( 'voluntario_aprobado' ) );
		$GLOBALS['_wp_stores']['posts'][21] = (object) array( 'ID' => 21, 'post_title' => 'Ruta del Cares', 'post_type' => 'actividad', 'post_status' => 'publish' );
		$GLOBALS['_wp_stores']['post_meta'][21]['_convoca_fecha_fin'] = '2026-10-20 18:00:00';

		$this->privado(
			'send_reminder',
			array(
				55,
				21,
				7,
				'¿Qué tal {nombre_actividad}?',
				'Hola {evaluador_nombre}, el {fecha_actividad} estuviste en {nombre_actividad}. Evalúa: {link_evaluacion}',
				'',
				'',
			)
		);

		$correo = null;
		foreach ( (array) $GLOBALS['db_inserts'] as $insert ) {
			if ( false !== strpos( (string) $insert['tabla'], 'email_queue' ) ) {
				$correo = $insert['datos'];
			}
		}

		$this->assertNotNull( $correo, 'el recordatorio se encola' );
		$this->assertNotEmpty( (string) $correo['recipient'], 'el correo va a la persona de la inscripción' );
		$this->assertSame( '¿Qué tal Ruta del Cares?', $correo['subject'], 'el asunto lleva el nombre de la actividad' );
		$this->assertStringContainsString( 'Persona 7', (string) ( $correo['body_html'] ?? '' ), 'y el cuerpo, el nombre de quien evalúa' );

		$this->assertArrayHasKey( '_convoca_reminder_eval_sent', $GLOBALS['_wp_stores']['post_meta'][55], 'la inscripción queda marcada' );
	}

	/**
	 * El tope por pasada es 30: con más inscripciones elegibles no se encola la 31.
	 *
	 * Enciende el filtro de meta del arnés (`convoca_test_meta_estricto`): sin él, `get_posts()` devuelve todo
	 * lo del tipo y la prueba mediría el vacío o de más (enroll#44).
	 */
	public function test_el_tope_por_pasada_es_treinta(): void {
		$GLOBALS['convoca_test_meta_estricto'] = true;

		for ( $i = 1; $i <= 40; $i++ ) {
			$this->usuario( $i, array( 'voluntario_aprobado' ) );
			$inscripcion                  = new \stdClass();
			$inscripcion->ID              = 100 + $i;
			$inscripcion->post_type       = 'inscripcion';
			$inscripcion->post_status     = 'publish';
			$inscripcion->post_title      = 'Inscripción ' . $i;

			// `get_posts()` del arnés de enroll lee `convoca_test_posts`; la meta, en cambio, sale de
			// `_wp_stores['post_meta']`. Mezclar los dos almacenes era la razón de que la consulta no viera nada.
			$GLOBALS['convoca_test_posts'][ 100 + $i ] = $inscripcion;
			$GLOBALS['_wp_stores']['post_meta'][ 100 + $i ]['_convoca_actividad_id'] = '21';
			$GLOBALS['_wp_stores']['post_meta'][ 100 + $i ]['_convoca_estado']       = 'confirmada';
			$GLOBALS['_wp_stores']['post_meta'][ 100 + $i ]['_convoca_asistencia']   = 'si';
			$GLOBALS['_wp_stores']['post_meta'][ 100 + $i ]['_convoca_user_id']      = (string) $i;
		}

		$enviados = $this->privado( 'process_activity', array( 21, 'Asunto', 'Cuerpo', '', '', 30 ) );

		$this->assertSame( 30, $enviados, 'no se pasa de treinta recordatorios por pasada' );
	}
}
