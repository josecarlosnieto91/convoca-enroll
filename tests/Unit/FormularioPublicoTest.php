<?php
/**
 * Formulario público: tarjeta legible (#58), aviso de socio traducible (#59) y DNI configurable (#64).
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/Utils.php';

/**
 * Formulario y tarjeta.
 */
final class FormularioPublicoTest extends TestCase {

	/**
	 * Estado previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el almacén.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array( 'options' => array(), 'post_meta' => array() );
	}

	/**
	 * Restaura.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = $this->previo;
	}

	/**
	 * Raíz del plugin.
	 *
	 * @return string
	 */
	private function raiz(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Luminancia relativa de un color #rrggbb (WCAG 2.1).
	 *
	 * @param string $hex Color.
	 * @return float
	 */
	private function luminancia( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		$rgb = array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);

		$lineal = array_map(
			static function ( float $c ): float {
				return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			},
			$rgb
		);

		return 0.2126 * $lineal[0] + 0.7152 * $lineal[1] + 0.0722 * $lineal[2];
	}

	/**
	 * Contraste WCAG entre dos colores.
	 *
	 * @param string $a Color.
	 * @param string $b Color.
	 * @return float
	 */
	private function contraste( string $a, string $b ): float {
		$la = $this->luminancia( $a );
		$lb = $this->luminancia( $b );
		$hi = max( $la, $lb );
		$lo = min( $la, $lb );

		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	/**
	 * Valor por defecto de una variable de la tarjeta en el CSS.
	 *
	 * @param string $css   CSS.
	 * @param string $nombre Variable.
	 * @return string
	 */
	private function variable( string $css, string $nombre ): string {
		$this->assertMatchesRegularExpression( '/' . preg_quote( $nombre, '/' ) . ':\s*(#[0-9a-fA-F]{6})/', $css, 'falta ' . $nombre );

		preg_match( '/' . preg_quote( $nombre, '/' ) . ':\s*(#[0-9a-fA-F]{6})/', $css, $m );

		return $m[1];
	}

	/**
	 * #58: los textos de la tarjeta cumplen contraste AA contra su fondo, con los valores por defecto.
	 */
	public function test_los_textos_de_la_tarjeta_cumplen_contraste(): void {
		$css = (string) file_get_contents( $this->raiz() . '/assets/css/convoca-enroll-public.css' );

		// Los valores se leen del bloque de la tarjeta, no del `:root`: el componente sobrescribe los
		// tokens globales a propósito (el título va en claro sobre el fondo oscuro).
		$desde = (int) strpos( $css, '.conv-enroll-block {' );
		$bloque = substr( $css, $desde, (int) strpos( $css, '}', $desde ) - $desde );

		$inicio = $this->variable( $bloque, '--conv-card-bg-start' );
		$fin    = $this->variable( $bloque, '--conv-card-bg-end' );
		$texto  = $this->variable( $bloque, '--conv-card-fg' );
		$titulo = $this->variable( $bloque, '--conv-accent' );

		foreach ( array( $inicio, $fin ) as $fondo ) {
			$this->assertGreaterThanOrEqual( 4.5, $this->contraste( $texto, $fondo ), "texto sobre {$fondo}" );
			$this->assertGreaterThanOrEqual( 4.5, $this->contraste( $titulo, $fondo ), "título sobre {$fondo}" );
		}
	}

	/**
	 * #58: el componente ya no depende de los presets de un tema ajeno.
	 */
	public function test_la_tarjeta_no_depende_de_presets_del_tema(): void {
		$css = (string) file_get_contents( $this->raiz() . '/assets/css/convoca-enroll-public.css' );

		$this->assertStringNotContainsString( '--wp--preset--color', $css, 'la paleta del producto no puede ser la de un cliente' );
	}

	/**
	 * #58: la especificidad del título gana a `.entry-content h3` del tema.
	 */
	public function test_el_titulo_de_la_tarjeta_gana_en_especificidad(): void {
		$css = (string) file_get_contents( $this->raiz() . '/assets/css/convoca-enroll-public.css' );

		$this->assertStringContainsString( '.convoca-activity-card .conv-activity-title', $css, 'clase + clase (0,2,0) gana a `.entry-content h3` (0,1,1)' );
	}

	/**
	 * #59: el JS no lleva rutas ni textos de un sitio concreto.
	 */
	public function test_el_aviso_de_socio_no_esta_escrito_en_el_js(): void {
		$js = (string) file_get_contents( $this->raiz() . '/assets/js/convoca-enroll-public.js' );

		$this->assertStringNotContainsString( '/acceder/', $js, 'la ruta de acceso es del sitio, no del producto' );
		$this->assertStringNotContainsString( 'Trasgu', $js, 'la etiqueta se toma de la configuración' );
		$this->assertStringNotContainsString( 'aportación reducida', $js, 'no se puede prometer en una actividad gratuita' );
		$this->assertStringContainsString( 'i18n', $js, 'el texto lo pone el servidor' );
	}

	/**
	 * #59: el servidor localiza los textos y usa las URLs de WordPress.
	 */
	public function test_el_servidor_localiza_el_aviso(): void {
		$php = (string) file_get_contents( $this->raiz() . '/public/class-form-inscripcion.php' );

		$this->assertStringContainsString( "'i18n'", $php );
		$this->assertStringContainsString( 'wp_login_url(', $php );
		$this->assertStringContainsString( 'wp_logout_url(', $php );
		$this->assertStringContainsString( "get_aportacion_label( 'trasgu' )", $php );
	}

	/**
	 * #64: por defecto el DNI sigue siendo obligatorio (no se cambia el comportamiento de los sitios).
	 */
	public function test_el_dni_es_obligatorio_por_defecto(): void {
		$this->assertSame( 'obligatorio', \Convoca\Enroll\Utils::dni_modo( 61 ) );
	}

	/**
	 * #64: el ajuste global y la actividad mandan en ese orden.
	 */
	public function test_el_modo_del_dni_se_hereda_y_se_puede_cambiar(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array( 'dni_modo' => 'opcional' );
		$this->assertSame( 'opcional', \Convoca\Enroll\Utils::dni_modo( 61 ), 'sin valor en la actividad manda el ajuste' );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_dni_modo'] = 'no';
		$this->assertSame( 'no', \Convoca\Enroll\Utils::dni_modo( 61 ), 'la actividad manda sobre el ajuste' );

		$GLOBALS['_wp_stores']['post_meta'][61]['_convoca_dni_modo'] = 'heredar';
		$this->assertSame( 'opcional', \Convoca\Enroll\Utils::dni_modo( 61 ), '«heredar» vuelve al ajuste' );
	}

	/**
	 * #64: con «no pedirlo» no se guarda el documento.
	 */
	public function test_con_no_pedirlo_el_dni_no_se_guarda(): void {
		$motor = (string) file_get_contents( $this->raiz() . '/includes/Motor_Inscripcion.php' );

		$this->assertStringContainsString( "'no' === \\Convoca\\Enroll\\Utils::dni_modo( \$actividad_id )", $motor, 'el motor debe vaciar el DNI cuando no se pide' );
	}
}
