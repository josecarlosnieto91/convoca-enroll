<?php
/**
 * La exportación de inscripciones a CSV.
 *
 * `admin/class-csv-exporter.php` estaba al 0 % (79 statements). Exporta datos personales, así que lo que se mide
 * es el control de acceso —permiso, responsabilidad de la actividad y nonce— y que el CSV diga la verdad: qué
 * columnas, qué filas, y los booleanos en palabras en vez de en `1`/`0`.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CSV_Exporter;
use PHPUnit\Framework\TestCase;

class ExportadorCsvTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']            = array( 'options' => array(), 'post_meta' => array(), 'posts' => array() );
		$GLOBALS['_csv']                  = array();
		$GLOBALS['_wp_logs']             = array();
		$GLOBALS['convoca_test_caps']     = array( 'manage_inscripciones' => true );
		$GLOBALS['convoca_test_user_can'] = array( 'manage_options' => true );
		$GLOBALS['convoca_test_get_posts_cola'] = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['nonces_invalidos']      = array();
		$_GET                             = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		// Se RESETEA, no se borra: `current_user_can()` del arnés necesita estos globales como array.
		$GLOBALS['_wp_stores']            = array( 'options' => array() );
		$GLOBALS['convoca_test_caps']     = array();
		$GLOBALS['convoca_test_user_can'] = array();
		unset( $GLOBALS['_csv'], $GLOBALS['_wp_logs'], $GLOBALS['convoca_test_get_posts_cola'], $GLOBALS['convoca_test_get_posts_args'], $GLOBALS['nonces_invalidos'] );
		$_GET = array();
	}

	/**
	 * Ejecuta la exportación y devuelve lo que se habría enviado al navegador.
	 *
	 * @return array{0: string, 1: mixed} Tipo ('die' o 'csv') y carga.
	 */
	private function exportar(): array {
		try {
			( new CSV_Exporter() )->export();
		} catch ( \Convoca_Json_Cortado $corte ) {
			$datos = (array) $corte->respuesta;
			if ( isset( $datos['die'] ) ) {
				return array( 'die', $datos['die'] );
			}
			return array( 'csv', $datos['csv'] ?? null );
		}

		$this->fail( 'la exportación tiene que terminar cortando la petición' );
		return array( '', null );
	}

	/**
	 * Sin el permiso de exportar no se genera nada, y se dice por qué.
	 */
	public function test_sin_permiso_no_exporta(): void {
		$GLOBALS['convoca_test_caps']['manage_inscripciones'] = false;

		list( $tipo, $carga ) = $this->exportar();

		$this->assertSame( 'die', $tipo );
		$this->assertStringContainsString( 'No tienes permisos suficientes', (string) $carga );
		$this->assertSame( array(), (array) $GLOBALS['_csv'], 'no se emite ni una fila' );
	}

	/**
	 * Un nonce que no vale corta antes de mirar permisos.
	 */
	public function test_sin_nonce_valido_no_exporta(): void {
		$GLOBALS['nonces_invalidos']['convoca_enroll_export_csv'] = true;

		$this->expectException( \Convoca_Json_Cortado::class );

		( new CSV_Exporter() )->export();
	}

	/**
	 * Un monitor que no es responsable de esa actividad no exporta sus datos.
	 */
	public function test_sin_ser_responsable_no_exporta(): void {
		$GLOBALS['convoca_test_user_can']['manage_options'] = false;
		$_GET['actividad_id']                               = '77';
		$GLOBALS['_wp_stores']['post_meta'][77]             = array( '_convoca_responsables' => '5,6' );

		list( $tipo, $carga ) = $this->exportar();

		$this->assertSame( 'die', $tipo );
		$this->assertStringContainsString( 'No tienes permiso para exportar', (string) $carga );
		$this->assertSame( array(), (array) $GLOBALS['_csv'], 'los datos de esa actividad no salen' );
	}

	/**
	 * Con permiso y responsabilidad, se emite el CSV con sus columnas y sus filas.
	 */
	public function test_el_csv_que_se_emite(): void {
		$_GET['actividad_id'] = '77';
		$GLOBALS['convoca_test_get_posts_cola'] = array(
			array( (object) array( 'ID' => 501 ), (object) array( 'ID' => 502 ) ),
		);
		$GLOBALS['_wp_stores']['posts'][77]      = (object) array( 'ID' => 77, 'post_title' => 'Taller de setas' );
		$GLOBALS['_wp_stores']['post_meta'][501] = array(
			'_convoca_nombre'                  => 'Ana',
			'_convoca_email'                   => 'ana@example.test',
			'_convoca_telefono'                => '600000000',
			'_convoca_es_socio'                => '1',
			'_convoca_estado'                  => 'confirmada',
			'_convoca_pagado'                  => '0',
			'_convoca_actividad_id'            => 77,
			'_convoca_consentimiento_version'  => '1',
			'_convoca_consentimiento_timestamp' => '2026-10-01 10:00:00',
			'_convoca_notas'                   => 'Sin alergias',
		);
		$GLOBALS['_wp_stores']['post_meta'][502] = array(
			'_convoca_nombre'   => 'Luis',
			'_convoca_email'    => 'luis@example.test',
			'_convoca_es_socio' => '0',
			'_convoca_estado'   => 'lista_espera',
			'_convoca_pagado'   => '1',
			'_convoca_actividad_id' => 77,
		);

		list( $tipo, $fichero ) = $this->exportar();

		$this->assertSame( 'csv', $tipo );
		$this->assertStringStartsWith( 'inscripciones-convoca-', (string) $fichero, 'el nombre lleva la fecha' );

		$emision = $GLOBALS['_csv'][0];
		$this->assertSame(
			array( 'Nombre', 'Email', 'Teléfono', 'Socio/a', 'Estado', 'Pagado', 'Actividad', 'Fecha inscripción', 'Consentimiento', 'Notas' ),
			$emision['cabecera'],
			'las columnas son las que espera quien abre el fichero'
		);

		$this->assertCount( 2, $emision['filas'] );

		$primera = $emision['filas'][0];
		$this->assertSame( 'Ana', $primera[0] );
		$this->assertSame( 'ana@example.test', $primera[1] );
		$this->assertSame( 'Sí', $primera[3], 'socia en palabras, no en 1' );
		$this->assertSame( 'Confirmada', $primera[4], 'el estado con su etiqueta, no el slug' );
		$this->assertSame( 'No', $primera[5], 'no pagado en palabras' );
		$this->assertSame( 'Taller de setas', $primera[6] );
		$this->assertStringContainsString( 'v1', $primera[8], 'el consentimiento lleva versión y fecha' );
		$this->assertSame( 'Sin alergias', $primera[9] );

		$segunda = $emision['filas'][1];
		$this->assertSame( 'No', $segunda[3] );
		$this->assertSame( 'Sí', $segunda[5], 'y el que sí pagó' );
	}

	/**
	 * La consulta: solo inscripciones publicadas, todas, y con el filtro de estado y de actividad.
	 */
	public function test_la_consulta_que_se_lanza(): void {
		$_GET['actividad_id'] = '77';
		$_GET['estado']       = 'pendiente';

		$this->exportar();

		$args = (array) $GLOBALS['convoca_test_get_posts_args'][0];
		$this->assertSame( 'inscripcion', $args['post_type'] );
		$this->assertSame( -1, $args['posts_per_page'], 'se exportan todas, no una página' );
		$this->assertSame( 'publish', $args['post_status'] );

		$meta = $args['meta_query'];
		$this->assertSame( 'AND', $meta['relation'] ?? null, 'los filtros se suman, no se sustituyen' );
		// `array_merge()` renumera las claves numéricas: el estado queda en 0 y la actividad en 1.
		$this->assertSame( 'pendiente', $meta[0]['value'] ?? null, 'el estado elegido filtra' );
		$this->assertSame( 77, $meta[1]['value'] ?? null, 'y la actividad también' );
	}

	/**
	 * Sin filtro de estado se exportan las que cuentan como inscripción viva.
	 */
	public function test_sin_filtro_de_estado(): void {
		$this->exportar();

		$meta = (array) $GLOBALS['convoca_test_get_posts_args'][0]['meta_query'];
		$this->assertSame( array( 'confirmada', 'pendiente' ), $meta[0]['value'] ?? null, 'canceladas y lista de espera fuera por defecto' );
		$this->assertSame( 'IN', $meta[0]['compare'] ?? null );
	}

	/**
	 * La exportación deja rastro en el registro (es una descarga de datos personales).
	 */
	public function test_la_exportacion_queda_registrada(): void {
		$_GET['actividad_id'] = '77';

		$this->exportar();

		$logs = (array) ( $GLOBALS['_wp_logs'] ?? array() );
		$this->assertNotEmpty( $logs, 'una exportación de datos personales tiene que quedar registrada' );

		$registro = (array) $logs[0];
		$this->assertSame( 'info', $registro['level'] );
		$this->assertSame( 'Enroll/CSV', $registro['context'], 'con su contexto, para poder filtrarlo' );
		$this->assertSame( 77, $registro['id'], 'y con la actividad, para poder rastrearla' );
		$this->assertStringContainsString( 'Exportación CSV', (string) $registro['message'] );
	}
}
