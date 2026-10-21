<?php
/**
 * Bootstrap de la suite de INTEGRACIÓN de convoca-enroll (tests/Integration).
 *
 * Qué cambia respecto al anterior (21-10-2026): los casos ya NO extienden `\WP_UnitTestCase`, así que
 * este bootstrap no necesita `wp-phpunit` ni los polyfills. Eso es lo que permite subir a PHPUnit 10:
 * `wp-phpunit` llama a `PHPUnit\Util\Test::parseTestMethodAnnotations()` y a `getName()`, eliminados en
 * 10.x, y su caso base reventaba 29 de los 39 tests.
 *
 * Lo que sí se conserva es la **biblioteca de pruebas de WordPress** (la que instala WordPress en la BD
 * de pruebas y arranca el sitio), que es independiente de PHPUnit: viene en `WP_TESTS_DIR`, la deja el
 * montaje del entorno (`bin/instalar-entorno-pruebas.sh`) y no toca ninguna clase de PHPUnit.
 *
 * Dos caminos, y ninguno miente:
 *   1. Hay entorno (WP_TESTS_DIR + wp-tests-config.php + WordPress): se instala/arranca WordPress, se
 *      carga el plugin y se ejecuta su activación —ahí se crean las tablas propias (cola de correo,
 *      webhooks, códigos de reserva, medios)—. Los 39 casos CORREN.
 *   2. No hay entorno: NO se define un `WP_UnitTestCase` de mentira (el anterior lo hacía y convertía
 *      «no medí nada» en verde). Se cargan los dobles de la suite unitaria solo para que las clases
 *      existan, y cada caso se declara SALTADO con su motivo. El CI lo cuenta y lo enseña.
 *
 * Variables de entorno:
 *   WP_TESTS_DIR                 biblioteca de pruebas de WordPress (la del montaje del entorno).
 *   WP_PHPUNIT__TESTS_CONFIG     ruta al wp-tests-config.php (compatibilidad con el montaje actual).
 *   WP_TESTS_CONFIG_FILE_PATH    alternativa estándar de WordPress.
 *   CONVOCA_WP_ROOT              raíz de WordPress (por defecto /var/www/html).
 */

$convoca_wp_root = getenv( 'CONVOCA_WP_ROOT' ) ?: '/var/www/html';
$convoca_wptests = getenv( 'WP_TESTS_DIR' ) ?: '';
$convoca_config  = getenv( 'WP_TESTS_CONFIG_FILE_PATH' ) ?: ( getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ?: '' );

/*
 * La clase base de los casos se carga SIEMPRE, con o sin entorno: es la que sabe declarar los casos
 * saltados cuando no hay WordPress. Si sólo se cargara en la rama buena, sin entorno PHPUnit moriría
 * con «Class IntegrationTestCase not found» (exit 255) en vez de decir qué falta.
 */
require_once __DIR__ . '/IntegrationTestCase.php';

$convoca_hay_entorno = '' !== $convoca_wptests
	&& file_exists( $convoca_wptests . '/includes/bootstrap.php' )
	&& '' !== $convoca_config
	&& file_exists( $convoca_config )
	&& file_exists( $convoca_wp_root . '/wp-load.php' );

if ( $convoca_hay_entorno ) {
	// La biblioteca de WordPress lee la configuración por constante, no por variable de entorno.
	if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
		define( 'WP_TESTS_CONFIG_FILE_PATH', $convoca_config );
	}

	/*
	 * La biblioteca de pruebas de WordPress EXIGE la librería de polyfills para arrancar (aunque aquí no
	 * usemos `WP_UnitTestCase`): sin ella aborta con «The PHPUnit Polyfills library is a requirement».
	 * La 2.0.x soporta PHPUnit 10. Se le dice dónde está por constante, que es lo que lee.
	 */
	$convoca_polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
	if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) && file_exists( $convoca_polyfills . '/phpunitpolyfills-autoload.php' ) ) {
		define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $convoca_polyfills );
	}

	require_once $convoca_wptests . '/includes/functions.php';
	require_once $convoca_wptests . '/includes/bootstrap.php';

	require_once dirname( __DIR__ ) . '/convoca-enroll.php';

	/*
	 * El entorno carga WordPress, pero no ejecuta la activación del plugin. Las tablas propias (cola de
	 * correo, webhooks, códigos de reserva, medios) se crean ahí: sin esto las pruebas fallan por «tabla
	 * no existe» y el fallo parece del código. Es idempotente (dbDelta / CREATE TABLE IF NOT EXISTS), así
	 * que se puede ejecutar en cada arranque.
	 */
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	do_action( 'activate_' . plugin_basename( dirname( __DIR__ ) . '/convoca-enroll.php' ) );

	return;
}

/*
 * Sin entorno. Se cargan los dobles de la suite unitaria para que las clases del plugin existan y el
 * fichero se pueda cargar; los casos, al no encontrar WordPress, se declaran saltados con su motivo
 * (lo hace `IntegrationTestCase::setUp`, que es quien sabe decirlo). Aquí NO se define nada que finja
 * ser WordPress.
 */
require_once __DIR__ . '/bootstrap-unit.php';
