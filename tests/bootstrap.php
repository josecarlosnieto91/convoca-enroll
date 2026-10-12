<?php
/**
 * Bootstrap de la suite de INTEGRACION de convoca-enroll (tests/Integration).
 *
 * Que arregla respecto al anterior:
 *  1. Cargaba WordPress DOS veces: primero el wp-load.php del sitio vivo (ruta
 *     absoluta /var/www/html, imposible de reproducir en el CI) y despues el
 *     bootstrap de wp-phpunit, que vuelve a cargar WordPress. Ahora carga una sola
 *     vez, por el camino de wp-phpunit (que es el que trae WP_UnitTestCase).
 *  2. La ruta de WordPress estaba escrita a mano. Ahora se resuelve por entorno
 *     (CONVOCA_WP_ROOT) con el valor del contenedor de desarrollo por defecto.
 *  3. Si el entorno de pruebas no esta montado, la suite NO reventaba con un
 *     "Class WP_UnitTestCase not found" que parecia un fallo del codigo: ahora los
 *     tests se declaran SALTADOS, contados y con el motivo escrito (issue
 *     convoca-health-check#3), y el resultado sale en el informe del CI.
 *
 * Variables de entorno:
 *   WP_PHPUNIT__TESTS_CONFIG  ruta al wp-tests-config.php (lo lee wp-phpunit).
 *   CONVOCA_WP_ROOT           raiz de WordPress (por defecto /var/www/html).
 */

$convoca_wp_root = getenv('CONVOCA_WP_ROOT') ?: '/var/www/html';

// wp-phpunit y los polyfills viven en el vendor de este plugin; si no estan
// instalados (p.ej. un checkout sin composer install) se buscan en el del nucleo,
// que es donde los encuentra el contenedor de desarrollo.
$convoca_candidatos = [
    dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit',
    $convoca_wp_root . '/wp-content/plugins/convoca-core/vendor/wp-phpunit/wp-phpunit',
];
$convoca_wp_phpunit = '';
foreach ($convoca_candidatos as $convoca_candidato) {
    if (file_exists($convoca_candidato . '/includes/bootstrap.php')) {
        $convoca_wp_phpunit = $convoca_candidato;
        break;
    }
}

$convoca_polyfills = [
    dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills',
    $convoca_wp_root . '/wp-content/plugins/convoca-core/vendor/yoast/phpunit-polyfills',
];
foreach ($convoca_polyfills as $convoca_candidato) {
    if (file_exists($convoca_candidato . '/phpunitpolyfills-autoload.php')) {
        defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH') || define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $convoca_candidato);
        break;
    }
}

$convoca_tests_config = getenv('WP_PHPUNIT__TESTS_CONFIG');
if ('' !== $convoca_wp_phpunit && file_exists((string) $convoca_tests_config)
    && file_exists($convoca_wp_root . '/wp-load.php')) {
    // Camino bueno: WordPress real (base de datos de pruebas) + WP_UnitTestCase.
    require_once $convoca_wp_phpunit . '/includes/functions.php';
    require_once $convoca_wp_phpunit . '/includes/bootstrap.php';
    require_once dirname(__DIR__) . '/convoca-enroll.php';
    return;
}

/*
 * Sin entorno de pruebas. Los ficheros de tests/Integration extienden WP_UnitTestCase,
 * que no existe en este proceso; sin esto la suite muere al cargarse ("Class not
 * found") y el fallo parece del codigo del plugin.
 *
 * Se cargan los dobles de WordPress (los mismos que la suite unitaria) por dos razones:
 *  - definen ABSPATH: sin el, un `if (!defined('ABSPATH')) { exit; }` de cualquier
 *    fichero del plugin MATA el proceso en silencio y con codigo 0 (una suite truncada
 *    que se lee como exito);
 *  - dejan cargar las clases que los tests usan, para poder contarlos.
 * Los que extienden WP_UnitTestCase se declaran SALTADOS, con el motivo y su numero.
 */
require_once __DIR__ . '/bootstrap-unit.php';

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends \PHPUnit\Framework\TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $this->markTestSkipped(
                'Suite de integracion sin entorno de WordPress: falta un WordPress con base de '
                . 'datos de pruebas. Ver convoca-health-check#3 (montar el entorno con '
                . 'bin/install-wp-tests.sh y WP_PHPUNIT__TESTS_CONFIG).'
            );
        }
    }
}
