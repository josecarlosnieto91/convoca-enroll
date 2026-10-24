<?php
/**
 * Datos estructurados de una actividad (schema.org).
 *
 * Es lo que lee Google para enseñar la actividad en los resultados de búsqueda: cuándo es, dónde y a qué precio. Si el
 * JSON sale mal —una fecha inválida, un precio con texto, «disponible» cuando no quedan plazas— la actividad pierde
 * su ficha en Google, o peor, promete plazas que no hay.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Schema del evento.
 */
final class ActividadSchemaTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * CPT.
	 *
	 * @var CPT_Actividad
	 */
	private CPT_Actividad $cpt;

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
		// El arnés de este repo fija la consulta en `convoca_test_query`.
		$GLOBALS['convoca_test_query']['es_singular'] = true;
		$GLOBALS['convoca_test_query']['queried_id']  = 61;
		$GLOBALS['_wp_stores']['bloginfo']['name'] = 'Convoca';

		$actividad                        = new \WP_Post();
		$actividad->ID                    = 61;
		$actividad->post_type             = 'actividad';
		$actividad->post_title            = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;

		$this->cpt = new CPT_Actividad();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                        = $this->previo;
		$GLOBALS['convoca_test_query']['es_singular'] = false;
		$GLOBALS['convoca_test_query']['queried_id']  = 0;
	}

	/**
	 * Pinta el schema y devuelve el JSON decodificado (o `null` si no pintó nada).
	 *
	 * @return array|null
	 */
	private function schema(): ?array {
		ob_start();
		try {
			$this->cpt->output_event_schema();
		} finally {
			$html = (string) ob_get_clean();
		}

		if ( ! preg_match( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m ) ) {
			return null;
		}

		return json_decode( trim( $m[1] ), true );
	}

	/**
	 * En una página que no es una actividad no se pinta nada.
	 */
	public function test_solo_en_la_ficha_de_una_actividad(): void {
		// Con datos completos: lo **único** que impide pintarlo es no estar en la ficha de la actividad.
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => '2026-11-14 18:00:00',
			'_convoca_plazas_disponibles' => '8',
		);
		$GLOBALS['convoca_test_query']['es_singular'] = false;

		$this->assertNull( $this->schema(), 'en un listado o en una página no se pinta' );
	}

	/**
	 * Sin fecha de inicio no hay ficha: mejor ninguna que una falsa.
	 */
	public function test_sin_fecha_no_hay_schema(): void {
		$this->assertNull( $this->schema() );
	}

	/**
	 * El JSON: tipo, fechas ISO, lugar y oferta con el precio más bajo.
	 */
	public function test_el_schema_completo(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'         => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'            => '2026-11-14 20:30:00',
			'_convoca_ubicacion'            => 'Centro Social',
			'_convoca_plazas_disponibles'   => '8',
			'_convoca_precio_socio'         => '60,50',
			'_convoca_precio_socio_dia'     => '15',
			'_convoca_precio_general'       => 'gratis',
		);

		$schema = $this->schema();

		$this->assertSame( 'EducationEvent', $schema['@type'] );
		$this->assertSame( 'Ruta de otoño', $schema['name'] );
		$this->assertStringContainsString( '2026-11-14', $schema['startDate'] );
		$this->assertStringContainsString( '2026-11-14', $schema['endDate'] );
		$this->assertSame( 'Centro Social', $schema['location']['name'] );
		$this->assertSame( 'InStock', substr( $schema['offers']['availability'], -7 ) );
		$this->assertSame( '15', $schema['offers']['price'], 'el más bajo de los que se cobran' );
		$this->assertSame( 'EUR', $schema['offers']['priceCurrency'] );
	}

	/**
	 * Sin fecha de fin se asume la duración por omisión (dos horas), no una fecha vacía.
	 */
	public function test_sin_fecha_fin_usa_dos_horas(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => '2026-11-14 18:00:00',
			'_convoca_plazas_disponibles' => '5',
		);

		$schema = $this->schema();

		$this->assertSame( strtotime( $schema['endDate'] ) - strtotime( $schema['startDate'] ), 7200 );
	}

	/**
	 * Sin plazas, «agotado»: no se puede anunciar como disponible.
	 */
	public function test_sin_plazas_esta_agotado(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => '2026-11-14 18:00:00',
			'_convoca_plazas_disponibles' => '0',
		);

		$this->assertSame( 'https://schema.org/SoldOut', $this->schema()['offers']['availability'] );
	}

	/**
	 * Sin ubicación se usa el nombre del sitio: un `Place` sin nombre no vale.
	 */
	public function test_sin_ubicacion_usa_el_nombre_del_sitio(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => '2026-11-14 18:00:00',
			'_convoca_plazas_disponibles' => '5',
		);

		$this->assertSame( 'Convoca', $this->schema()['location']['name'] );
	}

	/**
	 * Si todo es «gratis», el precio es 0 (no «gratis» dentro de un número).
	 */
	public function test_todo_gratis_precio_cero(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => '2026-11-14 18:00:00',
			'_convoca_plazas_disponibles' => '5',
			'_convoca_precio_socio'       => 'Gratis',
		);

		$this->assertSame( '0', $this->schema()['offers']['price'] );
	}

	/**
	 * Una fecha ilegible se trata como si no hubiera: no se inventa un evento.
	 */
	public function test_fecha_ilegible_no_pinta(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_fecha_inicio' => 'no es una fecha' );

		$this->assertNull( $this->schema() );
	}
}
