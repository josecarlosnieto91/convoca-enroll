<?php
/**
 * Google Sheets: la copia de cada inscripción en la hoja.
 *
 * Lo que hay que medir aquí es **cuándo no se manda nada**: la hoja solo se toca si la integración está encendida, la
 * actividad tiene hoja asignada y hay clave de API. Si alguna falta, no sale ninguna petición (ni datos de personas
 * viajando a una URL mal formada).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Inscripcion;
use Convoca\Enroll\Google_Sheets;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Google_Sheets.php';

/**
 * Hoja de cálculo.
 */
final class GoogleSheetsTest extends TestCase {

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
		$GLOBALS['_wp_http_posts'] = array();
		$GLOBALS['_convoca_acciones'] = array();

		$GLOBALS['_wp_stores']['post_meta'][70] = array(
			'_convoca_nombre'   => 'Ana',
			'_convoca_email'    => 'ana@example.test',
			'_convoca_telefono' => '600000000',
			'_convoca_es_socio' => '1',
			'_convoca_estado'   => 'confirmada',
		);
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_sheets_id' => 'hoja-abc',
		);
	}

	/**
	 * Devuelve el estado a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']     = $this->previo;
		$GLOBALS['_wp_http_posts'] = array();
		$GLOBALS['_convoca_acciones'] = array();
	}

	/**
	 * Sin la integración encendida no se engancha a nada.
	 */
	public function test_apagada_no_engancha(): void {
		new Google_Sheets();

		$this->assertArrayNotHasKey( 'convoca_enroll_inscripcion_nueva', $GLOBALS['_convoca_acciones'] );
	}

	/**
	 * Encendida, se engancha a las cuatro cosas.
	 */
	public function test_encendida_engancha(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'sheets_enabled' => 1 );

		new Google_Sheets();

		foreach ( array( 'convoca_enroll_inscripcion_nueva', 'convoca_enroll_inscripcion_confirmada', 'convoca_enroll_inscripcion_cancelada', 'convoca_enroll_inscripcion_promovida' ) as $hook ) {
			$this->assertArrayHasKey( $hook, $GLOBALS['_convoca_acciones'], 'falta: ' . $hook );
		}
	}

	/**
	 * Actividad sin hoja asignada: no se llama a Google.
	 */
	public function test_sin_hoja_no_manda(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'sheets_enabled' => 1, 'sheets_api_key' => 'clave' );

		( new Google_Sheets() )->on_inscription( 70, 99 );

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'], 'sin hoja no hay petición' );
	}

	/**
	 * Con hoja pero sin clave: tampoco. La clave no se inventa.
	 */
	public function test_sin_clave_no_manda(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'sheets_enabled' => 1 );

		( new Google_Sheets() )->on_inscription( 70, 61 );

		$this->assertSame( array(), $GLOBALS['_wp_http_posts'] );
	}

	/**
	 * Con hoja y clave: una fila, en la hoja correcta, con la clave en la cabecera.
	 */
	public function test_manda_la_fila(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'sheets_enabled' => 1, 'sheets_api_key' => 'clave' );

		( new Google_Sheets() )->on_inscription( 70, 61 );

		$this->assertCount( 1, $GLOBALS['_wp_http_posts'] );
		$peticion = $GLOBALS['_wp_http_posts'][0];
		$this->assertStringContainsString( 'spreadsheets/hoja-abc/values/A:F:append', $peticion['url'] );
		$this->assertSame( 'clave', $peticion['args']['headers']['X-Goog-Api-Key'] );

		$cuerpo = json_decode( $peticion['args']['body'], true );
		$fila   = $cuerpo['values'][0];
		$this->assertSame( 'Ana', $fila[0] );
		$this->assertSame( 'ana@example.test', $fila[1] );
		$this->assertSame( '600000000', $fila[2] );
		$this->assertSame( 'Sí', $fila[3] );
		$this->assertSame( 'Confirmada', $fila[4], 'la etiqueta, no el slug' );
	}

	/**
	 * El identificador de la hoja viaja escapado: no puede romper la URL.
	 */
	public function test_la_hoja_va_escapada(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'sheets_enabled' => 1, 'sheets_api_key' => 'clave' );
		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_sheets_id'] = 'hoja/../rara?x=1';

		( new Google_Sheets() )->on_inscription( 70, 61 );

		$this->assertStringContainsString( 'hoja%2F..%2Frara%3Fx%3D1', $GLOBALS['_wp_http_posts'][0]['url'] );
	}

	/**
	 * Un cambio de estado deja constancia con «CAMBIO →», sin teléfono ni socio.
	 */
	public function test_el_cambio_de_estado(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'sheets_enabled' => 1, 'sheets_api_key' => 'clave' );

		( new Google_Sheets() )->on_state_change( 70, 61 );

		$fila = json_decode( $GLOBALS['_wp_http_posts'][0]['args']['body'], true )['values'][0];
		$this->assertSame( 'Ana', $fila[0] );
		$this->assertSame( '', $fila[2] );
		$this->assertSame( '', $fila[3] );
		$this->assertSame( 'CAMBIO → Confirmada', $fila[4] );
	}
}
