<?php
/**
 * Pantalla de check-in del panel.
 *
 * Es la que abre el monitor de pie, con prisa, delante de la puerta: tiene que salir **la actividad de hoy** sin tocar
 * nada, y solo las que le corresponden. Se mide la elección por defecto (hoy → la siguiente → la última que hubo), el
 * permiso sobre la actividad ajena y el listado con su búsqueda.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Checkin;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/CPT_Inscripcion.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-checkin.php';

/**
 * Check-in.
 */
final class AdminCheckinTest extends TestCase {

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
			'options'      => array(),
			'post_meta'    => array(),
			'transients'   => array(),
			'posts'        => array(),
			'usuarios'     => array(),
			'get_posts_cola' => array(),
		);
		$GLOBALS['convoca_test_caps']           = array( 'manage_options' => true );
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_cola'] = array();
		$GLOBALS['convoca_test_pro']            = array();
		$_GET                                   = array();
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores']                  = $this->previo;
		$GLOBALS['convoca_test_caps']           = array();
		$GLOBALS['convoca_test_posts']          = array();
		$GLOBALS['convoca_test_get_posts_cola'] = null;
		$GLOBALS['convoca_test_pro']            = array();
		$_GET                                   = array();
	}

	/**
	 * Siembra una actividad con su fecha.
	 *
	 * @param int    $id    Id.
	 * @param string $fecha Fecha (Y-m-d).
	 * @param string $titulo Título.
	 * @return \WP_Post
	 */
	private function actividad( int $id, string $fecha, string $titulo = '' ): \WP_Post {
		$post                        = new \WP_Post();
		$post->ID                    = $id;
		$post->post_type             = 'actividad';
		$post->post_title            = $titulo ?: 'Actividad ' . $id;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array( '_convoca_fecha_inicio' => $fecha );

		return $post;
	}

	/**
	 * Inscripción sembrada.
	 *
	 * @param int    $id     Id.
	 * @param string $nombre Nombre.
	 * @param string $email  Correo.
	 * @return \WP_Post
	 */
	private function inscripcion( int $id, string $nombre = 'Ana', string $email = 'ana@example.test' ): \WP_Post {
		$post                        = new \WP_Post();
		$post->ID                    = $id;
		$post->post_type             = 'inscripcion';
		$post->post_title            = $nombre;
		$GLOBALS['_wp_stores']['posts'][ $id ] = $post;
		$GLOBALS['_wp_stores']['post_meta'][ $id ] = array(
			'_convoca_nombre'       => $nombre,
			'_convoca_email'        => $email,
			'_convoca_estado'       => 'confirmada',
			'_convoca_asistencia'   => 'no_registrada',
		);

		return $post;
	}

	/**
	 * Pinta la pantalla.
	 *
	 * @return string
	 */
	private function pintar(): string {
		ob_start();
		try {
			Admin_Checkin::render();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Elige la actividad de hoy sin que nadie la seleccione.
	 */
	public function test_elige_la_de_hoy(): void {
		$hoy     = current_time( 'Y-m-d' );
		$manana  = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		$pasada  = gmdate( 'Y-m-d', strtotime( '-3 days' ) );

		$deHoy   = $this->actividad( 1, $hoy, 'La de hoy' );
		$futura  = $this->actividad( 2, $manana, 'La de mañana' );
		$vieja   = $this->actividad( 3, $pasada, 'La de la semana pasada' );

		$GLOBALS['convoca_test_get_posts_cola'] = array( array( $futura, $deHoy, $vieja ), array() );

		$html = $this->pintar();

		$this->assertStringContainsString( 'Check-in de Actividades', $html );
		$this->assertMatchesRegularExpression( '/value="1"\s+selected/', $html, 'la de hoy sale seleccionada' );
	}

	/**
	 * Si hoy no hay ninguna, la siguiente más cercana.
	 */
	public function test_sin_actividad_hoy_elige_la_siguiente(): void {
		$manana    = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		$masTarde  = gmdate( 'Y-m-d', strtotime( '+10 days' ) );

		$cercana  = $this->actividad( 5, $manana, 'Mañana' );
		$lejana   = $this->actividad( 6, $masTarde, 'En diez días' );

		$GLOBALS['convoca_test_get_posts_cola'] = array( array( $lejana, $cercana ), array() );

		$html = $this->pintar();

		$this->assertMatchesRegularExpression( '/value="5"\s+selected/', $html, 'la más cercana primero' );
	}

	/**
	 * Si no hay futuras, la última que hubo.
	 */
	public function test_sin_futuras_elige_la_ultima(): void {
		$ayer      = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
		$antier    = gmdate( 'Y-m-d', strtotime( '-3 days' ) );

		$reciente = $this->actividad( 7, $ayer, 'Ayer' );
		$vieja    = $this->actividad( 8, $antier, 'Hace tres días' );

		$GLOBALS['convoca_test_get_posts_cola'] = array( array( $vieja, $reciente ), array() );

		$html = $this->pintar();

		$this->assertMatchesRegularExpression( '/value="7"\s+selected/', $html, 'la más reciente de las pasadas' );
	}

	/**
	 * Una actividad ajena no se puede mirar.
	 */
	public function test_actividad_ajena(): void {
		$this->actividad( 9, current_time( 'Y-m-d' ) );
		$GLOBALS['convoca_test_caps']['manage_options'] = false;
		$GLOBALS['_wp_stores']['options']['convoca_enroll_delegados_actividades'] = array( '1' => array( 10 ) );
		$_GET['actividad_id'] = '9';

		$capturado = null;
		try {
			Admin_Checkin::render();
		} catch ( \Convoca_Json_Cortado $cortado ) {
			$capturado = $cortado->respuesta;
		}

		$this->assertNotNull( $capturado, 'se corta' );
		$this->assertStringContainsString( 'permiso', (string) ( $capturado['die'] ?? $capturado ) );
	}

	/**
	 * Con la actividad elegida, se listan sus inscripciones.
	 */
	public function test_lista_las_inscripciones(): void {
		$this->actividad( 11, current_time( 'Y-m-d' ), 'Ruta de otoño' );
		$ana = $this->inscripcion( 20, 'Ana', 'ana@example.test' );
		$luis = $this->inscripcion( 21, 'Luis', 'luis@example.test' );

		$_GET['actividad_id'] = '11';
		$GLOBALS['convoca_test_get_posts_cola'] = array( array( $GLOBALS['_wp_stores']['posts'][11] ), array( $ana, $luis ) );

		$html = $this->pintar();

		$this->assertStringContainsString( 'Ana', $html );
		$this->assertStringContainsString( 'Luis', $html );
	}

	/**
	 * Sin licencia PRO se avisa de la función bloqueada, no se esconde.
	 */
	public function test_el_aviso_de_licencia(): void {
		$this->actividad( 12, current_time( 'Y-m-d' ) );
		$GLOBALS['convoca_test_get_posts_cola'] = array( array( $GLOBALS['_wp_stores']['posts'][12] ), array() );

		$this->assertStringContainsString( 'PRO', $this->pintar() );

		$GLOBALS['convoca_test_pro']['pwa_checkin'] = true;
		$this->assertStringNotContainsString( 'funcionalidad PRO', $this->pintar(), 'con licencia no se avisa' );
	}
}
