<?php
/**
 * Editor propio de evaluaciones: el formulario y su guardado.
 *
 * El guardado es el que importa: sin nonce no entra, sin permiso no entra, y al editar **no se pisa** quién la creó ni
 * cuándo — ese dato es la prueba de que la evaluación es de quien dice ser.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Evaluaciones_Editor;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__ ) . '/../admin/class-admin-evaluaciones-editor.php';

/**
 * Editor de evaluaciones.
 */
final class EvaluacionEditorTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * POST previo.
	 *
	 * @var array
	 */
	private array $post_previo = array();

	/**
	 * GET previo.
	 *
	 * @var array
	 */
	private array $get_previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$this->post_previo     = $_POST;
		$this->get_previo      = $_GET;
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_caps']      = array( 'edit_posts' => true );
		$GLOBALS['convoca_test_posts']     = array();
		$GLOBALS['convoca_test_tipos']     = array();
		$GLOBALS['nonce_invalido']         = false;
		$GLOBALS['_wp_die_lanza']          = true;
		$GLOBALS['_wp_redirects']          = array();
		$GLOBALS['_wp_posts_creados']      = array();
		$GLOBALS['_wp_insert_args']        = array();
		$_POST                             = array();
		$_GET                              = array();

		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
	}

	/**
	 * Devuelve el estado a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['convoca_test_caps']  = array();
		$GLOBALS['convoca_test_posts'] = array();
		$GLOBALS['convoca_test_tipos'] = array();
		$GLOBALS['nonce_invalido']     = false;
		$GLOBALS['_wp_die_lanza']      = false;
		$GLOBALS['_wp_redirects']      = array();
		$_POST                         = $this->post_previo;
		$_GET                          = $this->get_previo;
	}

	/**
	 * El editor propio declara su tipo y su slug.
	 */
	public function test_declara_el_editor(): void {
		$metodo = new \ReflectionMethod( Admin_Evaluaciones_Editor::class, 'editor_propio' );

		$this->assertSame(
			array( 'tipo' => 'convoca_evaluacion', 'slug' => 'conv-evaluacion-editor' ),
			$metodo->invoke( new Admin_Evaluaciones_Editor() )
		);
	}

	/**
	 * El formulario de alta: nonce, selector de actividad y las cuatro notas.
	 */
	public function test_el_formulario_de_alta(): void {
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] );

		ob_start();
		try {
			( new Admin_Evaluaciones_Editor() )->render_editor();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'Nueva Evaluación', $html );
		$this->assertStringContainsString( 'convoca_save_evaluacion_admin', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
		$this->assertStringContainsString( 'Ruta de otoño', $html, 'la actividad se puede elegir' );
		foreach ( array( 'gestion', 'instalaciones', 'participantes', 'comunicacion' ) as $campo ) {
			$this->assertStringContainsString( 'name="' . $campo . '"', $html, 'falta la nota: ' . $campo );
		}
	}

	/**
	 * Editando, el formulario trae lo guardado y el título cambia.
	 */
	public function test_el_formulario_de_edicion(): void {
		$eval                        = new \WP_Post();
		$eval->ID                    = 90;
		$eval->post_type             = 'convoca_evaluacion';
		$eval->post_content          = 'Muy bien organizado.';
		$GLOBALS['_wp_stores']['posts'][90] = $eval;
		$GLOBALS['_wp_stores']['post_meta'][90] = array(
			'_convoca_eval_actividad_id' => '61',
			'_convoca_eval_gestion'      => '4',
		);
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] );
		$_GET['id']                    = '90';

		ob_start();
		try {
			( new Admin_Evaluaciones_Editor() )->render_editor();
		} finally {
			$html = (string) ob_get_clean();
		}

		$this->assertStringContainsString( 'Editar Evaluación', $html );
		$this->assertStringContainsString( 'Muy bien organizado.', $html );
		$this->assertStringContainsString( 'value="90"', $html, 'el id viaja para editar' );
		$this->assertMatchesRegularExpression( '/value="61"\s+selected/', $html, 'la actividad guardada sale elegida' );
	}

	/**
	 * Guardar sin permiso no escribe.
	 */
	public function test_guardar_sin_permiso(): void {
		$GLOBALS['convoca_test_caps']['edit_posts'] = false;
		$_POST                                      = $this->formulario();

		$this->expectException( \Convoca_Json_Cortado::class );
		( new Admin_Evaluaciones_Editor() )->handle_save_admin();
	}

	/**
	 * Guardar con nonce inválido no escribe.
	 */
	public function test_guardar_sin_nonce(): void {
		$GLOBALS['nonce_invalido'] = true;
		$_POST                     = $this->formulario();

		$this->expectException( \Convoca_Json_Cortado::class );
		( new Admin_Evaluaciones_Editor() )->handle_save_admin();
	}

	/**
	 * Formulario completo.
	 *
	 * @return array
	 */
	private function formulario(): array {
		return array(
			'_wpnonce'       => 'bueno',
			'id'             => '0',
			'actividad_id'   => '61',
			'post_content'   => '  Un gran día.  ',
			'gestion'        => '5',
			'instalaciones'  => '4',
			'participantes'  => '3',
			'comunicacion'   => '2',
		);
	}

	/**
	 * Alta: se crea publicada, titulada por la actividad, con las cuatro notas, autor y fecha.
	 */
	public function test_el_alta(): void {
		$_POST = $this->formulario();

		try {
			( new Admin_Evaluaciones_Editor() )->handle_save_admin();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			unset( $cortado );
		}

		$alta = $GLOBALS['_wp_insert_args'][0];
		$this->assertSame( 'convoca_evaluacion', $alta['post_type'] );
		$this->assertSame( 'Evaluación: Ruta de otoño', $alta['post_title'] );
		$this->assertSame( 'publish', $alta['post_status'] );
		$this->assertSame( 'Un gran día.', $alta['post_content'], 'sin los espacios de fuera' );

		$creado = $GLOBALS['_wp_posts_creados'][0] ?? null;
		$this->assertNotNull( $creado );
		$m = $GLOBALS['_wp_stores']['post_meta'][ $creado ];
		$this->assertSame( '61', $m['_convoca_eval_actividad_id'] );
		$this->assertSame( '5', $m['_convoca_eval_gestion'] );
		$this->assertSame( '2', $m['_convoca_eval_comunicacion'] );
		$this->assertSame( '1', $m['_convoca_eval_usuario_id'], 'queda quién la hizo' );
		$this->assertNotEmpty( $m['_convoca_eval_fecha'] );
	}

	/**
	 * Edición: **no se pisan** el autor ni la fecha originales.
	 */
	public function test_la_edicion_respeta_autor_y_fecha(): void {
		$GLOBALS['_wp_stores']['post_meta'][90] = array(
			'_convoca_eval_usuario_id' => '7',
			'_convoca_eval_fecha'      => '2026-01-10 09:00:00',
		);
		$_POST = $this->formulario();
		$_POST['id'] = '90';

		try {
			( new Admin_Evaluaciones_Editor() )->handle_save_admin();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			unset( $cortado );
		}

		$m = $GLOBALS['_wp_stores']['post_meta'][90];
		$this->assertSame( '7', $m['_convoca_eval_usuario_id'] );
		$this->assertSame( '2026-01-10 09:00:00', $m['_convoca_eval_fecha'] );
		$this->assertSame( '5', $m['_convoca_eval_gestion'], 'las notas sí se actualizan' );
	}

	/**
	 * Si el alta no se puede crear, no se sigue escribiendo meta por ahí.
	 */
	public function test_si_falla_el_alta(): void {
		$GLOBALS['_wp_insert_falla'] = true;
		$_POST                       = $this->formulario();

		$this->expectException( \Convoca_Json_Cortado::class );
		( new Admin_Evaluaciones_Editor() )->handle_save_admin();
	}
}
