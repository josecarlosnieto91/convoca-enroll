<?php
/**
 * Piezas de arranque que quedaban sin medir: el CPT de evaluación, el comando de webhooks, la página de
 * inscripción y la instalación de las tablas de medios.
 *
 * Cuatro ficheros pequeños (`CPT_Evaluacion` 33 statements, `CLI_Webhooks` 26, `Pagina_Inscripcion` 25,
 * `Media_Installer` 24) que estaban al 0 %. Se agrupan aquí porque comparten una sola pregunta: **¿qué deja
 * cada uno montado en el sitio y qué hace cuando le falta un dato?**
 *
 * @package Convoca\Enroll\Tests
 */

namespace Convoca\Enroll\Tests;

use Convoca\Enroll\CLI_Webhooks;
use Convoca\Enroll\CPT_Evaluacion;
use Convoca\Enroll\Media\Media_Installer;
use Convoca\Enroll\Pagina_Inscripcion;
use PHPUnit\Framework\TestCase;

class PiezasDeArranqueTest extends TestCase {

	/**
	 * Prepara el caso.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_stores']        = array( 'options' => array(), 'results_mapa' => array() );
		$GLOBALS['_convoca_acciones'] = array();
		$GLOBALS['_wp_encolados']     = array();
		$GLOBALS['_wp_cli_salida']    = array();
		$GLOBALS['_wp_cli_comandos']  = array();
		$GLOBALS['_ddl']              = array();
		$GLOBALS['db_inserts']        = array();
		$GLOBALS['_bloques_pedidos']  = array();
		$GLOBALS['_convoca_tipos_registrados'] = array();
		$GLOBALS['_wp_shortcodes']    = array();
		$GLOBALS['bloques_render']    = '';
	}

	/**
	 * Limpia el caso.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_stores'] = array( 'options' => array() );
		unset( $GLOBALS['_convoca_acciones'], $GLOBALS['_wp_encolados'], $GLOBALS['_wp_cli_salida'], $GLOBALS['_wp_cli_comandos'], $GLOBALS['_ddl'], $GLOBALS['db_inserts'], $GLOBALS['_bloques_pedidos'], $GLOBALS['_convoca_tipos_registrados'], $GLOBALS['_wp_shortcodes'], $GLOBALS['bloques_render'] );
	}

	/* ── CPT de evaluación ─────────────────────────────── */

	/**
	 * El CPT se registra en `init`, cerrado al público y sin menú propio (vive dentro de Actividades).
	 */
	public function test_el_cpt_de_evaluacion(): void {
		CPT_Evaluacion::init();
		CPT_Evaluacion::register_cpt();

		$acciones = array_merge( (array) ( $GLOBALS['_wp_stores']['acciones'] ?? array() ), (array) ( $GLOBALS['_convoca_acciones'] ?? array() ) );
		$this->assertNotEmpty( $acciones['init'] ?? array() );

		$tipos = (array) ( $GLOBALS['_convoca_tipos_registrados'] ?? array() );
		$this->assertArrayHasKey( 'convoca_evaluacion', $tipos, 'el slug es convoca_evaluacion (enroll#47)' );

		$args = (array) $tipos['convoca_evaluacion'];
		$this->assertFalse( $args['public'], 'una evaluación no tiene página pública' );
		$this->assertFalse( $args['publicly_queryable'] );
		$this->assertFalse( $args['show_in_menu'], 'no cuelga del menú: se llega desde Actividades' );
		$this->assertTrue( $args['show_ui'] );
		$this->assertFalse( $args['rewrite'] );
		$this->assertSame( array( 'title', 'author' ), $args['supports'] );
	}

	/* ── Comando de webhooks ───────────────────────────── */

	/**
	 * Sin `--all` ni `--limit` no se hace nada: se explica qué falta.
	 */
	public function test_webhooks_sin_argumentos(): void {
		$comando = new CLI_Webhooks();

		$this->assertContains( 'conv webhooks', (array) ( $GLOBALS['_wp_cli_comandos'] ?? array() ), 'el comando está registrado' );

		$comando->retry_failed( array(), array() );

		$salida = implode( ' | ', (array) ( $GLOBALS['_wp_cli_salida'] ?? array() ) );
		$this->assertStringContainsString( 'especifica --all o un --limit', $salida );
		$this->assertSame( array(), (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ), 'no se toca la base sin saber cuántos' );
	}

	/**
	 * Si no hay fallidos, se dice y no se actualiza nada.
	 */
	public function test_webhooks_sin_fallidos(): void {
		$GLOBALS['_wp_stores']['col_cola'] = array( array() );

		( new CLI_Webhooks() )->retry_failed( array(), array( 'all' => true ) );

		$salida = implode( ' | ', (array) ( $GLOBALS['_wp_cli_salida'] ?? array() ) );
		$this->assertStringContainsString( 'No hay webhooks fallidos', $salida );
	}

	/**
	 * Con fallidos, se marcan a `pending` con sus reintentos a cero.
	 */
	public function test_webhooks_reintenta_los_fallidos(): void {
		$GLOBALS['_wp_stores']['col_cola'] = array( array( '4', '9' ) );

		( new CLI_Webhooks() )->retry_failed( array(), array( 'all' => true ) );

		$consultas = implode( "\n", (array) ( $GLOBALS['_wp_stores']['queries'] ?? array() ) );
		$this->assertStringContainsString( "status = 'pending'", $consultas );
		$this->assertStringContainsString( 'retries = 0', $consultas );
		$this->assertStringContainsString( 'IN (4,9)', $consultas, 'solo los fallidos' );
	}

	/**
	 * El `--limit` entra en la consulta preparado.
	 */
	public function test_webhooks_con_limite(): void {
		$GLOBALS['_wp_stores']['col_cola'] = array( array() );

		( new CLI_Webhooks() )->retry_failed( array(), array( 'limit' => '5' ) );

		$preparadas = implode( "\n", (array) ( $GLOBALS['db_prepares'] ?? array() ) );
		$this->assertStringContainsString( 'LIMIT %d', $preparadas, 'el límite va parametrizado' );
	}

	/* ── Página de inscripción ─────────────────────────── */

	/**
	 * El shortcode se engancha y la página pinta la intro, el enlace al panel y las actividades.
	 */
	public function test_la_pagina_de_inscripcion(): void {
		new Pagina_Inscripcion();
		$this->assertArrayHasKey( 'convoca_inscripcion_page', (array) $GLOBALS['_wp_shortcodes'] );

		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array(
			'texto_introduccion'  => "Primera línea\n\nSegunda",
			'url_panel_reservas'  => 'https://example.test/panel/',
		);
		$GLOBALS['bloques_render'] = '<div class="actividades">las de siempre</div>';

		$html = (string) ( new Pagina_Inscripcion() )->shortcode( array() );

		$this->assertStringContainsString( 'Primera línea', $html, 'la introducción que escribió el centro' );
		$this->assertStringContainsString( 'https://example.test/panel/', $html, 'y su enlace al panel de reservas' );
		$this->assertStringContainsString( 'las de siempre', $html, 'y las actividades, con el patrón del tema' );
		$this->assertContains( 'conv-public', (array) ( $GLOBALS['_wp_encolados']['styles'] ?? array() ) );
	}

	/**
	 * Sin texto de introducción no se pinta el hueco vacío, y sin URL propia se usa la de por defecto.
	 */
	public function test_la_pagina_sin_intro(): void {
		$GLOBALS['_wp_stores']['options']['convoca_enroll_settings'] = array();

		$html = (string) ( new Pagina_Inscripcion() )->shortcode( array() );

		$this->assertStringNotContainsString( 'conv-intro', $html, 'sin intro no queda un hueco' );
		$this->assertStringContainsString( 'panel-de-reservas', $html, 'y se ofrece el panel por defecto' );
	}

	/* ── Instalación de medios ─────────────────────────── */

	/**
	 * La siembra de plantillas de ejemplo: se hace una vez y con los datos del catálogo.
	 *
	 * EXCEPCIÓN ANOTADA: `Media_Installer::install()` no se puede ejecutar en la suite unitaria porque
	 * `create_tables()` hace `require_once ABSPATH . 'wp-admin/includes/upgrade.php'`, que no existe en el
	 * repo (igual que la migración 1.3.0). Lo que sí se mide es la siembra, que es lo que deja datos.
	 */
	public function test_la_siembra_de_plantillas(): void {
		$metodo = new \ReflectionMethod( Media_Installer::class, 'seed_default_templates' );
		$metodo->invoke( null );

		$inserts = (array) ( $GLOBALS['db_inserts'] ?? array() );
		$this->assertNotEmpty( $inserts, 'siembra las plantillas de ejemplo' );
		$this->assertStringContainsString( 'convoca_media_templates', (string) $inserts[0]['tabla'] );

		// La versión del esquema la apunta `install()` (bloqueada por el `require` de wp-admin), no la siembra.
		$this->assertGreaterThanOrEqual( 5, count( $inserts ), 'siembra el catálogo de plantillas de ejemplo' );
	}
}
