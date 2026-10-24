<?php
/**
 * Carteles: se generan de verdad y se cachean.
 *
 * No se puede medir un generador de imágenes leyéndolo: aquí se ejecuta con Imagick y se comprueba el **PNG resultante**
 * (que exista, que sea un PNG y que pese). Lo que se vigila es el ahorro: la segunda llamada no debe volver a dibujar,
 * y si el contenido cambia sí.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Media\Poster_Engine;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/media/class-poster-engine.php';

/**
 * Generación.
 */
final class PosterRenderTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Directorio de trabajo.
	 *
	 * @var string
	 */
	private string $carpeta = '';

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

		$this->carpeta                   = dirname( __DIR__, 2 ) . '/build/carteles-test';
		$GLOBALS['convoca_test_uploads'] = $this->carpeta;

		if ( ! defined( 'CONVOCA_ENROLL_DIR' ) ) {
			define( 'CONVOCA_ENROLL_DIR', dirname( __DIR__, 2 ) . '/' );
		}

		$actividad             = new \WP_Post();
		$actividad->ID         = 61;
		$actividad->post_type  = 'actividad';
		$actividad->post_title = 'Ruta de otoño';
		$actividad->post_modified = '2026-10-20 10:00:00';

		$GLOBALS['_wp_stores']['posts'][61]     = $actividad;
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'   => '2026-11-14 18:00:00',
			'_convoca_fecha_fin'      => '2026-11-14 20:00:00',
			'_convoca_ubicacion'      => 'Centro Social',
			'_convoca_precio_general' => '12,50',
		);
	}

	/**
	 * Borra lo generado y devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']           = $this->previo;
		$GLOBALS['convoca_test_uploads'] = null;

		foreach ( (array) glob( $this->carpeta . '/convoca-posters/*' ) as $fichero ) {
			@unlink( $fichero );
		}
		foreach ( (array) glob( $this->carpeta . '/convoca-qr/*' ) as $fichero ) {
			@unlink( $fichero );
		}
	}

	/**
	 * Genera un cartel y devuelve el resultado.
	 *
	 * @param array $extra Opciones.
	 * @return array|\WP_Error
	 */
	private function generar( array $extra = array() ) {
		return Poster_Engine::render( 61, 'nature-classic', array_merge( array( 'force' => true ), $extra ) );
	}

	/**
	 * El cartel se genera: el fichero existe y es un PNG de verdad.
	 */
	public function test_genera_un_png(): void {
		$r = $this->generar();

		$this->assertIsArray( $r, 'no es un error' );
		$ruta = $r['files']['square'] ?? '';

		$this->assertFileExists( $ruta );
		$this->assertGreaterThan( 2000, filesize( $ruta ), 'un cartel no pesa cuatro bytes' );

		$cabecera = (string) file_get_contents( $ruta, false, null, 0, 8 );
		$this->assertSame( "\x89PNG\r\n\x1a\n", $cabecera, 'es un PNG' );
	}

	/**
	 * Sin `force`, la segunda llamada sirve el de la caché en vez de volver a dibujar.
	 */
	public function test_la_cache(): void {
		$primera = $this->generar();
		$ruta    = $primera['files']['square'];
		$marca   = filemtime( $ruta );

		sleep( 1 );
		$segunda = Poster_Engine::render( 61, 'nature-classic' );

		$this->assertTrue( $segunda['cached'], 'se reutiliza' );
		$this->assertSame( $marca, filemtime( $ruta ), 'no se ha vuelto a dibujar' );
	}

	/**
	 * Cambiar el contenido cambia el fichero: el cartel no se queda con el título viejo.
	 */
	public function test_cambia_con_el_contenido(): void {
		$primera = $this->generar();
		$GLOBALS['_wp_stores']['posts'][61]->post_title = 'Ruta de invierno';

		$segunda = $this->generar( array( 'force' => true ) );

		$this->assertNotSame( $primera['files']['square'], $segunda['files']['square'], 'otra clave de caché' );
	}

	/**
	 * Otra plantilla, otro cartel.
	 */
	public function test_otra_plantilla(): void {
		$r = Poster_Engine::render( 61, 'modern-ngo', array( 'force' => true ) );

		$this->assertArrayHasKey( 'square', $r['files'] );
		$this->assertStringContainsString( 'modern-ngo', $r['files']['square'] );
	}

	/**
	 * Una actividad que no existe no genera nada.
	 */
	public function test_actividad_inexistente(): void {
		$r = Poster_Engine::render( 999, 'nature-classic', array( 'force' => true ) );

		$this->assertInstanceOf( \WP_Error::class, $r );
	}

	/**
	 * La URL devuelta apunta al fichero generado.
	 */
	public function test_la_url(): void {
		$r = $this->generar();

		$this->assertStringContainsString( 'convoca-posters/', $r['url'] );
		$this->assertStringEndsWith( '.png', $r['url'] );
	}
}
