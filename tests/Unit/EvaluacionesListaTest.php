<?php
/**
 * La lista de evaluaciones del panel: columnas, filtro por actividad y media.
 *
 * `admin/class-admin-evaluaciones-list.php` estaba al 0 % (86 statements). Lo que se prueba es que la lista diga
 * la verdad a quien la lee: qué actividad es, quién evaluó (y si esa persona ya no existe), y la puntuación media
 * calculada solo con lo que se puntuó de verdad.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Evaluaciones_List;
use PHPUnit\Framework\TestCase;

class EvaluacionesListaTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']                   = array( 'options' => array(), 'post_meta' => array(), 'edit_links' => array(), 'user_links' => array() );
		$GLOBALS['_convoca_acciones']            = array();
		$GLOBALS['_wp_stores']['acciones']       = array();
		$GLOBALS['_test_users']                  = array();
		$GLOBALS['convoca_test_usuarios_borrados'] = array();
		$GLOBALS['convoca_test_get_posts_args']  = array();
		$GLOBALS['convoca_test_is_admin']        = true;
		$GLOBALS['pagenow']                      = 'edit.php';
		$_GET                                    = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_convoca_acciones'], $GLOBALS['_test_users'], $GLOBALS['convoca_test_usuarios_borrados'], $GLOBALS['convoca_test_get_posts_args'], $GLOBALS['convoca_test_is_admin'], $GLOBALS['pagenow'] );
		$_GET = array();
	}

	/**
	 * Ejecuta una columna y devuelve lo que pinta.
	 *
	 * @param string $columna Columna.
	 * @param int    $post_id Evaluación.
	 * @return string
	 */
	private function columna( string $columna, int $post_id ): string {
		ob_start();
		Admin_Evaluaciones_List::custom_column_content( $columna, $post_id );

		return (string) ob_get_clean();
	}

	/**
	 * El listado engancha sus columnas, su contenido, el filtro y su aplicación.
	 */
	public function test_los_enganches(): void {
		Admin_Evaluaciones_List::init();

		$acciones = array_merge( (array) ( $GLOBALS['_wp_stores']['acciones'] ?? array() ), (array) ( $GLOBALS['_convoca_acciones'] ?? array() ) );
		$filtros  = (array) ( $GLOBALS['_wp_filters'] ?? array() );

		$this->assertNotEmpty( $filtros['manage_conv_evaluacion_posts_columns'] ?? array(), 'faltan las columnas' );
		foreach ( array( 'manage_conv_evaluacion_posts_custom_column', 'restrict_manage_posts', 'pre_get_posts' ) as $hook ) {
			$this->assertNotEmpty( $acciones[ $hook ] ?? array(), "falta el enganche {$hook}" );
		}
	}

	/**
	 * Las columnas son las cinco que se leen y en el orden que se leen.
	 */
	public function test_las_columnas(): void {
		$columnas = Admin_Evaluaciones_List::set_custom_columns( array( 'cb' => 'x', 'date' => 'vieja' ) );

		// `date` ya venía en el array, así que CONSERVA su sitio y las nuevas se añaden detrás: se afirma lo
		// que hace de verdad, no el orden que sería más bonito.
		$this->assertSame( array( 'cb', 'date', 'title', 'actividad', 'evaluador', 'puntuacion' ), array_keys( $columnas ) );
		$this->assertSame( 'Puntuación Media', $columnas['puntuacion'] );
	}

	/**
	 * La actividad se enlaza para poder abrirla; sin actividad, un guion.
	 */
	public function test_la_columna_de_actividad(): void {
		$GLOBALS['_wp_stores']['post_meta'][10]      = array( '_convoca_eval_actividad_id' => 77 );
		$GLOBALS['_wp_stores']['edit_links'][77]     = 'https://example.test/wp-admin/post.php?post=77';
		$GLOBALS['_wp_stores']['posts'][77]          = (object) array( 'ID' => 77, 'post_title' => 'Taller de setas' );

		$html = $this->columna( 'actividad', 10 );
		$this->assertStringContainsString( 'post.php?post=77', $html, 'la actividad se puede abrir' );
		$this->assertStringContainsString( 'Taller de setas', $html );

		$this->assertSame( '-', $this->columna( 'actividad', 11 ), 'una evaluación sin actividad lo dice con un guion' );
	}

	/**
	 * Quien evaluó: con nombre y enlace, avisando si esa persona ya no existe, o guion si no hay dato.
	 */
	public function test_la_columna_de_quien_evaluo(): void {
		$GLOBALS['_wp_stores']['post_meta'][20]  = array( '_convoca_eval_usuario_id' => 5 );
		$GLOBALS['_test_users'][5]               = (object) array( 'ID' => 5, 'display_name' => 'Marta' );
		$GLOBALS['_wp_stores']['user_links'][5]  = 'https://example.test/wp-admin/user-edit.php?user_id=5';

		$html = $this->columna( 'evaluador', 20 );
		$this->assertStringContainsString( 'Marta', $html );
		$this->assertStringContainsString( 'user-edit.php?user_id=5', $html );

		$GLOBALS['_wp_stores']['post_meta'][21] = array( '_convoca_eval_usuario_id' => 9 );
		$GLOBALS['convoca_test_usuarios_borrados'] = array( 9 );
		$html = $this->columna( 'evaluador', 21 );
		$this->assertStringContainsString( 'Usuario eliminado', $html, 'si la persona ya no existe se dice, no se deja en blanco' );
		$this->assertStringContainsString( '9', $html, 'y se deja el identificador para poder rastrearlo' );

		$this->assertSame( '-', $this->columna( 'evaluador', 22 ) );
	}

	/**
	 * La media se calcula SOLO con lo que se puntuó: un cero no cuenta y no hunde la media.
	 */
	public function test_la_puntuacion_media(): void {
		$GLOBALS['_wp_stores']['post_meta'][30] = array(
			'_convoca_eval_gestion'        => 4,
			'_convoca_eval_instalaciones'  => 5,
			'_convoca_eval_participantes'  => 3,
			'_convoca_eval_comunicacion'   => 0,
		);

		$html = $this->columna( 'puntuacion', 30 );

		$this->assertStringContainsString( '(4/5)', $html, 'media de 4, 5 y 3: el cero no puntúa' );
		$this->assertSame( 4, substr_count( $html, '★' ), 'cuatro estrellas llenas con media 4' );
		$this->assertSame( 1, substr_count( $html, '☆' ), 'y una vacía' );
	}

	/**
	 * Sin ninguna puntuación no se inventa una media de cero.
	 */
	public function test_sin_puntuaciones_no_hay_media(): void {
		$GLOBALS['_wp_stores']['post_meta'][31] = array(
			'_convoca_eval_gestion'       => 0,
			'_convoca_eval_instalaciones' => '',
		);

		$this->assertSame( '-', $this->columna( 'puntuacion', 31 ) );
	}

	/**
	 * Una columna desconocida no pinta nada (ni revienta).
	 */
	public function test_una_columna_desconocida_no_pinta(): void {
		$this->assertSame( '', $this->columna( 'lo_que_sea', 30 ) );
	}

	/**
	 * El filtro por actividad solo aparece en la pantalla de evaluaciones.
	 */
	public function test_el_filtro_solo_en_evaluaciones(): void {
		$GLOBALS['typenow'] = 'actividad';
		ob_start();
		Admin_Evaluaciones_List::add_custom_filters();
		$this->assertSame( '', (string) ob_get_clean(), 'en otras pantallas no se cuela el selector' );

		$GLOBALS['typenow'] = 'convoca_evaluacion';
		$GLOBALS['convoca_test_posts'] = array(
			(object) array( 'ID' => 77, 'post_title' => 'Taller de setas' ),
			(object) array( 'ID' => 78, 'post_title' => 'Sendero' ),
		);
		$_GET['filter_actividad'] = '78';

		ob_start();
		Admin_Evaluaciones_List::add_custom_filters();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="filter_actividad"', $html );
		$this->assertStringContainsString( 'Todas las actividades', $html );
		$this->assertStringContainsString( 'Taller de setas', $html );
		$this->assertStringContainsString( "value=\"78\"  selected='selected'", $html, 'la actividad filtrada queda marcada' );

		$args = (array) ( $GLOBALS['convoca_test_get_posts_args'][0] ?? array() );
		$this->assertSame( 'actividad', $args['post_type'] );
		$this->assertSame( -1, $args['posts_per_page'], 'se listan todas, no una página' );
	}

	/**
	 * El filtro se aplica a la consulta solo cuando toca: evaluaciones, en el panel y con actividad elegida.
	 */
	public function test_el_filtro_se_aplica_a_la_consulta(): void {
		$consulta = (object) array( 'query_vars' => array() );

		$_GET['post_type']        = 'convoca_evaluacion';
		$_GET['filter_actividad'] = '78';
		Admin_Evaluaciones_List::filter_evaluations_by_activity( $consulta );
		$this->assertSame( '_convoca_eval_actividad_id', $consulta->query_vars['meta_key'] ?? null );
		$this->assertSame( 78, $consulta->query_vars['meta_value'] ?? null );

		// Sin actividad elegida no se filtra nada.
		$consulta = (object) array( 'query_vars' => array() );
		$_GET['filter_actividad'] = '0';
		Admin_Evaluaciones_List::filter_evaluations_by_activity( $consulta );
		$this->assertArrayNotHasKey( 'meta_key', $consulta->query_vars );

		// Ni fuera del panel.
		$consulta = (object) array( 'query_vars' => array() );
		$_GET['filter_actividad']   = '78';
		$GLOBALS['convoca_test_is_admin'] = false;
		Admin_Evaluaciones_List::filter_evaluations_by_activity( $consulta );
		$this->assertArrayNotHasKey( 'meta_key', $consulta->query_vars, 'la consulta pública no se toca' );

		// Ni en otra pantalla.
		$consulta = (object) array( 'query_vars' => array() );
		$GLOBALS['convoca_test_is_admin'] = true;
		$GLOBALS['pagenow']               = 'upload.php';
		Admin_Evaluaciones_List::filter_evaluations_by_activity( $consulta );
		$this->assertArrayNotHasKey( 'meta_key', $consulta->query_vars );
	}
}
