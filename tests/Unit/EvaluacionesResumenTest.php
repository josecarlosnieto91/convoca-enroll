<?php
/**
 * El resumen de evaluaciones dentro de la ficha de la actividad.
 *
 * `admin/class-admin-evaluaciones-meta-box.php` estaba al 0 % (61 statements). Es la caja que, al abrir una
 * actividad, dice qué han puntuado quienes la evaluaron. Se prueba que no se invente medias sin datos y que las
 * cuentas cuadren, y que el enlace lleve al tipo de contenido que existe de verdad (enroll#47).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Evaluaciones_Meta_Box;
use PHPUnit\Framework\TestCase;

class EvaluacionesResumenTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']                   = array( 'options' => array(), 'post_meta' => array() );
		$GLOBALS['_convoca_cajas']               = array();
		$GLOBALS['_convoca_acciones']            = array();
		$GLOBALS['convoca_test_get_posts_cola']  = array();
		$GLOBALS['convoca_test_get_posts_args']  = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_convoca_cajas'], $GLOBALS['_convoca_acciones'], $GLOBALS['convoca_test_get_posts_cola'], $GLOBALS['convoca_test_get_posts_args'] );
	}

	/**
	 * Pinta la caja para una actividad.
	 *
	 * @param int $actividad_id Actividad.
	 * @return string
	 */
	private function pintar( int $actividad_id ): string {
		ob_start();
		Admin_Evaluaciones_Meta_Box::render_meta_box( (object) array( 'ID' => $actividad_id ) );

		return (string) ob_get_clean();
	}

	/**
	 * Crea evaluaciones con sus puntuaciones.
	 *
	 * @param array<int, array<string, int>> $puntuaciones Una por evaluación.
	 */
	private function evaluaciones( array $puntuaciones ): void {
		$posts = array();
		foreach ( $puntuaciones as $indice => $notas ) {
			$id                          = 900 + $indice;
			$posts[]                     = (object) array( 'ID' => $id );
			$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
				'_convoca_eval_gestion'       => $notas['gestion'] ?? 0,
				'_convoca_eval_instalaciones' => $notas['instalaciones'] ?? 0,
				'_convoca_eval_participantes' => $notas['participantes'] ?? 0,
				'_convoca_eval_comunicacion'  => $notas['comunicacion'] ?? 0,
			);
		}

		$GLOBALS['convoca_test_get_posts_cola'] = array( $posts );
	}

	/**
	 * La caja se engancha al editor y se registra en la ficha de la actividad.
	 */
	public function test_los_enganches_y_la_caja(): void {
		Admin_Evaluaciones_Meta_Box::init();
		Admin_Evaluaciones_Meta_Box::add_meta_box();

		$acciones = array_merge( (array) ( $GLOBALS['_wp_stores']['acciones'] ?? array() ), (array) ( $GLOBALS['_convoca_acciones'] ?? array() ) );
		$this->assertNotEmpty( $acciones['add_meta_boxes'] ?? array() );

		$cajas = (array) ( $GLOBALS['_convoca_cajas'] ?? array() );
		$this->assertNotEmpty( $cajas );
		$this->assertSame( 'actividad', $cajas[0]['pantalla'], 'el resumen va en la ficha de la actividad' );
	}

	/**
	 * Sin evaluaciones se dice, en vez de enseñar una tabla de ceros.
	 */
	public function test_sin_evaluaciones(): void {
		$GLOBALS['convoca_test_get_posts_cola'] = array( array() );

		$html = $this->pintar( 77 );

		$this->assertStringContainsString( 'No hay evaluaciones', $html );
		$this->assertStringNotContainsString( 'Media Global', $html, 'sin datos no se inventa una media' );
	}

	/**
	 * La consulta pide las evaluaciones DE ESA actividad, publicadas.
	 */
	public function test_la_consulta_de_evaluaciones(): void {
		$GLOBALS['convoca_test_get_posts_cola'] = array( array() );

		$this->pintar( 77 );

		$args = (array) $GLOBALS['convoca_test_get_posts_args'][0];
		$this->assertSame( 'convoca_evaluacion', $args['post_type'], 'el slug real del CPT (enroll#47)' );
		$this->assertSame( '_convoca_eval_actividad_id', $args['meta_key'] );
		$this->assertSame( 77, $args['meta_value'] );
		$this->assertSame( 'publish', $args['post_status'] );
	}

	/**
	 * Con dos evaluaciones, las medias y la global cuadran.
	 */
	public function test_las_medias(): void {
		$this->evaluaciones(
			array(
				array( 'gestion' => 4, 'instalaciones' => 5, 'participantes' => 3, 'comunicacion' => 4 ),
				array( 'gestion' => 2, 'instalaciones' => 3, 'participantes' => 5, 'comunicacion' => 2 ),
			)
		);

		$html = $this->pintar( 77 );

		$this->assertStringContainsString( 'Total evaluaciones: 2', str_replace( '  ', ' ', $html ) );
		$this->assertStringContainsString( '(3/5)', $html, 'gestión: media de 4 y 2' );
		$this->assertStringContainsString( '(4/5)', $html, 'instalaciones: media de 5 y 3' );
		$this->assertStringContainsString( '(4/5)', $html, 'participantes: media de 3 y 5' );
		// Medias: gestión 3, instalaciones 4, participantes 4, comunicación 3 → global (3+4+4+3)/4 = 3,5.
		$this->assertStringContainsString( '(3.5/5)', $html, 'la global es la media de las cuatro medias' );
	}

	/**
	 * El enlace lleva al tipo de contenido que EXISTE, no a uno inventado (enroll#47).
	 */
	public function test_el_enlace_al_listado(): void {
		$this->evaluaciones( array( array( 'gestion' => 5, 'instalaciones' => 5, 'participantes' => 5, 'comunicacion' => 5 ) ) );

		$html = $this->pintar( 77 );

		$this->assertStringContainsString( 'post_type=convoca_evaluacion', $html, 'el CPT real' );
		$this->assertStringContainsString( 'filter_actividad=77', $html, 'y el filtro por esa actividad' );
		$this->assertStringNotContainsString( 'post_type=conv_evaluacion', $html, 'el slug corto no existe' );
	}

}
