<?php
/**
 * Pantalla de ajustes del plugin (ocho pestañas).
 *
 * Es donde se configura todo lo que el resto del plugin da por hecho: email del administrador, normas que el socio
 * acepta al inscribirse, textos de protección de datos, plantillas de correo, recordatorios de evaluación y las
 * integraciones de Google. Lo que se prueba: que cada pestaña pinta **el ajuste guardado** (no el valor por
 * omisión) y que lo escapa, que una pestaña que no existe cae en General, y que sin el núcleo activo se avisa.
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\Admin_Settings;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/CPT_Actividad.php';
require_once dirname( __DIR__, 2 ) . '/includes/Email_Automation.php';
require_once dirname( __DIR__, 2 ) . '/admin/class-admin-settings.php';

/**
 * Ajustes.
 */
final class AdminSettingsRenderTest extends TestCase {

	/**
	 * Estado global previo.
	 *
	 * @var array
	 */
	private array $previo = array();

	/**
	 * Prepara el caso.
	 */
	/**
	 * `$wpdb` anterior.
	 *
	 * @var mixed
	 */
	private $wpdb_previo = null;

	protected function setUp(): void {
		$this->wpdb_previo = $GLOBALS['wpdb'] ?? null;
		$this->previo          = $GLOBALS['_wp_stores'] ?? array();
		$GLOBALS['_wp_stores'] = array(
			'options'    => array(),
			'post_meta'  => array(),
			'transients' => array(),
			'posts'      => array(),
			'usuarios'   => array(),
		);
		$GLOBALS['plugins_inactivos'] = array();
		$_GET                         = array();
		$_POST                        = array();
		if ( ! defined( 'CONVOCA_IMAGES_URL' ) ) {
			define( 'CONVOCA_IMAGES_URL', 'https://example.test/plugin/assets/images/' );
		}
	}

	/**
	 * Devuelve el almacén a como estaba.
	 */
	protected function tearDown(): void {
		if ( null !== $this->wpdb_previo ) {
			$GLOBALS['wpdb'] = $this->wpdb_previo;
		}
		$GLOBALS['_wp_stores']         = $this->previo;
		$GLOBALS['plugins_inactivos']  = array();
		$_GET                          = array();
		$_POST                 = array();
	}

	/**
	 * Pinta la pestaña pedida.
	 *
	 * @param string $tab   Pestaña.
	 * @param array  $ajustes Ajustes guardados.
	 * @return string
	 */
	private function pintar( string $tab, array $ajustes = array() ): string {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = $ajustes;
		$_GET['tab'] = $tab;

		ob_start();
		try {
			Admin_Settings::render();
		} finally {
			$html = (string) ob_get_clean();
		}

		return $html;
	}

	/**
	 * Las ocho pestañas están y la activa se marca.
	 */
	public function test_las_pestanas(): void {
		$html = $this->pintar( 'general' );

		foreach ( array( 'General', 'Normas de inscripción', 'Protección de datos', 'Emails', 'Recordatorios de evaluación', 'Google Photos', 'Google Calendar', 'Estado' ) as $etiqueta ) {
			$this->assertStringContainsString( $etiqueta, $html, 'pestaña: ' . $etiqueta );
		}
		$this->assertStringContainsString( 'nav-tab-active', $html );
		$this->assertStringContainsString( 'tab=normas', $html, 'enlace a otra pestaña' );
		$this->assertStringContainsString( 'convoca_enroll_settings_nonce', $html, 'el formulario lleva nonce' );
	}

	/**
	 * General: los valores guardados salen en el formulario, y el email escapado.
	 */
	public function test_general(): void {
		$html = $this->pintar(
			'general',
			array(
				'admin_email'     => 'ajustes@example.test',
				'plazas_por_defecto' => '35',
				'nombre_contacto' => 'A" onclick="alert(1)',
			)
		);

		$this->assertStringContainsString( 'ajustes@example.test', $html );
		$this->assertStringContainsString( 'value="35"', $html );
		$this->assertStringNotContainsString( 'onclick="alert(1)"', $html, 'el valor va escapado' );
	}

	/**
	 * Normas: el texto guardado sale en su área de texto, sin romper el HTML.
	 */
	public function test_normas(): void {
		$html = $this->pintar(
			'normas',
			array(
				'normas_inscripcion' => "1. Llegar puntual.\n2. Avisar si no vienes.",
				'texto_introduccion' => 'Gracias por apuntarte.',
			)
		);

		$this->assertStringContainsString( 'Llegar puntual', $html );
		$this->assertStringContainsString( 'Avisar si no vienes', $html );
		$this->assertStringContainsString( 'textarea', $html );
	}

	/**
	 * Protección de datos: su texto y su casilla.
	 */
	public function test_rgpd(): void {
		$html = $this->pintar(
			'rgpd',
			array(
				'url_privacidad'        => 'https://example.test/privacidad',
				'url_proteccion_datos'  => 'https://example.test/datos',
			)
		);

		$this->assertStringContainsString( 'https://example.test/privacidad', $html );
		$this->assertStringContainsString( 'https://example.test/datos', $html );
	}

	/**
	 * Una pestaña que no existe cae en General, sin pantalla en blanco.
	 */
	public function test_pestana_desconocida_cae_en_general(): void {
		$html = $this->pintar( 'inventada' );

		$this->assertStringContainsString( 'Configuración General', $html );
	}

	/**
	 * Sin el núcleo activo se avisa (no se deja al usuario con funciones que no van).
	 */
	public function test_sin_nucleo_avisa(): void {
		$GLOBALS['_wp_stores']['post_types']                 = array( 'actividad' => true );
		$GLOBALS['plugins_inactivos']['convoca-core/convoca-core.php'] = true;

		$html = $this->pintar( 'general' );

		$this->assertStringContainsString( 'Convoca Core no está activo', $html );
	}

	/**
	 * Recordatorios de evaluación: activo, días, asunto y cuerpo; y si no está programado, se dice.
	 */
	public function test_recordatorios_de_evaluacion(): void {
		$html = $this->pintar(
			'eval_reminders',
			array(
				'eval_reminder' => array(
					'active'  => 1,
					'days'    => '5',
					'subject' => '¿Qué tal {nombre_actividad}?',
					'body'    => 'Cuéntanoslo aquí: {link_evaluacion}',
					'cc'      => 'equipo@example.test',
				),
			)
		);

		$this->assertStringContainsString( '¿Qué tal {nombre_actividad}?', $html );
		$this->assertStringContainsString( 'Cuéntanoslo aquí: {link_evaluacion}', $html );
		$this->assertStringContainsString( 'equipo@example.test', $html );
		$this->assertStringContainsString( 'No programado', $html, 'sin cron, se dice' );
		$this->assertStringContainsString( 'checked', $html, 'el recordatorio está activo' );
	}

	/**
	 * Google Photos: sin credenciales se explica cómo obtenerlas; con ellas, salen sus datos.
	 */
	public function test_google_photos(): void {
		$html = $this->pintar(
			'google_photos',
			array(
				'google_photos_enabled'       => 1,
				'google_photos_client_id'     => 'cliente-photos',
				'google_photos_album_prefix'  => 'Actividad',
			)
		);

		$this->assertStringContainsString( 'cliente-photos', $html );
		$this->assertStringContainsString( 'Actividad', $html );
	}

	/**
	 * Google Calendar: el calendario y la sincronización automática.
	 */
	public function test_google_calendar(): void {
		$html = $this->pintar(
			'google_calendar',
			array(
				'google_calendar_id'        => 'calendario@group.calendar.example.test',
				'google_calendar_auto_sync' => 1,
			)
		);

		$this->assertStringContainsString( 'calendario@group.calendar.example.test', $html );
		$this->assertStringContainsString( 'checked', $html, 'la sincronización está activada' );
	}

	/**
	 * Estado: los diagnósticos que ve el admin, con su gravedad. Y la caché que evita repetirlos.
	 */
	public function test_estado_y_diagnosticos(): void {
		$bloques = Admin_Settings::aportar_checks( array(), true );
		$checks  = $bloques['enroll'];

		$this->assertNotEmpty( $checks, 'hay comprobaciones' );
		$titulos = array_column( $checks, 'title' );
		$this->assertContains( 'Plugin: Convoca Core', $titulos );

		// El núcleo está (es el doble del arnés): sale en verde. Los otros dos no están: avisan.
		$por_titulo = array_column( $checks, null, 'title' );
		$this->assertSame( 'ok', $por_titulo['Plugin: Convoca Core']['status'] );
		$this->assertSame( 'warning', $por_titulo['Plugin: Convoca Members']['status'], 'no está instalado' );
		$this->assertSame( 'warning', $por_titulo['Plugin: Convoca Gateway']['status'] );
		$this->assertNotEmpty( $por_titulo['Plugin: Convoca Members']['fix'], 'y dice cómo arreglarlo' );

		// La segunda llamada sin `force` usa la caché; con `force` vuelve a medir.
		$GLOBALS['_wp_stores']['options']['convoca_enroll_diagnostic_cache'] = array(
			'expires' => time() + 600,
			'results' => array( array( 'title' => 'Cacheado', 'status' => 'ok', 'message' => '', 'fix' => '' ) ),
		);
		$cacheado = Admin_Settings::aportar_checks( array() );
		$this->assertSame( 'Cacheado', $cacheado['enroll'][0]['title'], 'se reutiliza el diagnóstico' );

		$fresco = Admin_Settings::aportar_checks( array(), true );
		$this->assertSame( 'Plugin: Convoca Core', $fresco['enroll'][0]['title'], 'con force se vuelve a medir' );

		$html = $this->pintar( 'status' );
		$this->assertStringContainsString( 'Estado', $html );
	}
}
