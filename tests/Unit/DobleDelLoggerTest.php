<?php
declare(strict_types=1);

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * El doble de `Convoca\Core\Logger` tiene que copiar el contrato real.
 *
 * Un doble más estricto que el original no protege: esconde. Si en producción se puede pasar `null` y en la prueba
 * revienta, la prueba falla por su propia ficción y el defecto real queda tapado (enroll#45).
 */
final class DobleDelLoggerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_logs'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['_wp_logs'] );
		parent::tearDown();
	}

	/**
	 * `log()` acepta `null` en el cuarto argumento, como el núcleo.
	 */
	public function test_log_acepta_null_en_el_identificador(): void {
		\Convoca\Core\Logger::log( 'sin entidad', 'info', 'Enroll/Prueba', null );

		$this->assertCount( 1, $GLOBALS['_wp_logs'] );
		$this->assertSame( 0, $GLOBALS['_wp_logs'][0]['id'], 'null se normaliza a 0 para el registro' );
	}

	/**
	 * El contexto por defecto es `General`, igual que en el núcleo —no la cadena vacía—.
	 */
	public function test_el_contexto_por_defecto_es_general(): void {
		\Convoca\Core\Logger::log( 'sin contexto' );

		$this->assertSame( 'General', $GLOBALS['_wp_logs'][0]['context'] );
	}

	/**
	 * Un identificador real llega intacto.
	 */
	public function test_el_identificador_llega_intacto(): void {
		\Convoca\Core\Logger::log( 'con entidad', 'info', 'Enroll/Prueba', 61 );

		$this->assertSame( 61, $GLOBALS['_wp_logs'][0]['id'] );
	}
}
