<?php
/**
 * Guardado de cuentas sociales: upsert idempotente y fallo visible (enroll#103).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Social\Social_OAuth;
use PHPUnit\Framework\TestCase;

class SocialOAuthStoreTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array(), 'queries' => array() );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_wp_stores'], $GLOBALS['convoca_test_query_retorno'] );
	}

	public function test_el_alta_es_un_upsert_con_label(): void {
		Social_OAuth::store_token( 'facebook', '123', 'Página', 'token' );

		$sql = (string) end( $GLOBALS['_wp_stores']['queries'] );
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', $sql );
		$this->assertStringContainsString( 'label', $sql );
		$this->assertStringContainsString( 'LAST_INSERT_ID(id)', $sql, 'conserva el id al reconectar' );
	}

	public function test_un_fallo_de_persistencia_devuelve_cero(): void {
		$GLOBALS['convoca_test_query_retorno'] = array( 'INSERT INTO' => false );

		$this->assertSame( 0, Social_OAuth::store_token( 'facebook', '123', 'Página', 'token' ) );
	}
}
