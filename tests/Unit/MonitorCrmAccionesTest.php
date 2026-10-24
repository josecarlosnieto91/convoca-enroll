<?php
/**
 * Acciones del panel de monitores (`?action=confirmar|cancelar|promover`).
 *
 * Es la vía por la que un monitor confirma o cancela una inscripción desde el móvil, sin entrar al escritorio de
 * WordPress. Todas las guardas importan: sin permiso, con nonce caducado o sin ser responsable de esa actividad, la
 * acción no se ejecuta y no se redirige como si hubiera ido bien.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Monitor_CRM;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/includes/Motor_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-monitor-crm.php';

/**
 * Acciones del panel.
 */
final class MonitorCrmAccionesTest extends TestCase {

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
		$GLOBALS['_convoca_redirects']    = array();
		$GLOBALS['nonce_invalido']        = false;
		$GLOBALS['convoca_test_caps']     = array( 'manage_inscripciones' => true );
		$GLOBALS['convoca_test_user_can'] = array();
		$_GET                             = array();

		// 410: ya confirmada (confirmar() no escribe nada). 411: cancelada (confirmar() devuelve error de estado).
		$GLOBALS['_wp_stores']['post_meta'][410] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => 'confirmada',
		);
		$GLOBALS['_wp_stores']['post_meta'][411] = array(
			'_convoca_actividad_id' => 61,
			'_convoca_estado'       => 'cancelada',
		);

		$this->panel = new Admin_Monitor_CRM();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']            = $this->previo;
		$GLOBALS['_convoca_redirects']    = array();
		$GLOBALS['nonce_invalido']        = false;
		$GLOBALS['convoca_test_caps']     = array();
		$GLOBALS['convoca_test_user_can'] = array();
		$_GET                             = array();
	}

	/**
	 * Ejecuta la acción y devuelve la redirección (o `null` si no hubo).
	 *
	 * @return string|null
	 */
	private function ejecutar(): ?string {
		try {
			$this->panel->handle_actions();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$r = $cortado->respuesta;
			if ( is_array( $r ) && isset( $r['redirect'] ) ) {
				return (string) $r['redirect'];
			}

			return null;
		}

		return null;
	}

	/**
	 * El mensaje con el que murió, si murió.
	 *
	 * @return string
	 */
	private function muere(): string {
		try {
			$this->panel->handle_actions();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$r = $cortado->respuesta;

			return is_array( $r ) ? (string) ( $r['die'] ?? '' ) : '';
		}

		return '';
	}

	/**
	 * Pedir otra página (o ninguna) no es asunto de este panel.
	 */
	public function test_solo_actua_en_su_pagina(): void {
		$_GET = array( 'page' => 'otra-cosa', 'action' => 'confirmar', 'id' => '410' );

		$this->assertNull( $this->ejecutar() );
		$this->assertSame( array(), $GLOBALS['_convoca_redirects'], 'ni redirige' );

		$_GET = array( 'page' => 'conv-monitor-crm' );
		$this->assertNull( $this->ejecutar(), 'sin acción tampoco' );
	}

	/**
	 * Sin la capacidad, muere y no redirige.
	 */
	public function test_sin_permiso_muere(): void {
		$GLOBALS['convoca_test_caps'] = array( 'manage_inscripciones' => false );
		$_GET                         = array(
			'page'   => 'conv-monitor-crm',
			'action' => 'confirmar',
			'id'       => '410',
			'_wpnonce' => 'nonce-valido',
		);

		$this->assertStringContainsString( 'No tienes permisos suficientes', $this->muere() );
	}

	/**
	 * Nonce caducado: muere aunque tenga permiso.
	 */
	public function test_nonce_invalido_muere(): void {
		$GLOBALS['nonce_invalido'] = true;
		$_GET                      = array(
			'page'   => 'conv-monitor-crm',
			'action' => 'confirmar',
			'id'       => '410',
			'_wpnonce' => 'nonce-valido',
		);

		$this->assertStringContainsString( 'Nonce inválido', $this->muere() );
	}

	/**
	 * Ser monitor de una actividad no da permiso sobre otra: la actividad se deduce de la inscripción.
	 */
	public function test_no_responsable_muere(): void {
		$GLOBALS['convoca_test_user_can']       = array( 'manage_options' => false );
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_responsables' => '7,9' );
		$_GET                                   = array(
			'page'   => 'conv-monitor-crm',
			'action' => 'confirmar',
			'id'       => '410',
			'_wpnonce' => 'nonce-valido',
		);

		$this->assertStringContainsString( 'No tienes permiso para gestionar esta inscripción', $this->muere() );
	}

	/**
	 * Confirmar una inscripción ya confirmada no es un error: redirige al panel de su actividad.
	 */
	public function test_confirmar_redirige_al_panel(): void {
		$_GET = array(
			'page'         => 'conv-monitor-crm',
			'action'       => 'confirmar',
			'id'           => '410',
			'actividad_id' => '61',
			'_wpnonce'     => 'nonce-valido',
		);

		$destino = $this->ejecutar();

		$this->assertNotNull( $destino, 'redirige' );
		$this->assertStringContainsString( 'page=conv-monitor-crm', $destino );
		$this->assertStringContainsString( 'actividad_id=61', $destino );
	}

	/**
	 * Confirmar algo que no se puede confirmar muere con el motivo, en vez de redirigir como si nada.
	 */
	public function test_confirmar_invalido_muere_con_el_motivo(): void {
		$_GET = array(
			'page'         => 'conv-monitor-crm',
			'action'       => 'confirmar',
			'id'           => '411',
			'actividad_id' => '61',
			'_wpnonce'     => 'nonce-valido',
		);

		$this->assertStringContainsString( 'Solo se pueden confirmar', $this->muere() );
	}

	/**
	 * Una acción que no existe no rompe: se redirige igual (sin hacer nada).
	 */
	public function test_accion_desconocida_no_rompe(): void {
		$_GET = array(
			'page'         => 'conv-monitor-crm',
			'action'       => 'inventada',
			'id'           => '410',
			'actividad_id' => '61',
			'_wpnonce'     => 'nonce-valido',
		);

		$this->assertNotNull( $this->ejecutar(), 'redirige sin hacer nada' );
	}
}
