<?php
/**
 * Editor de actividades y su listado.
 *
 * El editor es la pantalla en la que se prepara cada taller, y el listado la que se mira para ver qué hay, cuánto se ha
 * llenado y quién está en espera. Se prueban el formulario (nueva actividad con sus valores por omisión, y edición de
 * una existente) y las columnas de la lista, que son las que llevan la cuenta.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Actividades;
use Convoca\Enroll\Admin_Actividades_List;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-actividades.php';

/**
 * Editor y listado.
 */
final class AdminActividadesEditorTest extends TestCase {

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
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'results'      => array(),
			'results_mapa' => array(),
		);
		$GLOBALS['convoca_test_caps']           = array( 'manage_options' => true );
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['_wp_paginacion']              = array();
		$_GET                                   = array();
		$_GET['page']                           = 'convoca-enroll-actividades';

		$post                                 = new \WP_Post();
		$post->ID                             = 61;
		$post->post_type                      = 'actividad';
		$post->post_title                     = 'Ruta de otoño';
		$post->post_status                    = 'publish';
		$GLOBALS['_wp_stores']['posts'][61]   = $post;
		$GLOBALS['convoca_test_tipos'][61]    = 'actividad';
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'   => '2026-11-14 18:00:00',
			'_convoca_plazas_totales' => '30',
			'_convoca_plazas_disponibles' => '24',
			'_convoca_ubicacion'      => 'Centro Social',
			'_convoca_precio_socio'   => '60,50',
		);
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_tipos']          = array();
		$GLOBALS['_wp_paginacion']              = array();
		$_GET                                   = array();
	}

	/**
	 * Pinta el editor.
	 *
	 * @return string
	 */
	private function editor(): string {
		ob_start();
		try {
			( new Admin_Actividades() )->render_editor();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Una actividad nueva llega con valores por omisión: mañana, 20 plazas y gratuito.
	 */
	public function test_actividad_nueva(): void {
		$html = $this->editor();

		$this->assertStringContainsString( 'Nueva Actividad', $html );
		$this->assertStringContainsString( 'name="post_title"', $html );
		$this->assertStringContainsString( 'convoca_save_actividad_admin', $html, 'el formulario apunta a su acción' );
		$this->assertStringContainsString( 'value="20"', $html, '20 plazas por omisión' );
		$this->assertStringContainsString( (string) wp_date( 'Y-m-d', strtotime( '+1 day' ) ), $html, 'fecha de mañana' );
	}

	/**
	 * Una existente se edita con sus datos.
	 */
	public function test_actividad_existente(): void {
		$_GET['id'] = '61';

		$html = $this->editor();

		$this->assertStringContainsString( 'Editar Actividad', $html );
		$this->assertStringContainsString( 'Ruta de otoño', $html );
		$this->assertStringContainsString( 'Centro Social', $html );
		$this->assertStringContainsString( '60,50', $html );
	}

	/**
	 * El listado pide 20 por página, ordenado por fecha y con el filtro del monitor si lo hay.
	 */
	public function test_el_listado(): void {
		$GLOBALS['convoca_test_posts'] = array( $GLOBALS['_wp_stores']['posts'][61] );

		$lista = new Admin_Actividades_List();
		$lista->prepare_items();

		$args = end( $GLOBALS['convoca_test_get_posts_args'] );
		$this->assertSame( 20, $args['posts_per_page'] );
		$this->assertSame( 'meta_value', $args['orderby'], 'ordenado por la fecha de la actividad' );
		$this->assertSame( 'DESC', $args['order'] );
		$this->assertSame( 1, $GLOBALS['_wp_paginacion']['total_items'], 'la paginación cuenta lo que hay' );
		$this->assertCount( 1, $lista->items );
	}

	/**
	 * Con búsqueda se ordena por fecha de publicación (la meta rompería el JOIN del buscador).
	 */
	public function test_el_listado_con_busqueda(): void {
		$_GET['s'] = 'ruta';

		( new Admin_Actividades_List() )->prepare_items();

		$args = end( $GLOBALS['convoca_test_get_posts_args'] );
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'ruta', $args['s'] );
		$this->assertArrayNotHasKey( 'meta_key', $args );
	}

	/**
	 * Las columnas: ocupación con su porcentaje, espera y cancelaciones.
	 */
	public function test_las_columnas(): void {
		$item = $GLOBALS['_wp_stores']['posts'][61];

		$lista = new Admin_Actividades_List();
		// Las columnas son `protected`: se llaman por reflexión.
		$columna = static function ( string $metodo, $item ) use ( $lista ): string {
			$reflejo = new \ReflectionMethod( Admin_Actividades_List::class, $metodo );
			ob_start();
			try {
				$valor = $reflejo->invoke( $lista, $item );
			} finally {
				$impreso = (string) ob_get_clean();
			}

			// Estas columnas **devuelven** la celda; otras la imprimen.
			return is_string( $valor ) && '' !== $valor ? $valor : $impreso;
		};

		$this->assertStringContainsString( '30', $columna( 'column_plazas', $item ) );
		$this->assertStringContainsString( 'Centro Social', $columna( 'column_ubicacion', $item ) );

		// Sin datos de espera, un guion.
		$fila         = new \stdClass();
		$fila->estado = 'lista_espera';
		$fila->total  = '2';
		$GLOBALS['_wp_stores']['results_mapa']['COUNT(*) AS total'] = array( $fila );

		$this->assertSame( '2', trim( $columna( 'column_espera', $item ) ), 'los que están en espera' );
	}
}
