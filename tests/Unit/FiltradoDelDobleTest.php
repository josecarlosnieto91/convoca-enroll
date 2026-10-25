<?php
declare(strict_types=1);

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * El doble de `get_posts()` resuelve `meta_query`, como WordPress.
 *
 * Durante mucho tiempo devolvía **todo** lo del tipo y solo filtraba con un interruptor opt-in, porque al activarlo
 * rompían 22 pruebas (enroll#44). Esas pruebas sembraban escenarios imposibles —inscripciones sin actividad ni
 * estado, actividades sin fecha— y pasaban gracias al doble, no al producto.
 *
 * Esta prueba fija las dos mitades: que un post que **no casa** no sale, y que uno que sí casa **sí** sale. Sin la
 * segunda, un doble que no devolviera nada también pasaría.
 */
final class FiltradoDelDobleTest extends TestCase {

	/**
	 * Prepara tres inscripciones: una confirmada, una cancelada y una sin estado.
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['convoca_test_posts'] = array();

		foreach ( array( 70, 71, 73 ) as $id ) {
			$p            = new \WP_Post();
			$p->ID        = $id;
			$p->post_type = 'inscripcion';
			$GLOBALS['convoca_test_posts'][] = $p;
		}

		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_actividad_id' => '61',
			'_convoca_estado'       => 'confirmada',
		);
		$GLOBALS['_wp_stores']['post_meta'][71] = array(
			'_convoca_actividad_id' => '61',
			'_convoca_estado'       => 'cancelada',
		);
		// La 73 no tiene estado: `NOT IN` **no** la cuenta, como en WordPress.
		$GLOBALS['_wp_stores']['post_meta'][73] = array(
			'_convoca_actividad_id' => '61',
		);
	}

	protected function tearDown(): void {
		unset( $GLOBALS['convoca_test_posts'], $GLOBALS['_wp_stores']['post_meta'] );
		parent::tearDown();
	}

	/**
	 * Identificadores devueltos por una consulta.
	 *
	 * @param array $args Argumentos de `get_posts()`.
	 * @return array<int, int>
	 */
	private function ids( array $args ): array {
		$args['post_type'] = 'inscripcion';
		$args['fields']    = 'ids';

		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * `NOT IN` deja fuera la cancelada y también la que no tiene la meta.
	 */
	public function test_not_in_filtra(): void {
		$ids = $this->ids(
			array(
				'meta_query' => array(
					'relation' => 'AND',
					array( 'key' => '_convoca_actividad_id', 'value' => 61 ),
					array(
						'key'     => '_convoca_estado',
						'value'   => array( 'cancelada' ),
						'compare' => 'NOT IN',
					),
				),
			)
		);

		$this->assertSame( array( 70 ), $ids, 'sólo la confirmada: la cancelada y la sin estado quedan fuera' );
	}

	/**
	 * `>=` compara como fecha.
	 */
	public function test_comparadores_de_orden(): void {
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_fecha_inicio'] = '2026-11-14 18:00:00';
		$GLOBALS['_wp_stores']['post_meta'][71]['_convoca_fecha_inicio'] = '2020-01-01 10:00:00';

		$ids = $this->ids(
			array(
				'meta_query' => array(
					array(
						'key'     => '_convoca_fecha_inicio',
						'value'   => '2026-01-01 00:00:00',
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
			)
		);

		$this->assertSame( array( 70 ), $ids, 'la de 2020 no pasa el corte' );
	}

	/**
	 * `REGEXP` encuentra el id dentro de una lista separada por comas.
	 */
	public function test_regexp_encuentra_en_la_lista(): void {
		$GLOBALS['_wp_stores']['post_meta'][70]['_convoca_responsables'] = '3,7,9';
		$GLOBALS['_wp_stores']['post_meta'][71]['_convoca_responsables'] = '17,71';

		$ids = $this->ids(
			array(
				'meta_query' => array(
					array(
						'key'     => '_convoca_responsables',
						'value'   => '(^|,)7(,|$)',
						'compare' => 'REGEXP',
					),
				),
			)
		);

		$this->assertSame( array( 70 ), $ids, 'el 7 está en la lista del 70; el 71 no contiene un 7 suelto' );
	}
}
