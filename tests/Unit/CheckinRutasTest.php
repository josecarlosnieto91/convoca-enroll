<?php
/**
 * Las rutas de check-in tienen que rellenar la query var que el handler lee.
 *
 * Defecto real del 2026-09-26: los rewrites de `/checkin/` y `/checkin/<token>/` ponían
 * `conv_enroll_checkin*` mientras la query var registrada (y leída) es
 * `convoca_enroll_checkin*`. Como los nombres no coincidían, WordPress no rellenaba nada,
 * el handler salía por el `return` de la primera línea y la página del escáner respondía
 * con la web normal (200) en lugar de con el escáner. La ruta del diseño no llevaba a
 * ninguna parte.
 *
 * Es un contrato entre tres sitios del mismo fichero: el `add_rewrite_rule`, el filtro
 * `query_vars` y el `get_query_var`. Ningún lint lo ve.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests del contrato de rutas del check-in.
 */
class CheckinRutasTest extends TestCase
{
	/**
	 * Fuente del handler.
	 *
	 * @return string
	 */
	private function fuente(): string {
		$ruta = dirname( __DIR__, 2 ) . '/includes/Checkin_Handler.php';
		$this->assertFileExists( $ruta );

		return (string) file_get_contents( $ruta );
	}

	public function test_el_rewrite_usa_la_misma_query_var_que_el_handler(): void {
		$fuente = $this->fuente();

		preg_match_all( "/add_rewrite_rule\(\s*'[^']*'\s*,\s*'index\.php\?([a-z0-9_]+)=/", $fuente, $m );
		$this->assertNotEmpty( $m[1], 'Debería haber reglas de reescritura que revisar.' );

		preg_match_all( "/\\\$vars\[\]\s*=\s*'([a-z0-9_]+)'/", $fuente, $v );
		$this->assertNotEmpty( $v[1], 'Debería haber query vars registradas.' );

		foreach ( $m[1] as $en_rewrite ) {
			$this->assertContains(
				$en_rewrite,
				$v[1],
				"El rewrite usa «{$en_rewrite}» pero esa query var no se registra: la ruta no llevará a ninguna parte."
			);
		}
	}

	public function test_el_handler_lee_las_query_vars_registradas(): void {
		$fuente = $this->fuente();

		preg_match_all( "/get_query_var\(\s*'([a-z0-9_]+)'\s*\)/", $fuente, $g );
		preg_match_all( "/\\\$vars\[\]\s*=\s*'([a-z0-9_]+)'/", $fuente, $v );

		$this->assertNotEmpty( $g[1] );
		foreach ( $g[1] as $leida ) {
			$this->assertContains(
				$leida,
				$v[1],
				"El handler lee «{$leida}», que no está registrada como query var: siempre llegará vacía."
			);
		}
	}

	public function test_ninguna_ruta_queda_con_el_prefijo_viejo(): void {
		// Solo las reglas: el comentario que explica el defecto menciona el prefijo viejo
		// a propósito, así que no puede contar como código.
		$this->assertStringNotContainsString( 'index.php?conv_enroll_checkin', $this->fuente() );
	}
}
