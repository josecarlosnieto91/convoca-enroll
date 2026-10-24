<?php
/**
 * Carteles: las piezas que se publican sin revisión.
 *
 * El cartel de una actividad se genera a partir de sus datos y se sube a redes tal cual. Aquí se prueban las piezas
 * que se calculan antes de dibujar: la fecha («14 de noviembre de 2026», y el rango si dura dos días), la hora (que
 * **no** anuncia un evento a medianoche cuando no hay hora), el precio, las filas del cartel y el recorte del texto.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/media/class-poster-engine.php';

/**
 * Piezas del cartel.
 */
final class PosterEngineTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

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
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Llama a un método privado del motor.
	 *
	 * @param string $metodo Método.
	 * @param mixed  ...$args Argumentos.
	 * @return mixed
	 */
	private function llamar( string $metodo, ...$args ) {
		$clase = 'Convoca\\Enroll\\Poster_Engine';
		if ( ! class_exists( $clase ) ) {
			$clase = 'Convoca\\Enroll\\Media\\Poster_Engine';
		}
		$reflejo = new \ReflectionMethod( $clase, $metodo );
		return $reflejo->invoke( null, ...$args );
	}

	/**
	 * Los datos tal como los arma `activity_data()`: las cinco claves, aunque estén vacías.
	 *
	 * @param array $encima Valores que pisa la prueba.
	 * @return array
	 */
	private function datos( array $encima = array() ): array {
		return array_merge(
			array(
				'date'     => '',
				'time'     => '',
				'location' => '',
				'places'   => 0,
				'price'    => '',
			),
			$encima
		);
	}

	/**
	 * Las filas del cartel: cinco fijas y, si hay, organiza y edad. Lo que falta se dice, no se deja en blanco.
	 */
	public function test_las_filas(): void {
		$filas = $this->llamar(
			'filas_de_datos',
			$this->datos(
				array(
					'date'      => '14 de noviembre de 2026',
					'time'      => '18:00',
					'location'  => 'Centro Social',
					'places'    => 3,
					'price'     => '5,00 €',
					'organizer' => 'Asociación de ejemplo',
					'age'       => 'Familias',
				)
			)
		);

		$this->assertCount( 7, $filas );
		$etiquetas = array_column( $filas, 0 );
		$this->assertSame( array( 'Fecha', 'Hora', 'Lugar', 'Plazas', 'Precio', 'Organiza', 'Edad' ), $etiquetas );
		$this->assertSame( '3 plazas', $filas[3][1], 'el plural' );
	}

	/**
	 * Una plaza: en singular.
	 */
	public function test_una_sola_plaza(): void {
		$filas = $this->llamar( 'filas_de_datos', $this->datos( array( 'places' => 1 ) ) );

		$this->assertSame( '1 plaza', $filas[3][1] );
	}

	/**
	 * Sin datos, el cartel dice «Por confirmar» en vez de callarse.
	 */
	public function test_sin_datos(): void {
		// Las cinco claves que arma `activity_data()`, todas vacías. (Sin las claves, la función emite avisos de
		// «Undefined array key»: es interna y solo la llama el propio motor con el array completo.)
		$filas = $this->llamar( 'filas_de_datos', $this->datos() );

		$this->assertCount( 5, $filas, 'sin organiza ni edad' );
		foreach ( $filas as $fila ) {
			$this->assertSame( 'Por confirmar', $fila[1], 'campo vacío: ' . $fila[0] );
		}
	}

	/**
	 * Fecha y hora se separan de la cadena de la base.
	 */
	public function test_separa_fecha_y_hora(): void {
		$this->assertSame( array( '2026-11-14', '18:00:00' ), $this->llamar( 'split_datetime', '2026-11-14 18:00:00' ) );
		$this->assertSame( array( '2026-11-14', '' ), $this->llamar( 'split_datetime', '2026-11-14' ) );
		$this->assertSame( array( 'sin fecha', '' ), $this->llamar( 'split_datetime', 'sin fecha' ) );
	}

	/**
	 * La fecha: un día; si acaba otro día, el rango.
	 */
	public function test_la_fecha(): void {
		$un_dia = $this->llamar( 'date_label', '2026-11-14 18:00:00', '2026-11-14 20:30:00' );
		$this->assertMatchesRegularExpression( '/14\s+\S+\s+2026/', $un_dia, 'mismo día: una sola fecha' );
		$this->assertStringNotContainsString( '–', $un_dia, 'sin rango si acaba el mismo día' );

		$rango = $this->llamar( 'date_label', '2026-11-14 18:00:00', '2026-11-15 12:00:00' );
		$this->assertStringContainsString( '–', $rango, 'acaba otro día: rango' );
		$this->assertStringContainsString( '15', $rango, 'con el día de fin' );

		$this->assertSame( '', $this->llamar( 'date_label', '', '' ), 'sin fecha, vacío' );
	}

	/**
	 * La hora: con hora, el rango; sin hora y a las 00:00 **no** se anuncia medianoche.
	 */
	public function test_la_hora(): void {
		$this->assertSame( '18:00–20:30', $this->llamar( 'time_label', '2026-11-14 18:00:00', '2026-11-14 20:30:00', '18:00-20:30' ) );
		$this->assertSame( '', $this->llamar( 'time_label', '2026-11-14 00:00:00', '', '' ), 'sin hora no hay medianoche' );
		$this->assertSame( '18:00', $this->llamar( 'time_label', '2026-11-14 18:00:00', '2026-11-14 18:00:00', '' ) );
	}

	/**
	 * El precio: con dos decimales y euro; sin precio, «Gratuito».
	 */
	public function test_el_precio(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_precio_socio' => '60,50' );
		$this->assertSame( '60,50 €', $this->llamar( 'price_label', 61 ) );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_precio_socio'] = '0';
		$this->assertSame( 'Gratuito', $this->llamar( 'price_label', 61 ) );
	}

	/**
	 * El primer meta que tenga valor (no el primero de la lista si está vacío).
	 */
	public function test_el_primer_meta_con_valor(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_precio_socio' => '',
			'_convoca_precio_general' => '12',
		);

		$this->assertSame( '12', $this->llamar( 'meta_first', 61, array( '_convoca_precio_socio', '_convoca_precio_general' ) ) );
		$this->assertSame( '', $this->llamar( 'meta_first', 61, array( 'no_existe' ) ) );
	}

	/**
	 * El recorte del texto no parte una letra acentuada por la mitad.
	 */
	public function test_recorta_sin_partir_letras(): void {
		$texto = $this->llamar( 'plain_trim', '<p>Una actividad de educación ambiental en el monte</p>', 20 );

		$this->assertSame( 20, mb_strlen( $texto ) );
		$this->assertStringEndsWith( '…', $texto );

		$this->assertSame( 'Corto', $this->llamar( 'plain_trim', 'Corto', 20 ), 'lo que cabe entero no se toca' );
	}

	/**
	 * El estilo del evento: etiqueta legible; sin tipo, «Actividad».
	 */
	public function test_el_estilo(): void {
		$estilo = $this->llamar( 'event_style', 'taller-de-setas' );

		$this->assertSame( 'Taller de setas', $estilo['label'] );
		$this->assertSame( '#5fa65a', $estilo['color'] );

		$this->assertSame( 'Actividad', $this->llamar( 'event_style', '' )['label'] );
	}

	/**
	 * El formato de exportación: `jpeg` es `jpg`; lo que no se conoce cae en `png`.
	 */
	public function test_el_formato_de_exportacion(): void {
		$this->assertSame( 'jpg', $this->llamar( 'normalize_export', 'JPEG' ) );
		$this->assertSame( 'webp', $this->llamar( 'normalize_export', 'webp' ) );
		$this->assertSame( 'png', $this->llamar( 'normalize_export', 'inventado' ) );
	}

	/**
	 * Los formatos del cartel: se descartan los que no existen y no se repiten.
	 */
	public function test_los_formatos(): void {
		$this->assertSame(
			array( 'story', 'square' ),
			$this->llamar( 'normalize_formats', array( 'formats' => array( 'story', 'inventado', 'story', 'square' ) ) )
		);
		$this->assertSame( array( 'square' ), $this->llamar( 'normalize_formats', array() ), 'por omisión, cuadrado' );
	}

	/**
	 * Las medidas de cada formato, y una desconocida cae en cuadrado.
	 */
	public function test_las_medidas(): void {
		$todas = $this->llamar( 'all_format_dimensions' );
		$this->assertArrayHasKey( 'a4', $todas );
		$this->assertSame( 1080, $todas['story']['width'] );
		$this->assertSame( 1920, $todas['story']['height'] );

		$this->assertSame( 1080, $this->llamar( 'format_dimensions', 'inventado' )['height'], 'desconocido → cuadrado' );
	}
}
