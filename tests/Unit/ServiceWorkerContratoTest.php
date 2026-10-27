<?php
/**
 * Contrato del service worker del check-in.
 *
 * El worker precacheaba `public/assets/css/checkin.css`, que **no existe**: el 404 hacía que
 * `cache.addAll()` rechazara la instalación y el service worker no llegaba a registrarse. Y el
 * listener `sync` que vacía la cola estaba, pero nadie registraba el tag. Las dos cosas dejaban la
 * promesa del check-in sin cobertura en papel mojado (issue #54).
 *
 * Estas pruebas leen el `sw.js` y comprueban lo que un test de PHP puede comprobar: que lo que
 * precachea existe, que precachea el documento y que registra el tag de sincronización.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

class ServiceWorkerContratoTest extends TestCase {

	/**
	 * Ruta absoluta del service worker.
	 */
	private function ruta(): string {
		return dirname( __DIR__, 2 ) . '/public/assets/pwa/sw.js';
	}

	/**
	 * Contenido del service worker.
	 */
	private function sw(): string {
		$contenido = file_get_contents( $this->ruta() );

		$this->assertIsString( $contenido, 'No se pudo leer el service worker.' );

		return (string) $contenido;
	}

	/**
	 * Cada ruta declarada en `ASSETS` tiene que existir en el plugin.
	 */
	public function test_las_rutas_precacheadas_existen(): void {
		$sw = $this->sw();

		$this->assertSame(
			1,
			preg_match( '/const ASSETS = \[(.*?)\];/s', $sw, $bloque ),
			'No se encontró la lista ASSETS en el service worker.'
		);

		preg_match_all( "/'([^']+)'/", $bloque[1], $rutas );

		$base = dirname( __DIR__, 2 );

		foreach ( $rutas[1] as $url ) {
			$relativa = str_replace( '/wp-content/plugins/convoca-enroll/', '', $url );

			$this->assertFileExists(
				$base . '/' . ltrim( $relativa, '/' ),
				sprintf( 'El service worker precachea «%s» y ese fichero no existe: el 404 tumba la instalación.', $url )
			);
		}
	}

	/**
	 * El documento de la PWA tiene que estar en el precache (sin él no se abre sin red).
	 */
	public function test_precachea_el_documento_del_checkin(): void {
		$this->assertStringContainsString(
			"const SHELL = ['/checkin/'];",
			$this->sw(),
			'El service worker no precachea el documento de /checkin/: sin red no hay página que abrir.'
		);
	}

	/**
	 * La instalación no puede depender de un solo recurso: un fallo no debe tumbarla.
	 */
	public function test_la_instalacion_no_usa_addall(): void {
		$this->assertStringNotContainsString(
			'cache.addAll(',
			$this->sw(),
			'Con `addAll`, un solo 404 rechaza la instalación del service worker.'
		);
	}

	/**
	 * El tag que vacía la cola al recuperar la conexión tiene que registrarse.
	 */
	public function test_registra_el_tag_de_sincronizacion(): void {
		$sw = $this->sw();

		$this->assertStringContainsString(
			"sync.register('conv-flush-checkins')",
			$sw,
			'Nadie registra el tag de sincronización: la cola de check-ins no se vacía nunca.'
		);

		$this->assertStringContainsString(
			"event.tag === 'conv-flush-checkins'",
			$sw,
			'El listener de sincronización no escucha el tag que se registra.'
		);
	}
}
