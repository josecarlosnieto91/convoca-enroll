<?php
/**
 * Exportación CSV desde el panel de monitores.
 *
 * El monitor se lleva la lista el día de la actividad (para el portátil sin red, o para imprimir). Lo que se prueba:
 * quién puede exportar, qué filas salen —una por inscripción, con su asistencia y su token de check-in—, que el
 * export de «asistentes» filtre de verdad, y que el nombre del fichero lleve la actividad y el tipo.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Monitor_CRM;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-monitor-crm.php';

/**
 * Exportación CSV.
 */
final class MonitorCrmExportTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Panel.
	 *
	 * @var Admin_Monitor_CRM
	 */
	private Admin_Monitor_CRM $panel;

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
		$GLOBALS['_csv']                          = array();
		$GLOBALS['nonces_invalidos']              = array();
		$GLOBALS['convoca_test_posts']            = array();
		$GLOBALS['convoca_test_get_posts_args']   = array();
		$GLOBALS['convoca_test_caps']             = array( 'manage_inscripciones' => true );
		$GLOBALS['convoca_test_user_can']         = array();
		$_GET                                     = array();

		$actividad                                    = new \WP_Post();
		$actividad->ID                                = 61;
		$actividad->post_type                         = 'actividad';
		$actividad->post_title                        = 'Ruta de Otoño';
		$GLOBALS['_wp_stores']['posts'][61]           = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61]       = array( '_convoca_fecha' => '2026-11-14' );

		foreach ( array( 70 => 'Ana', 71 => array( 'Luis', '' ) ) as $id => $quien ) {
			$nombre = is_array( $quien ) ? $quien[0] : $quien;
			$asist  = is_array( $quien ) ? $quien[1] : 'si';

			$inscripcion                          = new \WP_Post();
			$inscripcion->ID                      = $id;
			$inscripcion->post_type               = 'inscripcion';
			$inscripcion->post_title              = $nombre;
			$GLOBALS['_wp_stores']['posts'][ $id ] = $inscripcion;
			$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
				'_convoca_actividad_id'    => 61,
				'_convoca_nombre'          => $nombre,
				'_convoca_email'           => strtolower( $nombre ) . $id . '@example.test',
				'_convoca_telefono'        => '600000000',
				'_convoca_estado'          => 'confirmada',
				'_convoca_asistencia'      => $asist,
				'_convoca_checkin_token'   => 'tok' . $id,
			);
			$GLOBALS['convoca_test_posts'][] = $inscripcion;
		}

		$this->panel = new Admin_Monitor_CRM();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['_csv']                        = array();
		$GLOBALS['nonces_invalidos']            = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_args'] = array();
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['convoca_test_user_can']       = array();
		$_GET                                   = array();
	}

	/**
	 * Ejecuta la exportación.
	 *
	 * @return void
	 */
	private function exportar(): void {
		try {
			$this->panel->handle_exports();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			unset( $cortado );
		}
	}

	/**
	 * El CSV se llama por la actividad y el tipo, y trae una fila por inscripción con su asistencia y su token.
	 */
	public function test_exporta_las_inscripciones(): void {
		$_GET = array(
			'page'         => 'conv-monitor-crm',
			'export'       => 'todos',
			'actividad_id' => '61',
			'_wpnonce'     => 'x',
		);

		$this->exportar();

		$this->assertCount( 1, $GLOBALS['_csv'], 'un solo fichero' );
		$csv = $GLOBALS['_csv'][0];

		$this->assertSame( 'ruta-de-otono-todos-' . wp_date( 'Y-m-d' ) . '.csv', $csv['fichero'] );
		$this->assertCount( 8, $csv['cabecera'] );
		$this->assertSame( 'Token Check-in', $csv['cabecera'][7] );
		$this->assertCount( 2, $csv['filas'], 'una por inscripción' );

		$this->assertSame( 'Ruta de Otoño', $csv['filas'][0][0], 'la actividad' );
		$this->assertSame( '2026-11-14', $csv['filas'][0][1], 'su fecha' );
		$this->assertSame( 'Ana', $csv['filas'][0][2] );
		$this->assertSame( 'ana70@example.test', $csv['filas'][0][3] );
		$this->assertSame( 'si', $csv['filas'][0][6], 'la asistencia' );
		$this->assertSame( 'tok70', $csv['filas'][0][7], 'el token de check-in' );
	}

	/**
	 * El export de asistentes filtra por asistencia: es el que se lleva quien sí vino.
	 */
	public function test_el_export_de_asistentes_filtra(): void {
		$_GET = array(
			'page'         => 'conv-monitor-crm',
			'export'       => 'asistentes',
			'actividad_id' => '61',
			'_wpnonce'     => 'x',
		);

		$this->exportar();

		$args = end( $GLOBALS['convoca_test_get_posts_args'] );
		$meta = $args['meta_query'];
		$asistencia = null;
		foreach ( $meta as $parte ) {
			if ( is_array( $parte ) && str_ends_with( (string) $parte['key'], 'asistencia' ) ) {
				$asistencia = $parte;
			}
		}

		$this->assertNotNull( $asistencia, 'el export de asistentes añade el filtro' );
		$this->assertSame( array( 'si', '1' ), $asistencia['value'], 'la forma actual y la antigua' );
		$this->assertStringContainsString( 'asistentes', $GLOBALS['_csv'][0]['fichero'] );
	}

	/**
	 * Pedir otra página no exporta nada.
	 */
	public function test_solo_exporta_en_su_pagina(): void {
		$_GET = array( 'page' => 'otra', 'export' => 'todos', 'actividad_id' => '61' );
		$this->exportar();

		$this->assertSame( array(), $GLOBALS['_csv'] );
	}

	/**
	 * Sin la capacidad no se exporta (y muere).
	 */
	public function test_sin_permiso_no_exporta(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => false );
		$_GET                         = array(
			'page'         => 'conv-monitor-crm',
			'export'       => 'todos',
			'actividad_id' => '61',
			'_wpnonce'     => 'x',
		);

		$this->exportar();

		$this->assertSame( array(), $GLOBALS['_csv'] );
	}

	/**
	 * Con nonce caducado tampoco.
	 */
	public function test_nonce_invalido_no_exporta(): void {
		$GLOBALS['nonces_invalidos']['convoca_enroll_export_csv'] = true;
		$_GET                                                     = array(
			'page'         => 'conv-monitor-crm',
			'export'       => 'todos',
			'actividad_id' => '61',
			'_wpnonce'     => 'x',
		);

		$this->exportar();

		$this->assertSame( array(), $GLOBALS['_csv'] );
	}

	/**
	 * Ser monitor de otra actividad no da los datos de esta: son datos personales.
	 */
	public function test_no_responsable_no_exporta(): void {
		$GLOBALS['convoca_test_user_can']        = array( 'manage_options' => false );
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha'        => '2026-11-14',
			'_convoca_responsables' => '7,9',
		);
		$_GET = array(
			'page'         => 'conv-monitor-crm',
			'export'       => 'todos',
			'actividad_id' => '61',
			'_wpnonce'     => 'x',
		);

		$this->exportar();

		$this->assertSame( array(), $GLOBALS['_csv'], 'ni una fila con datos personales' );
	}
}
