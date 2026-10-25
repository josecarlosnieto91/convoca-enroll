<?php
/**
 * El chequeo de salud de las cuentas sociales.
 *
 * `social/class-social-healthcheck.php` estaba al 0 % (44 statements). Es el aviso que ve quien
 * gestiona cuando un canal ha caducado: sin él, los posts se encolan, fallan todos y nadie se entera
 * hasta que alguien pregunta por qué no se publicó nada.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_Healthcheck;
use PHPUnit\Framework\TestCase;

class SaludSocialTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		require_once dirname( __DIR__, 2 ) . '/social/class-social-healthcheck.php';
		$GLOBALS['_wp_stores']   = array( 'results' => array(), 'acciones' => array() );
		$GLOBALS['convoca_test_caps'] = array( 'convoca_manage_social' => true );
		$GLOBALS['_convoca_acciones'] = array();
		$GLOBALS['db_inserts']   = array();
		$GLOBALS['_wp_screen'] = '';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		// Igual que arriba: el almacén se vacía, no se borra.
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['convoca_test_caps'], $GLOBALS['_convoca_acciones'], $GLOBALS['db_inserts'], $GLOBALS['_wp_screen'] );
	}

	/**
	 * El chequeo se engancha al cron y al panel.
	 */
	public function test_se_engancha_al_cron_y_al_panel(): void {
		Social_Healthcheck::init();

		$acciones = $GLOBALS['_convoca_acciones'];
		$this->assertContains( array( Social_Healthcheck::class, 'run' ), $acciones['convoca_social_token_healthcheck'] );
		$this->assertContains( array( Social_Healthcheck::class, 'admin_warning' ), $acciones['admin_notices'] );
	}

	/**
	 * Sin cuentas conectadas no se comprueba nada ni se escribe en el registro.
	 */
	public function test_sin_cuentas_no_hace_nada(): void {
		Social_Healthcheck::run();

		$this->assertSame( array(), $GLOBALS['db_inserts'], 'sin cuentas no se registra nada' );
	}

	/**
	 * Una cuenta sin token o de una red que no sabemos atender se salta (no se inventa un fallo).
	 */
	public function test_las_cuentas_sin_token_o_de_red_desconocida_se_saltan(): void {
		$GLOBALS['_wp_stores']['results'] = array(
			array( 'id' => 1, 'network' => 'facebook', 'access_token' => '' ),
			array( 'id' => 2, 'network' => 'mastodon', 'access_token' => 'x' ),
		);

		Social_Healthcheck::run();

		$this->assertSame( array(), $GLOBALS['db_inserts'], 'ni se prueba ni se registra' );
	}

	/**
	 * Sin permiso, el aviso no se pinta.
	 */
	public function test_sin_permiso_no_hay_aviso(): void {
		$GLOBALS['convoca_test_caps']['convoca_manage_social'] = false;
		$GLOBALS['_wp_stores']['results'] = array(
			array( 'id' => 1, 'network' => 'facebook', 'is_active' => 0, 'account_name' => 'Página', 'label' => '' ),
		);

		ob_start();
		Social_Healthcheck::admin_warning();
		$salida = (string) ob_get_clean();

		$this->assertSame( '', $salida );
	}

	/**
	 * Con todo activo no hay nada que avisar.
	 */
	public function test_sin_cuentas_caducadas_no_hay_aviso(): void {
		$GLOBALS['_wp_stores']['results'] = array(
			array( 'id' => 1, 'network' => 'facebook', 'is_active' => 1, 'account_name' => 'Página', 'label' => '' ),
		);

		ob_start();
		Social_Healthcheck::admin_warning();
		$salida = (string) ob_get_clean();

		$this->assertSame( '', $salida );
	}

	/**
	 * Una cuenta caducada se avisa con la lista y el enlace a la pantalla de cuentas.
	 */
	public function test_una_cuenta_caducada_se_avisa(): void {
		$GLOBALS['_wp_stores']['results'] = array(
			array( 'id' => 1, 'network' => 'instagram', 'is_active' => 0, 'account_name' => 'Cuenta IG', 'label' => '' ),
			// Con `account_name` nulo (columna ausente) se usa la etiqueta: es el respaldo real.
			array( 'id' => 2, 'network' => 'facebook', 'is_active' => 0, 'account_name' => null, 'label' => 'Página FB' ),
		);

		ob_start();
		Social_Healthcheck::admin_warning();
		$salida = (string) ob_get_clean();

		$this->assertStringContainsString( 'instagram: Cuenta IG', $salida, 'dice qué cuenta' );
		$this->assertStringContainsString( 'facebook: Página FB', $salida, 'y usa la etiqueta cuando no hay nombre' );
		$this->assertStringContainsString( 'page=convoca-media-accounts', $salida, 'con enlace a donde se arregla' );
	}

	/**
	 * En la pantalla de cuentas el aviso no se repite (ahí ya se ve).
	 */
	public function test_en_la_pantalla_de_cuentas_no_se_repite(): void {
		$GLOBALS['_wp_stores']['results'] = array(
			array( 'id' => 1, 'network' => 'instagram', 'is_active' => 0, 'account_name' => 'Cuenta IG', 'label' => '' ),
		);
		$GLOBALS['_wp_screen'] = 'admin_page_convoca-media-accounts';

		ob_start();
		Social_Healthcheck::admin_warning();
		$salida = (string) ob_get_clean();

		$this->assertSame( '', $salida, 'no se avisa dentro de la propia pantalla' );
	}
}
