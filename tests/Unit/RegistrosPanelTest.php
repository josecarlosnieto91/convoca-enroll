<?php
/**
 * La pantalla de registros del panel.
 *
 * `admin/class-admin-logs.php` estaba al 0 % (104 statements). Es la pantalla que se mira cuando algo ha ido mal,
 * así que lo que se prueba es que diga la verdad: si la tabla no existe lo explica, no inventa filas vacías, no
 * enseña HTML de un mensaje ajeno y no borra nada sin permiso ni sin nonce.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Logs;
use PHPUnit\Framework\TestCase;

class RegistrosPanelTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']            = array( 'options' => array(), 'results_mapa' => array(), 'edit_links' => array(), 'get_var' => 'wp_convoca_logs' );
		$GLOBALS['db_reads']              = array();
		$GLOBALS['db_prepares']           = array();
		$GLOBALS['_wp_paginacion_links']  = array();
		$GLOBALS['convoca_test_caps']     = array( 'manage_options' => true );
		$GLOBALS['nonces_invalidos']      = array();
		$_GET                             = array();
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['db_reads'], $GLOBALS['db_prepares'], $GLOBALS['_wp_paginacion_links'], $GLOBALS['convoca_test_caps'], $GLOBALS['nonces_invalidos'] );
		$_GET = array();
	}

	/**
	 * Pinta la pantalla y devuelve su HTML.
	 *
	 * @return string
	 */
	private function pintar(): string {
		ob_start();
		Admin_Logs::render();

		return (string) ob_get_clean();
	}

	/**
	 * Una fila del registro.
	 *
	 * @param array<string, mixed> $datos Datos que se cambian.
	 * @return object
	 */
	private function fila( array $datos = array() ): object {
		return (object) array_merge(
			array(
				'created_at' => '2026-10-24 09:30:00',
				'level'      => 'error',
				'message'    => 'No se pudo enviar el correo',
				'context'    => 'Enroll/Mailer',
				'object_id'  => 0,
			),
			$datos
		);
	}

	/**
	 * Si la tabla no existe, se dice qué hacer en vez de enseñar una tabla vacía.
	 */
	public function test_si_no_hay_tabla_lo_explica(): void {
		$GLOBALS['_wp_stores']['tablas_existentes'] = array( 'wp_convoca_otra_cosa' );

		$html = $this->pintar();

		$this->assertStringContainsString( 'no ha sido creada', $html );
		$this->assertStringContainsString( 'Convoca Core', $html, 'dice qué hay que reactivar' );
		$this->assertStringNotContainsString( 'No hay logs registrados', $html, 'no se disimula con una tabla vacía' );
	}

	/**
	 * Sin filas, la tabla lo dice en claro.
	 */
	public function test_sin_filas_lo_dice(): void {
		$GLOBALS['_wp_stores']['tablas_existentes']        = array( 'wp_convoca_logs' );
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *'] = array();

		$html = $this->pintar();

		$this->assertStringContainsString( 'No hay logs registrados', $html );
	}

	/**
	 * Las filas salen con fecha, nivel, mensaje, contexto y objeto, y el mensaje va escapado.
	 */
	public function test_las_filas_se_pintan_escapadas(): void {
		$GLOBALS['_wp_stores']['tablas_existentes']        = array( 'wp_convoca_logs' );
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *'] = array(
			$this->fila( array( 'message' => 'Fallo <script>alert(1)</script>' ) ),
		);

		$html = $this->pintar();

		$this->assertStringContainsString( '24/10/2026', $html, 'la fecha en formato de aquí' );
		$this->assertStringContainsString( '09:30', $html );
		$this->assertStringContainsString( 'convoca-badge--error', $html, 'el nivel con su insignia' );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html, 'el mensaje de un registro no puede inyectar HTML' );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( 'Enroll/Mailer', $html );

		// Solo se listan los registros de Enroll: la tabla es común a todo el ecosistema.
		$preparadas = implode( "\n", (array) $GLOBALS['db_prepares'] );
		$this->assertStringContainsString( 'context LIKE %s', $preparadas );
	}

	/**
	 * Un objeto editable se enlaza; uno borrado o sin permiso se queda en `#id`.
	 */
	public function test_el_objeto_se_enlaza_solo_si_se_puede(): void {
		$GLOBALS['_wp_stores']['tablas_existentes']           = array( 'wp_convoca_logs' );
		$GLOBALS['_wp_stores']['edit_links'][77]              = 'https://example.test/wp-admin/post.php?post=77&action=edit';
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *']    = array(
			$this->fila( array( 'object_id' => 77 ) ),
			$this->fila( array( 'object_id' => 88 ) ),
			$this->fila( array( 'object_id' => 0 ) ),
		);

		$html = $this->pintar();

		$this->assertStringContainsString( 'post.php?post=77', $html, 'el que existe se puede abrir' );
		$this->assertStringContainsString( '#88', $html, 'el que ya no existe se queda en su número' );
		$this->assertStringContainsString( '—', $html, 'y sin objeto, un guion' );
	}

	/**
	 * Borrar los registros exige permiso de administración.
	 */
	public function test_borrar_sin_permiso_no_borra(): void {
		$GLOBALS['convoca_test_caps']['manage_options'] = false;
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *'] = array();
		$_GET['action'] = 'clear';

		$html = $this->pintar();

		$this->assertStringNotContainsString( 'Logs de Enroll borrados', $html );
		$consultas = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringNotContainsString( 'DELETE', $consultas, 'sin permiso no se borra nada' );
	}

	/**
	 * Con permiso, el borrado solo toca los registros de Enroll.
	 */
	public function test_borrar_toca_solo_los_registros_de_enroll(): void {
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *'] = array();
		$_GET['action'] = 'clear';

		$html = $this->pintar();

		$this->assertStringContainsString( 'borrados', $html );

		$consultas = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringContainsString( 'DELETE FROM wp_convoca_logs', $consultas );
		$this->assertStringContainsString( "context LIKE '%Enroll%'", $consultas, 'los registros de los demás plugins no se tocan' );
	}

	/**
	 * La paginación aparece cuando hay más de una página, con el total real.
	 */
	public function test_la_paginacion_aparece_solo_si_hay_paginas(): void {
		$GLOBALS['_wp_stores']['get_var_cola']             = array( 'wp_convoca_logs', '120' );
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *'] = array( $this->fila() );

		$html = $this->pintar();

		$this->assertStringContainsString( 'tablenav', $html, '120 registros son tres páginas con 50 por página' );
		$paginacion = (array) ( $GLOBALS['_wp_paginacion_links'][0] ?? array() );
		$this->assertSame( 3, (int) ( $paginacion['total'] ?? 0 ) );
		$this->assertSame( 1, (int) ( $paginacion['current'] ?? 0 ) );
	}

	/**
	 * Con pocas filas no se pinta paginación (ni se ofrece ir a una página que no existe).
	 */
	public function test_sin_paginas_no_hay_paginacion(): void {
		$GLOBALS['_wp_stores']['tablas_existentes']        = array( 'wp_convoca_logs' );
		$GLOBALS['_wp_stores']['results_mapa']['SELECT *'] = array( $this->fila() );

		$html = $this->pintar();

		$this->assertStringNotContainsString( 'tablenav', $html );
	}
}
