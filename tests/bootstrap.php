<?php
/**
 * PHPUnit bootstrap for Convoca Enroll.
 *
 * @package Convoca\Enroll\Tests
 */

// Try to find WordPress test library.
$wp_tests_dir = getenv('WP_TESTS_DIR');

if (!$wp_tests_dir) {
    $candidates = [
        '/var/www/html/wp-content/plugins/../..',
        getenv('WP_DEVELOP_DIR') . '/tests/phpunit',
        '/tmp/wordpress-tests-lib',
        '../../../../tests/phpunit',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate && file_exists($candidate . '/includes/functions.php')) {
            $wp_tests_dir = $candidate;
            break;
        }
    }
}

if ($wp_tests_dir && file_exists($wp_tests_dir . '/includes/functions.php')) {
    require_once $wp_tests_dir . '/includes/functions.php';

    function _manually_load_plugin(): void {
        // Load convoca-core first (as it's a dependency).
        $core_file = dirname(__DIR__, 2) . '/convoca-core/convoca-core.php';
        if (file_exists($core_file)) {
            require_once $core_file;
        }
        // Then load convoca-enroll.
        $enroll_file = dirname(__DIR__) . '/convoca-enroll.php';
        if (file_exists($enroll_file)) {
            require_once $enroll_file;
        }
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');

    require_once $wp_tests_dir . '/includes/bootstrap.php';
} else {
    // Standalone test mode.
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    if (!defined('BDE_VERSION')) {
        define('BDE_VERSION', '2.5.1');
    }
    if (!defined('BDE_DIR')) {
        define('BDE_DIR', dirname(__DIR__) . '/');
    }
    if (!defined('BDE_DB_VERSION')) {
        define('BDE_DB_VERSION', '1.3.0');
    }

    // Load convoca-core classes (dependency).
    $core_dir = dirname(__DIR__, 2) . '/convoca-core';
    $core_autoload = $core_dir . '/vendor/autoload.php';
    if (file_exists($core_autoload)) {
        require_once $core_autoload;
    }

    $core_includes = $core_dir . '/includes';
    $core_classes = [
        'class-utils.php',
        'class-logger.php',
    ];
    foreach ($core_classes as $file) {
        $path = $core_includes . '/' . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // Load enroll classes.
    $enroll_includes = BDE_DIR . 'includes';
    $enroll_classes = [
        'class-cpt-actividad.php',
        'class-cpt-inscripcion.php',
        'class-motor-inscripcion.php',
        'class-rest-api.php',
    ];
    foreach ($enroll_classes as $file) {
        $path = $enroll_includes . '/' . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

if (!defined('CONVOCA_ENROLL_TEST_MODE')) {
    define('CONVOCA_ENROLL_TEST_MODE', true);
}
