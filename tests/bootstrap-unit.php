<?php
/**
 * Bootstrap for unit tests — standalone, no WordPress needed.
 * Mocks WordPress functions for Convoca Enroll testing.
 * Stubs defined BEFORE autoloader to prevent ABSPATH exit guards.
 */
 
// Mock Convoca\Core classes first (must be in own namespace block at top)
namespace Convoca\Core {
    if (!class_exists('Utils')) {
        class Utils {
            public static function validate_dni(string $dni): bool {
                // Quick validation: 8 digits + letter, or X/Y/Z + 7 digits + letter
                return (bool) preg_match('/^(\d{8}|[XYZ]\d{7})[A-Z]$/', strtoupper(trim($dni)));
            }
            public static function validar_dni(string $dni): bool { return self::validate_dni($dni); }
        }
    }
}

namespace { // global namespace for all stubs

$GLOBALS['_wp_stores'] = [
    'options'    => [],
    'post_meta'  => [],
    'transients' => [],
    'user_meta'  => [],
];

if (!defined('ABSPATH')) { define('ABSPATH', dirname(__DIR__) . '/'); }
if (!defined('WP_DEBUG')) { define('WP_DEBUG', true); }
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }

// Options
if (!function_exists('get_option')) {
    function get_option($k, $d = false) {
        $s = &$GLOBALS['_wp_stores']['options'];
        return array_key_exists($k, $s) ? $s[$k] : $d;
    }
    function update_option($k, $v, $a = null) { $GLOBALS['_wp_stores']['options'][$k] = $v; return true; }
    function delete_option($k) { unset($GLOBALS['_wp_stores']['options'][$k]); return true; }
}

// Transients
if (!function_exists('get_transient')) {
    function get_transient($k) { $s = &$GLOBALS['_wp_stores']['transients']; return $s[$k] ?? false; }
    function set_transient($k, $v, $e = 0) { $GLOBALS['_wp_stores']['transients'][$k] = $v; return true; }
    function delete_transient($k) { unset($GLOBALS['_wp_stores']['transients'][$k]); return true; }
}

// Post meta
if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $k, $s = false) {
        $v = $GLOBALS['_wp_stores']['post_meta'][$id][$k] ?? null;
        if ($v === null) return $s ? '' : [];
        return $s ? $v : (is_array($v) ? $v : [$v]);
    }
    function update_post_meta($id, $k, $v) { $GLOBALS['_wp_stores']['post_meta'][$id][$k] = $v; return true; }
    function delete_post_meta($id, $k) { unset($GLOBALS['_wp_stores']['post_meta'][$id][$k]); return true; }
}

// Core WP
if (!function_exists('__')) { function __($t, $d = 'default') { return $t; } }
if (!function_exists('_e')) { function _e($t, $d = 'default') { echo $t; } }
if (!function_exists('esc_html')) { function esc_html($t) { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_attr')) { function esc_attr($t) { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($u) { return filter_var($u, FILTER_SANITIZE_URL); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return trim(strip_tags($s)); } }
if (!function_exists('sanitize_title')) { function sanitize_title($t) { return strtolower(str_replace(' ', '-', trim($t))); } }
if (!function_exists('sanitize_email')) { function sanitize_email($e) { return filter_var($e, FILTER_SANITIZE_EMAIL); } }
if (!function_exists('absint')) { function absint($v) { return abs((int)$v); } }
if (!function_exists('wp_unslash')) { function wp_unslash($s) { return is_string($s) ? stripslashes($s) : $s; } }

// Hooks
if (!function_exists('apply_filters')) { function apply_filters($t, $v, ...$a) { return $v; } }
if (!function_exists('do_action')) { function do_action($t, ...$a) {} }
if (!function_exists('add_action')) { function add_action($t, $c, $p = 10, $a = 1) { return true; } }
if (!function_exists('add_filter')) { function add_filter($t, $c, $p = 10, $a = 1) { return true; } }

// Auth
if (!function_exists('current_user_can')) { function current_user_can($c, ...$a) { return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 1; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a = -1) { return 'test_nonce'; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($n, $a = -1) { return true; } }

// HTTP
if (!function_exists('wp_remote_get')) { function wp_remote_get($u, $a = []) { return ['response' => ['code' => 200], 'body' => '{}']; } }
if (!function_exists('wp_remote_post')) { function wp_remote_post($u, $a = []) { return ['response' => ['code' => 200], 'body' => '{}']; } }
if (!function_exists('is_wp_error')) { function is_wp_error($t) { return $t instanceof WP_Error; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d, $o = 0, $d2 = 512) { return json_encode($d, $o, $d2); } }
if (!function_exists('wp_safe_remote_get')) { function wp_safe_remote_get($u, $a = []) { return wp_remote_get($u, $a); } }

// Time
if (!function_exists('current_time')) {
    function current_time($t = 'mysql') {
        if ($t === 'mysql') return date('Y-m-d H:i:s');
        if ($t === 'timestamp') return time();
        return date($t);
    }
}
if (!function_exists('wp_date')) { function wp_date($f, $ts = null) { return date($f, $ts ?? time()); } }
if (!function_exists('wp_timezone_string')) { function wp_timezone_string() { return 'Europe/Madrid'; } }

// Posts
if (!function_exists('get_the_title')) { function get_the_title($id) { return "Post $id"; } }
if (!function_exists('get_post_status')) { function get_post_status($id) { return 'publish'; } }
if (!function_exists('get_post')) { function get_post($id = null) { if (!$id) return null; $p = new stdClass(); $p->ID = $id; $p->post_type = 'post'; $p->post_status = 'publish'; return $p; } }
if (!function_exists('post_type_exists')) { function post_type_exists($t) { return in_array($t, ['post','page','actividad','miembro'], true); } }
if (!function_exists('wp_insert_post')) { function wp_insert_post($d) { static $c = 100; $c++; return $c; } }
if (!function_exists('wp_update_post')) { function wp_update_post($d) { return $d['ID'] ?? wp_insert_post($d); } }
if (!function_exists('get_posts')) { function get_posts($a) { return []; } }

// URLs
if (!function_exists('home_url')) { function home_url($p = '') { return "https://example.com$p"; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return "/wp-admin/$p"; } }
if (!function_exists('plugin_basename')) { function plugin_basename($f) { return basename($f); } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return dirname($f) . '/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f) { return 'https://example.com/wp-content/plugins/' . basename(dirname($f)) . '/'; } }

// Schedule
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h) { return false; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event($ts, $r, $h, $a = []) { return true; } }
if (!function_exists('wp_clear_scheduled_hook')) { function wp_clear_scheduled_hook($h) { return true; } }

// Misc
if (!function_exists('register_post_type')) { function register_post_type($s, $a) { return null; } }
if (!function_exists('register_post_meta')) { function register_post_meta($t, $k, $a) { return true; } }
if (!function_exists('register_taxonomy')) { function register_taxonomy($s, $t, $a) { return null; } }
if (!function_exists('register_rest_route')) { function register_rest_route($n, $r, $a) { return true; } }
if (!function_exists('wp_redirect')) { function wp_redirect($u) {} }
if (!function_exists('wp_die')) { function wp_die($m = '', $t = '', $a = []) {} }
if (!function_exists('wp_cache_delete')) { function wp_cache_delete($k, $g = '') { return true; } }
if (!function_exists('flush_rewrite_rules')) { function flush_rewrite_rules() {} }
if (!function_exists('remove_action')) { function remove_action($h, $c, $p = 10) { return true; } }
if (!function_exists('load_plugin_textdomain')) { function load_plugin_textdomain($d, $dep, $p) {} }
if (!function_exists('get_user_by')) { function get_user_by($f, $v) { return false; } }
if (!function_exists('get_users')) { function get_users($a = []) { return []; } }
if (!function_exists('get_userdata')) { function get_userdata($id) { if ($id <= 0) return false; $u = new stdClass(); $u->ID = $id; $u->display_name = "User $id"; $u->user_email = "user$id@test.com"; return $u; } }
if (!function_exists('get_current_screen')) { function get_current_screen() { return null; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style($h, $s = '', $d = [], $v = '', $m = 'all') {} }
if (!function_exists('wp_register_style')) { function wp_register_style($h, $s, $d = [], $v = '', $m = 'all') { return true; } }
if (!function_exists('wp_set_script_translations')) { function wp_set_script_translations($h, $d, $p) {} }
if (!function_exists('wp_kses_post')) { function wp_kses_post($s) { return $s; } }
if (!function_exists('wp_rand')) { function wp_rand($min = 0, $max = PHP_INT_MAX) { return rand($min, $max); } }
if (!function_exists('user_can')) { function user_can($user, $cap) { return true; } }

// WP_Error
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = []; private $error_data = [];
        public function __construct($code = '', $message = '', $data = '') {
            if ($code) { $this->errors[$code] = [$message]; $this->error_data[$code] = $data; }
        }
        public function get_error_code() { return key($this->errors); }
        public function get_error_message($code = '') {
            if (!$code) $code = $this->get_error_code();
            return isset($this->errors[$code][0]) ? $this->errors[$code][0] : '';
        }
    }
}

// WP_Post
if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID = 0; public $post_title = ''; public $post_type = 'post';
        public $post_status = 'publish'; public $post_content = '';
    }
}

// WP_User
if (!class_exists('WP_User')) {
    class WP_User {
        public $ID = 0; public $roles = ['administrator'];
        public function exists() { return $this->ID > 0; }
    }
}

// $wpdb
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_'; public $posts = 'wp_posts'; public $postmeta = 'wp_postmeta';
        public $options = 'wp_options'; public $insert_id = 42;
        public function get_var($q = null, $x = 0, $y = 0) { return '0'; }
        public function get_results($q = null, $o = 'OBJECT') { return []; }
        public function get_row($q = null) { return null; }
        public function query($q) { return 1; }
        public function insert($t, $d, $f = []) { $this->insert_id = 42; return 1; }
        public function update($t, $d, $w) { return 1; }
        public function delete($t, $w) { return 1; }
        public function prepare($q, ...$a) {
            $sql = $q;
            foreach ($a as $arg) { $p = strpos($sql, '%'); if ($p !== false) { $sql = substr_replace($sql, (string)$arg, $p, 2); } }
            return $sql;
        }
        public function escape($d) { return addslashes($d); }
        public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
    };
}

// WP_REST_Response
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data; private $status;
        public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
    }
}

// Load Composer autoloader LAST
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

date_default_timezone_set('Europe/Madrid');
}
