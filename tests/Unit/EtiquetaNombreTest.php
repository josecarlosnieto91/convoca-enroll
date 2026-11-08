<?php
/**
 * La etiqueta del nombre habla de «adulto responsable» solo si hay menores (issue #70).
 *
 * En un taller de adultos la etiqueta decía «Nombre completo del adulto responsable» aunque el bloque de
 * menores estuviera apagado: sugiere que hay que apuntar a alguien más.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Utils;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';

final class EtiquetaNombreTest extends TestCase {

	/**
	 * Con menores, se habla de responsable.
	 */
	public function test_con_menores_la_etiqueta_habla_de_responsable(): void {
		$this->assertStringContainsString( 'responsable', Utils::etiqueta_nombre( true ) );
	}

	/**
	 * Sin menores, no.
	 */
	public function test_sin_menores_la_etiqueta_no_habla_de_responsable(): void {
		$etiqueta = Utils::etiqueta_nombre( false );

		$this->assertStringNotContainsString( 'responsable', $etiqueta, 'en un taller de adultos sobra' );
		$this->assertStringContainsString( 'Nombre completo', $etiqueta );
	}
}
