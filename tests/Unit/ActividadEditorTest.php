<?php
/**
 * La ficha de la actividad: lo que se pinta en el editor y lo que se guarda.
 *
 * El guardado es el punto donde una actividad puede quedar con datos imposibles (fin antes del inicio, más plazas
 * disponibles que totales, un álbum de Google activado sin configurar). Se comprueba que **corrige y avisa**, no que
 * escriba y calle.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CPT_Actividad;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-client.php';
require_once dirname( __DIR__, 2 ) . '/tests/dobles/google-sdk.php';
require_once dirname( __DIR__ ) . '/../includes/Google_Photos.php';
require_once dirname( __DIR__ ) . '/../includes/Google_Calendar.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';

/**
 * Editor de actividades.
 */
final class ActividadEditorTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Post previo.
	 *
	 * @var array
	 */
	private array $post_previo = array();

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$this->post_previo     = $_POST;
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['convoca_test_caps']     = array( 'edit_post' => true );
		$GLOBALS['nonce_invalido']        = false;
		$GLOBALS['convoca_test_tipos']    = array( 61 => 'actividad' );
		$GLOBALS['convoca_test_users_lista'] = array(
			(object) array( 'ID' => 7, 'display_name' => 'Marta', 'user_email' => 'marta@example.test' ),
			(object) array( 'ID' => 9, 'display_name' => 'Luis', 'user_email' => 'luis@example.test' ),
		);
		$GLOBALS['convoca_test_is_admin'] = false;
		$_POST                            = array();

		$actividad                          = new \WP_Post();
		$actividad->ID                      = 61;
		$actividad->post_type               = 'actividad';
		$actividad->post_title              = 'Ruta de otoño';
		$GLOBALS['_wp_stores']['posts'][61] = $actividad;
	}

	/**
	 * Devuelve el estado a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']          = $this->previo;
		$GLOBALS['convoca_test_caps']   = array();
		$GLOBALS['nonce_invalido']      = false;
		$GLOBALS['convoca_test_tipos']  = array();
		$GLOBALS['convoca_test_is_admin'] = false;
		$_POST                          = $this->post_previo;
	}

	/**
	 * Rellena el formulario con los campos del editor.
	 *
	 * @param array $campos Valores por campo.
	 */
	private function formulario( array $campos ): void {
		$_POST = array( 'convoca_enroll_actividad_nonce' => 'nonce-bueno' );
		foreach ( $campos as $k => $v ) {
			// Lo que EMITE el formulario: `conv_enroll_<clave>` (enroll#38).
			$_POST[ 'conv_enroll_' . $k ] = $v;
		}
	}

	/**
	 * El aviso que va a la redirección, si lo hay.
	 *
	 * @return string|null
	 */
	private function aviso(): ?string {
		$url = apply_filters( 'redirect_post_location', 'http://panel.example.test/post.php?post=61' );
		if ( ! is_string( $url ) ) {
			return null;
		}
		parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );

		return $q['message'] ?? null;
	}

	/**
	 * El editor pinta el nonce y los valores guardados.
	 */
	public function test_el_metabox_se_pinta(): void {
		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio' => '2026-11-14 09:30',
			'_convoca_ubicacion'    => 'Plaza del Ayuntamiento',
			'_convoca_responsables' => '9',
		);

		ob_start();
		( new CPT_Actividad() )->render_metabox( $GLOBALS['_wp_stores']['posts'][61] );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'convoca_enroll_actividad_nonce', $html, 'sin nonce no hay guardado' );
		$this->assertStringContainsString( 'value="2026-11-14 09:30"', $html );
		$this->assertStringContainsString( 'value="Plaza del Ayuntamiento"', $html );
		$this->assertStringContainsString( 'Luis (luis@example.test)', $html );
		$this->assertMatchesRegularExpression( '/value="9"\s+selected/', $html, 'el responsable guardado sale marcado' );
		$this->assertDoesNotMatchRegularExpression( '/value="7"\s+selected/', $html, 'y el que no está, no' );
	}

	/**
	 * Sin nonce no se guarda nada: es lo que impide que un formulario ajeno escriba la ficha.
	 */
	public function test_sin_nonce_no_se_guarda(): void {
		$this->formulario( array( 'ubicacion' => 'Sevilla' ) );
		unset( $_POST['convoca_enroll_actividad_nonce'] );

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertArrayNotHasKey( '_convoca_ubicacion', $GLOBALS['_wp_stores']['post_meta'][61] ?? array() );
	}

	/**
	 * Con un nonce que no vale, tampoco.
	 */
	public function test_nonce_invalido(): void {
		$this->formulario( array( 'ubicacion' => 'Sevilla' ) );
		$GLOBALS['nonce_invalido'] = true;

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertArrayNotHasKey( '_convoca_ubicacion', $GLOBALS['_wp_stores']['post_meta'][61] ?? array() );
	}

	/**
	 * Sin permiso para editar ese post, tampoco.
	 */
	public function test_sin_permiso(): void {
		$this->formulario( array( 'ubicacion' => 'Sevilla' ) );
		$GLOBALS['convoca_test_caps']['edit_post'] = false;

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertArrayNotHasKey( '_convoca_ubicacion', $GLOBALS['_wp_stores']['post_meta'][61] ?? array() );
	}

	/**
	 * Guardado normal: fechas sin la `T` del navegador, precios con coma y responsables unidos.
	 */
	public function test_guarda_los_campos(): void {
		$this->formulario(
			array(
				'fecha_inicio'      => '2026-11-14T09:30',
				'fecha_fin'         => '2026-11-14T13:00',
				'horas_actividad'   => '3,5',
				'precio_socio'      => '12,50',
				'plazas_totales'    => '20',
				'plazas_disponibles'=> '20',
				'ubicacion'         => '  Plaza Mayor  ',
				'responsables'      => array( '7', '9' ),
			)
		);

		( new CPT_Actividad() )->save_metabox( 61 );

		$m = $GLOBALS['_wp_stores']['post_meta'][61];
		$this->assertSame( '2026-11-14 09:30', $m['_convoca_fecha_inicio'] );
		$this->assertSame( 3.5, (float) $m['_convoca_horas_actividad'] );
		$this->assertSame( 12.5, (float) $m['_convoca_precio_socio'] );
		$this->assertSame( 'Plaza Mayor', $m['_convoca_ubicacion'] );
		$this->assertSame( '7,9', $m['_convoca_responsables'] );
	}

	/**
	 * Fin antes que inicio: se corrige a la fecha de inicio **y se avisa**.
	 */
	public function test_fin_antes_del_inicio(): void {
		$this->formulario(
			array(
				'fecha_inicio' => '2026-11-14T09:30',
				'fecha_fin'    => '2026-11-13T09:30',
			)
		);

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertSame( '2026-11-14 09:30', $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_fecha_fin'] );
		$this->assertSame( 'convoca_enroll_date_error', $this->aviso() );
	}

	/**
	 * Si solo se dan las plazas totales, las disponibles son las mismas.
	 */
	public function test_plazas_disponibles_desde_totales(): void {
		$this->formulario( array( 'plazas_totales' => '20' ) );

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertSame( 20, (int) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_plazas_disponibles'] );
	}

	/**
	 * Y al revés (defensivo): sin totales, se toman las disponibles.
	 */
	public function test_plazas_totales_desde_disponibles(): void {
		$this->formulario( array( 'plazas_disponibles' => '8' ) );

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertSame( 8, (int) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_plazas_totales'] );
	}

	/**
	 * Más disponibles que totales: se recortan y se avisa.
	 */
	public function test_no_puede_haber_mas_disponibles_que_totales(): void {
		$this->formulario(
			array(
				'plazas_totales'     => '10',
				'plazas_disponibles' => '15',
			)
		);

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertSame( 10, (int) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_plazas_disponibles'] );
		$this->assertSame( 'convoca_enroll_plazas_adjusted', $this->aviso() );
	}

	/**
	 * Álbum de Google activado sin configurar: se apaga y se avisa, no se queda mintiendo.
	 */
	public function test_album_de_google_sin_configurar(): void {
		$this->formulario( array( 'google_create_album' => '1' ) );
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array();

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertSame( 0, (int) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_create_album'] );
		$this->assertSame( 'convoca_enroll_google_photos_error', $this->aviso() );
	}

	/**
	 * Calendario igual: sin configurar, no se promete sincronización.
	 */
	public function test_calendario_sin_configurar(): void {
		$this->formulario( array( 'google_calendar_sync' => '1' ) );
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array();

		( new CPT_Actividad() )->save_metabox( 61 );

		$this->assertSame( 0, (int) $GLOBALS['_wp_stores']['post_meta'][61]['_convoca_google_calendar_sync'] );
		$this->assertSame( 'convoca_enroll_google_calendar_error', $this->aviso() );
	}

	/**
	 * Los marcadores de la ficha: fecha legible, lugar, plazas y precio.
	 */
	public function test_los_marcadores(): void {
		global $post;
		$post = $GLOBALS['_wp_stores']['posts'][61];

		$GLOBALS['_wp_stores']['post_meta'][61] = array(
			'_convoca_fecha_inicio'       => '2026-11-14 09:30:00',
			'_convoca_ubicacion'          => 'Plaza Mayor',
			'_convoca_precio'             => '12.5',
			'_convoca_plazas_disponibles' => '7',
			'_convoca_plazas_totales'     => '20',
		);

		$html = ( new CPT_Actividad() )->render_activity_placeholders(
			'%%FECHA_INICIO%% · %%LUGAR%% · %%PRECIO%% · %%PLAZAS%%',
			null
		);

		$this->assertStringContainsString( '14 de noviembre del 2026', $html );
		$this->assertStringContainsString( 'Plaza Mayor', $html );
		$this->assertStringContainsString( '12,50 €', $html );
		$this->assertStringContainsString( '7 / 20', $html );

		$post = null;
	}

	/**
	 * Sin precio guardado, «Gratis».
	 */
	public function test_sin_precio_es_gratis(): void {
		global $post;
		$post = $GLOBALS['_wp_stores']['posts'][61];
		$GLOBALS['_wp_stores']['post_meta'][61] = array( '_convoca_ubicacion' => 'Plaza Mayor' );

		$html = ( new CPT_Actividad() )->render_activity_placeholders( '%%PRECIO%%', null );

		$this->assertSame( 'Gratis', $html );
		$post = null;
	}

	/**
	 * Un texto sin marcadores no se toca (ni se lee la base).
	 */
	public function test_sin_marcadores_no_toca_nada(): void {
		$this->assertSame( 'Hola', ( new CPT_Actividad() )->render_activity_placeholders( 'Hola', null ) );
	}

	/**
	 * Fuera de una actividad no se sustituye nada.
	 */
	public function test_fuera_de_una_actividad(): void {
		global $post;
		$post = $GLOBALS['_wp_stores']['posts'][61];
		$GLOBALS['convoca_test_tipos'][61] = 'page';

		$this->assertSame( '%%LUGAR%%', ( new CPT_Actividad() )->render_activity_placeholders( '%%LUGAR%%', null ) );
		$post = null;
	}

	/**
	 * El archivo público solo enseña lo que aún no ha pasado, ordenado por fecha.
	 */
	public function test_el_archivo_solo_ensena_lo_que_viene(): void {
		$GLOBALS['convoca_test_is_admin'] = false;
		$consulta = new ConsultaFalsa( true, true );

		( new CPT_Actividad() )->archive_future_only( $consulta );

		$this->assertSame( '_convoca_fecha_inicio', $consulta->valores['meta_key'] );
		$this->assertSame( '>=', $consulta->valores['meta_compare'] );
		$this->assertSame( gmdate( 'Y-m-d' ), $consulta->valores['meta_value'] );
		$this->assertSame( 'ASC', $consulta->valores['order'] );
	}

	/**
	 * En el panel no se filtra: allí hay que poder ver las pasadas.
	 */
	public function test_en_el_panel_no_se_filtra(): void {
		$GLOBALS['convoca_test_is_admin'] = true;
		$consulta = new ConsultaFalsa( true, true );

		( new CPT_Actividad() )->archive_future_only( $consulta );

		$this->assertSame( array(), $consulta->valores );
	}

	/**
	 * Y una consulta que no es el archivo principal, tampoco.
	 */
	public function test_otra_consulta_no_se_toca(): void {
		$consulta = new ConsultaFalsa( false, true );

		( new CPT_Actividad() )->archive_future_only( $consulta );

		$this->assertSame( array(), $consulta->valores );
	}
}

/**
 * Consulta mínima para el filtro del archivo.
 */
final class ConsultaFalsa {

	/**
	 * Valores fijados.
	 *
	 * @var array
	 */
	public array $valores = array();

	/**
	 * ¿Es el archivo?
	 *
	 * @var bool
	 */
	private bool $archivo;

	/**
	 * ¿Es la consulta principal?
	 *
	 * @var bool
	 */
	private bool $principal;

	/**
	 * Constructor.
	 *
	 * @param bool $archivo   ¿Archivo?
	 * @param bool $principal ¿Principal?
	 */
	public function __construct( bool $archivo, bool $principal ) {
		$this->archivo   = $archivo;
		$this->principal = $principal;
	}

	/**
	 * ¿Archivo de actividades?
	 *
	 * @param string $tipo Tipo.
	 * @return bool
	 */
	public function is_post_type_archive( $tipo = '' ): bool {
		return $this->archivo && 'actividad' === $tipo;
	}

	/**
	 * ¿Consulta principal?
	 *
	 * @return bool
	 */
	public function is_main_query(): bool {
		return $this->principal;
	}

	/**
	 * Fija un valor.
	 *
	 * @param string $k Clave.
	 * @param mixed  $v Valor.
	 */
	public function set( $k, $v ): void {
		$this->valores[ $k ] = $v;
	}
}
