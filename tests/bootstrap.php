<?php
/**
 * PHPUnit bootstrap for Convoca Enroll.
 *
 * @package Convoca\Enroll\Tests
 */

// ── WP function stubs (standalone test mode) ──
// These must be at file level, not inside control structures.
namespace Convoca\Enroll {

if (!function_exists('Convoca\Enroll\add_action')) {
    function add_action() {}
    function add_filter() {}
    function apply_filters($tag, $value) { return $value; }
    function do_action() {}
    function __($text, $domain = null) { return $text; }
    function current_user_can($cap = '') { return true; }
    function get_user_meta($user_id, $key = '', $single = false) { return ''; }
    function wp_upload_dir() { return array('basedir' => sys_get_temp_dir(), 'baseurl' => 'http://localhost'); }
    function get_option($option, $default = false) { if ($option === 'conv_enroll_settings') return array('admin_email' => 'test@test.com', 'rgpd_version' => '1.0'); return $default; }
    function update_option($option, $value) { return true; }
    function get_post_meta($post_id, $key = '', $single = false) { return ''; }
    function update_post_meta($post_id, $meta_key, $meta_value) { return $post_id > 0 ? true : false; }
    function delete_post_meta($post_id, $key) { return true; }
    function wp_parse_args($args, $defaults) { return array_merge($defaults, is_array($args) ? $args : array()); }
    function wp_json_encode($data) { return json_encode($data); }
    function wp_create_nonce($action = '') { return md5($action); }
    function wp_verify_nonce($nonce, $action = '') { return true; }
    function wp_generate_password($length = 12, $special_chars = true) { return str_repeat('x', $length); }
    function sanitize_text_field($str) { return $str; }
    function sanitize_title($title) { return strtolower(str_replace(' ', '-', $title)); }
    function absint($maybe_int) { return (int)$maybe_int; }
    function trailingslashit($string) { return rtrim($string, '/') . '/'; }
    function get_posts($args = array()) { return array(); }
    function user_can($user, $cap) { return true; }
    function wp_list_pluck($list, $field) { return array_map(function($item) use ($field) { return is_array($item) ? ($item[$field] ?? null) : ($item->$field ?? null); }, $list); }
    function current_time($type) { return date('Y-m-d H:i:s'); }
    function _x($text, $context, $domain = null) { return $text; }
    function esc_html($text) { return htmlspecialchars((string)$text); }
    function esc_attr($text) { return htmlspecialchars((string)$text); }
}

} // end namespace Convoca\Enroll

// ── Back to global namespace ──
namespace {

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
        $core_file = dirname(__DIR__, 2) . '/convoca-core/convoca-core.php';
        if (file_exists($core_file)) {
            require_once $core_file;
        }
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
    if (!defined('CONV_ENROLL_VERSION')) {
        define('CONV_ENROLL_VERSION', '2.5.1');
    }
    if (!defined('CONV_ENROLL_DIR')) {
        define('CONV_ENROLL_DIR', dirname(__DIR__) . '/');
    }
    if (!defined('CONV_ENROLL_DB_VERSION')) {
        define('CONV_ENROLL_DB_VERSION', '1.3.0');
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
    $enroll_includes = CONV_ENROLL_DIR . 'includes';
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

} // end global namespace
