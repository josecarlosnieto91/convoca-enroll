<?php
/**
 * El formulario público de evaluación de actividades.
 *
 * `public/class-formulario-evaluacion.php` estaba al 0 % (202 statements) y es la pantalla donde una persona
 * valora una actividad pasada. Lo que se fija: que solo evalúe quien debe (asistió, es monitor o gestiona),
 * que no se pueda evaluar dos veces la misma actividad, que una actividad sin terminar no se pueda evaluar,
 * que las cuatro valoraciones sean 1-5 de verdad, y que el guardado escriba la meta con su valor.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Formulario_Evaluacion;
use PHPUnit\Framework\TestCase;

class FormularioEvaluacionTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/public/class-formulario-evaluacion.php';

		$GLOBALS['_wp_stores']        = array( 'options' => array(), 'post_meta' => array(), 'transients' => array(), 'posts' => array() );
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['_test_users']        = array();
		$GLOBALS['_test_caps']         = array();
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['_wp_json']           = array();
		$GLOBALS['_convoca_acciones']  = array();
		$GLOBALS['convoca_test_meta_estricto'] = true;
		$_POST                        = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = array( 'options' => array() );
		$GLOBALS['convoca_test_posts'] = array();
		$_POST                         = array();
		unset( $GLOBALS['_test_users'], $GLOBALS['_test_caps'], $GLOBALS['convoca_test_caps'], $GLOBALS['_wp_json'], $GLOBALS['convoca_test_meta_estricto'] );
	}

	/**
	 * Siembra una actividad terminada.
	 *
	 * @param int    $id       Actividad.
	 * @param string $fechaFin Fecha de fin.
	 * @param string $estado   Estado del post.
	 * @param array  $meta     Meta extra.
	 */
	private function actividad( int $id, string $fechaFin = '', string $estado = 'publish', array $meta = array() ): void {
		$post              = new \stdClass();
		$post->ID          = $id;
		$post->post_type   = 'actividad';
		$post->post_status = $estado;
		$post->post_title  = 'Ruta del Cares';

		// `get_post()` lee `_wp_stores['posts']` y `get_posts()` lee `convoca_test_posts`:
		// un mismo caso necesita los DOS almacenes (enroll#44).
		$GLOBALS['convoca_test_posts'][ $id ]  = $post;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_fecha_fin'] = '' !== $fechaFin ? $fechaFin : gmdate( 'Y-m-d H:i:s', time() - 86400 );
		foreach ( $meta as $clave => $valor ) {
			$GLOBALS['_wp_stores']['post_meta'][ $id ][ $clave ] = $valor;
		}
	}

	/**
	 * Siembra una persona con sesión.
	 *
	 * @param int      $id    Usuario.
	 * @param string[] $roles Roles.
	 * @param bool     $cap   Capacidad de gestionar sus turnos.
	 */
	private function persona( int $id, array $roles = array( 'socio' ), bool $cap = false ): void {
		$user               = new \WP_User();
		$user->ID           = $id;
		$user->roles        = $roles;
		$user->display_name = 'Persona ' . $id;
		$user->user_email   = 'persona' . $id . '@example.test';
		$user->caps         = $cap ? array( 'gestionar_mis_turnos' => true ) : array();

		$GLOBALS['_test_users'][ $id ]         = $user;
		$GLOBALS['_wp_stores']['current_user'] = $user;
		$GLOBALS['convoca_test_roles']         = $roles;
	}

	/**
	 * Pinta el shortcode con unos atributos.
	 *
	 * @param int $actividad_id Actividad.
	 * @return string
	 */
	private function pintar( int $actividad_id ): string {
		return (string) Formulario_Evaluacion::render_shortcode( array( 'actividad_id' => $actividad_id ) );
	}

	/**
	 * Los cinco enganches del formulario existen.
	 */
	public function test_los_enganches(): void {
		Formulario_Evaluacion::init();

		foreach ( array( 'wp_ajax_convoca_submit_evaluacion', 'wp_ajax_nopriv_convoca_submit_evaluacion', 'wp_ajax_convoca_eval_get_nonce', 'wp_ajax_nopriv_convoca_eval_get_nonce' ) as $hook ) {
			$this->assertNotEmpty( $GLOBALS['_convoca_acciones'][ $hook ] ?? array(), "falta {$hook}" );
		}
	}

	/**
	 * Una actividad que no existe, no es actividad o no está publicada no se puede evaluar.
	 */
	public function test_actividad_invalida(): void {
		$this->assertStringContainsString( 'no válido', $this->pintar( 0 ) );

		$ajena              = new \stdClass();
		$ajena->ID          = 9;
		$ajena->post_type   = 'entrada';
		$ajena->post_status = 'publish';
		$GLOBALS['convoca_test_posts'][9]  = $ajena;
		$GLOBALS['_wp_stores']['posts'][9] = $ajena;
		$this->assertStringContainsString( 'no válido', $this->pintar( 9 ) );

		$this->actividad( 10, '', 'draft' );
		$this->assertStringContainsString( 'no válido', $this->pintar( 10 ) );
	}

	/**
	 * Una actividad que todavía no ha terminado no se evalúa.
	 */
	public function test_actividad_sin_terminar(): void {
		$this->actividad( 11, gmdate( 'Y-m-d H:i:s', time() + 86400 ) );

		$this->assertStringContainsString( 'no ha finalizado', $this->pintar( 11 ) );
	}

	/**
	 * Sin sesión no se evalúa.
	 */
	public function test_sin_sesion_no_se_evalua(): void {
		$this->actividad( 12 );

		$this->assertStringContainsString( 'iniciar sesión', $this->pintar( 12 ) );
	}

	/**
	 * Quien ya evaluó no vuelve a ver el formulario.
	 */
	public function test_quien_ya_evaluo_no_repite(): void {
		$this->actividad( 13 );
		$this->persona( 7 );

		$previa              = new \stdClass();
		$previa->ID          = 90;
		$previa->post_type   = 'convoca_evaluacion';
		$previa->post_status = 'publish';
		$GLOBALS['convoca_test_posts'][90]  = $previa;
		$GLOBALS['_wp_stores']['posts'][90] = $previa;
		$GLOBALS['_wp_stores']['post_meta'][90]['_convoca_eval_actividad_id'] = '13';
		$GLOBALS['_wp_stores']['post_meta'][90]['_convoca_eval_usuario_id']   = '7';

		$this->assertStringContainsString( 'Ya has enviado', $this->pintar( 13 ) );
	}

	/**
	 * Quien gestiona ve el formulario con sus cuatro valoraciones y su nonce.
	 */
	public function test_quien_gestiona_ve_el_formulario(): void {
		$this->actividad( 14 );
		$this->persona( 8 );
		$GLOBALS['_test_caps']['manage_options'] = true;

		$html = $this->pintar( 14 );

		$this->assertStringContainsString( 'conv-evaluacion-container', $html );
		$this->assertStringContainsString( 'Evaluar Actividad: Ruta del Cares', $html );
		foreach ( array( 'gestion', 'instalaciones', 'participantes', 'comunicacion' ) as $campo ) {
			$this->assertStringContainsString( 'name="' . $campo . '"', $html, "falta la valoración {$campo}" );
		}
		$this->assertStringContainsString( 'name="security"', $html, 'el formulario lleva su campo de nonce' );
	}

	/**
	 * Un socio que no asistió no puede evaluar; con asistencia marcada, sí.
	 */
	public function test_voluntario_sin_asistencia_no_puede_y_con_asistencia_si(): void {
		$this->actividad( 15 );
		$this->persona( 9, array( 'voluntario_aprobado' ) );

		$inscripcion              = new \stdClass();
		$inscripcion->ID          = 70;
		$inscripcion->post_type   = 'inscripcion';
		$inscripcion->post_status = 'publish';
		$GLOBALS['convoca_test_posts'][70]  = $inscripcion;
		$GLOBALS['_wp_stores']['posts'][70] = $inscripcion;
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_actividad_id'] = '15';
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_user_id']      = '9';
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia']   = 'no';

		// `current_user_can()` del arnés dice que sí a todo por defecto: sin apagarlo, la guarda no se mide.
		$GLOBALS['convoca_test_caps']['manage_options']            = false;
		$GLOBALS['convoca_test_caps']['convoca_manage_evaluations'] = false;
		$GLOBALS['_test_caps']['manage_options']                    = false;

		$this->assertStringContainsString( 'No tienes permisos', $this->pintar( 15 ), 'sin asistencia no se puede evaluar' );

		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_asistencia'] = 'si';

		$this->assertStringContainsString( 'conv-evaluacion-container', $this->pintar( 15 ) );
	}

	/**
	 * El monitor de la actividad evalúa aunque no esté inscrito.
	 */
	public function test_el_monitor_responsable_puede_evaluar(): void {
		$this->actividad( 16, '', 'publish', array( '_convoca_responsables' => '11,12' ) );
		$this->persona( 11, array( 'socio' ) );

		$this->assertStringContainsString( 'conv-evaluacion-container', $this->pintar( 16 ) );
	}

	/**
	 * Enviar sin sesión se rechaza sin escribir nada.
	 */
	public function test_enviar_sin_sesion_se_rechaza(): void {
		$_POST['actividad_id'] = '20';

		try {
			Formulario_Evaluacion::handle_submission();
			$this->fail( 'sin sesión no puede guardar' );
		} catch ( \Convoca_Json_Cortado $e ) {
			$this->assertNotEmpty( $GLOBALS['_wp_json'], 'se contesta con error' );
		$this->assertFalse( $GLOBALS['_wp_json'][0]['ok'] );
		}
	}

	/**
	 * Una actividad sin terminar tampoco se puede guardar.
	 */
	public function test_enviar_actividad_sin_terminar_se_rechaza(): void {
		$this->actividad( 21, gmdate( 'Y-m-d H:i:s', time() + 86400 ) );
		$this->persona( 13 );
		$_POST['actividad_id'] = '21';

		try {
			Formulario_Evaluacion::handle_submission();
			$this->fail( 'una actividad futura no se puede evaluar' );
		} catch ( \Convoca_Json_Cortado $e ) {
			$this->assertStringContainsString( 'no ha finalizado', wp_json_encode( $GLOBALS['_wp_json'][0]['data'] ) );
		}
	}

	/**
	 * Valoraciones fuera de rango (0 o 6) se rechazan y no se guarda nada.
	 */
	public function test_valoraciones_fuera_de_rango_se_rechazan(): void {
		$this->actividad( 22 );
		$this->persona( 14 );
		$_POST = array(
			'actividad_id'   => '22',
			'gestion'        => '0',
			'instalaciones'  => '5',
			'participantes'  => '5',
			'comunicacion'   => '5',
		);

		$antes = count( $GLOBALS['convoca_test_posts'] );

		try {
			Formulario_Evaluacion::handle_submission();
			$this->fail( 'una valoración de 0 estrellas no es válida' );
		} catch ( \Convoca_Json_Cortado $e ) {
			$this->assertStringContainsString( 'valoraciones', wp_json_encode( $GLOBALS['_wp_json'][0]['data'] ) );
		}

		$this->assertSame( $antes, count( $GLOBALS['convoca_test_posts'] ), 'no se crea ninguna evaluación' );
		$this->assertArrayNotHasKey(
			'convoca_eval_lock_14_22',
			$GLOBALS['_wp_stores']['transients'],
			'el cerrojo se suelta al fallar (si no, la persona queda bloqueada 30 s sin poder reintentar)'
		);
	}

	/**
	 * Con todo válido se guarda la evaluación con sus valoraciones y el texto saneado.
	 */
	public function test_envio_valido_guarda_la_evaluacion(): void {
		$this->actividad( 23 );
		$this->persona( 15 );
		$_POST = array(
			'actividad_id'        => '23',
			'gestion'             => '4',
			'instalaciones'       => '5',
			'participantes'       => '3',
			'comunicacion'        => '5',
			'aspectos_positivos'  => 'Lo mejor: la gente',
		);

		try {
			Formulario_Evaluacion::handle_submission();
		} catch ( \Convoca_Json_Cortado $e ) {
			// El arnés corta al responder, como el `wp_die` real: la respuesta ya está apuntada.
			unset( $e );
		}

		// La evaluación se busca por su meta (el arnés no mete lo insertado en el almacén de posts).
		$ids = array();
		foreach ( (array) $GLOBALS['_wp_stores']['post_meta'] as $post_id => $metas ) {
			if ( isset( $metas['_convoca_eval_actividad_id'] ) && '23' === (string) $metas['_convoca_eval_actividad_id'] ) {
				$ids[] = (int) $post_id;
			}
		}
		$this->assertCount( 1, $ids, 'se crea una evaluación' );

		$id = $ids[0];
		$this->assertSame( '23', (string) $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_eval_actividad_id'] );
		$this->assertSame( '15', (string) $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_eval_usuario_id'] );
		// WordPress guarda la meta como TEXTO: se compara el valor, no el tipo, y se fija que sea el 4 puesto.
		$this->assertSame( '4', (string) $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_eval_gestion'], 'la valoración se guarda con lo que se envió' );
		$this->assertSame( 'Lo mejor: la gente', $GLOBALS['_wp_stores']['post_meta'][ $id ]['_convoca_eval_aspectos_positivos'] );

		$this->assertTrue( $GLOBALS['_wp_json'][0]['ok'], 'se contesta con éxito' );
	}
}
