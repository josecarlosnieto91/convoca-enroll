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
            /**
             * Etiqueta de la aportación según el contexto (en core es configurable; aquí, la de por defecto).
             *
             * @param string $contexto Contexto.
             * @return string
             */
            /**
             * Logo del centro para el contexto pedido (lo fija la prueba con `convoca_test_logo`).
             *
             * @param string $contexto Contexto.
             * @return string
             */
            public static function get_logo_url(string $contexto = ''): string {
                unset($contexto);
                return (string) ($GLOBALS['convoca_test_logo'] ?? '');
            }

            public static function get_aportacion_label(string $contexto = 'singular'): string {
                if ('plural' === $contexto) { return 'Aportaciones'; }
                return 'Aportación';
            }

            /**
             * «Emite» un CSV: apunta el nombre, la cabecera y las filas, y corta el flujo como el `exit` real.
             *
             * El `stream_csv()` de verdad abre la salida y termina el proceso: en una prueba se llevaría por delante
             * a PHPUnit. Se recorre el iterable igual, que es lo que hay que medir.
             *
             * @param string   $nombre   Fichero.
             * @param array    $cabecera Columnas.
             * @param iterable $filas    Filas.
             */
            /**
             * Avisos guardados en la opción temporal: los pinta la pantalla de ajustes.
             *
             * @return void
             */
            /**
             * Fecha formateada en la zona del sitio.
             *
             * @param string $fecha  Fecha (Y-m-d H:i:s).
             * @param string $formato Formato.
             * @return string
             */
            public static function format_date( $fecha, $formato = 'd/m/Y' ) {
                $ts = strtotime( (string) $fecha );
                if ( ! $ts ) { return ''; }
                $salida = date( $formato, $ts );
                // El sitio está en español: `wp_date()` traduce el mes. `date()` lo deja en inglés y las pruebas
                // leerían «14 November 2026» como si el producto fallara.
                if ( false !== strpos( $formato, 'F' ) ) {
                    $meses = array(
                        'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril',
                        'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto',
                        'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre',
                        'December' => 'diciembre',
                    );
                    $salida = strtr( $salida, $meses );
                }
                return $salida;
            }

            /**
             * ¿Está activo el plugin? Con `$GLOBALS['plugins_inactivos'][ slug ]` se simula que no.
             *
             * @param string $slug Plugin.
             * @return bool
             */
            /**
             * Guarda un aviso para la siguiente pantalla.
             *
             * @param string $mensaje Texto.
             * @param string $tipo    info|success|warning|error.
             * @return void
             */
            /**
             * Pinta un aviso en la pantalla.
             *
             * @param string $mensaje Texto.
             * @param string $tipo    Tipo.
             * @return void
             */
            public static function admin_notice( $mensaje, $tipo = 'info' ) {
                echo '<div class="notice notice-' . esc_attr( $tipo ) . '"><p>' . esc_html( $mensaje ) . '</p></div>';
                $GLOBALS['_wp_notices'][] = array( 'mensaje' => $mensaje, 'tipo' => $tipo );
            }

            public static function set_admin_notice( $mensaje, $tipo = 'info' ) {
                $GLOBALS['_wp_stores']['transients']['_convoca_notices'][] = array( 'mensaje' => $mensaje, 'tipo' => $tipo );
            }

            /**
             * Panel de diagnóstico: una fila por comprobación.
             *
             * @param array $checks Comprobaciones.
             * @return void
             */
            public static function render_diagnostic_panel( array $checks ) {
                echo '<div class="conv-diagnostico">';
                foreach ( $checks as $check ) {
                    echo '<div class="conv-check conv-check-' . esc_attr( $check['status'] ) . '">'
                        . esc_html( $check['title'] ) . ': ' . esc_html( $check['message'] ) . '</div>';
                }
                echo '</div>';
            }

            public static function is_plugin_active_safe( $slug ) {
                return empty( $GLOBALS['plugins_inactivos'][ $slug ] );
            }

            public static function render_stored_notices() {
                $avisos = $GLOBALS['_wp_stores']['transients']['_convoca_notices'] ?? array();
                foreach ( (array) $avisos as $aviso ) {
                    echo '<div class="notice">' . esc_html( (string) $aviso ) . '</div>';
                }
            }

            public static function stream_csv($nombre, array $cabecera, $filas) {
                $datos = array();
                foreach ($filas as $fila) { $datos[] = $fila; }
                $GLOBALS['_csv'][] = array('fichero' => $nombre, 'cabecera' => $cabecera, 'filas' => $datos);
                throw new \Convoca_Json_Cortado(array('csv' => $nombre));
            }

            /**
             * DNI/NIE con la **letra correcta**, como el del núcleo: una regex sola acepta cualquier letra y
             * deja pasar un DNI inventado (12345678A en vez de 12345678Z).
             */
            public static function validate_dni(string $dni): bool {
                $dni = strtoupper(str_replace(array(' ', '-'), '', trim($dni)));
                if ('' === $dni) { return false; }
                if (!preg_match('/^([0-9]{8}|[XYZ][0-9]{7})[A-Z]$/', $dni)) { return false; }

                $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
                $numero = substr($dni, 0, -1);
                $numero = strtr($numero[0], array('X' => '0', 'Y' => '1', 'Z' => '2')) . substr($numero, 1);

                return $letras[ ((int) $numero) % 23 ] === substr($dni, -1);
            }
            public static function validar_dni(string $dni): bool { return self::validate_dni($dni); }
            public static function do_action(string $new_hook, string $old_hook = '', ...$args): void {}

            /**
             * Cerrojo cooperativo: por defecto se adquiere; una prueba puede negarlo.
             *
             * @param string $clave  Clave.
             * @param int    $segundos Caducidad.
             * @return bool
             */
            public static function acquire_lock(string $clave, int $segundos = 300): bool {
                $GLOBALS['_convoca_locks_pedidos'][] = $clave;
                return (bool) ($GLOBALS['convoca_test_lock'] ?? true);
            }

            /**
             * Suelta el cerrojo.
             *
             * @param string $clave Clave.
             * @return void
             */
            public static function release_lock(string $clave): void {
                $GLOBALS['_convoca_locks_soltados'][] = $clave;
            }
            /**
             * Límite de peticiones que la prueba puede agotar: por defecto deja pasar, y con
             * `$GLOBALS['convoca_test_rate_ok'][ accion ] = false` se simula el 429.
             */
            public static function check_rate_limit(string $action, int $max = 10, int $window = 300): bool {
                return (bool) ($GLOBALS['convoca_test_rate_ok'][ $action ] ?? true);
            }
            /** Caché de respuestas: ejecuta el generador, que es lo que hace la real sin caché. */
            public static function rest_cache_get(string $key, int $ttl, callable $generador) { return $generador(); }
            // Sal persistente del sitio: la firma del enlace de check-in se calcula con
            // ella, así que la prueba la puede fijar para comprobar el positivo y el negativo.
            public static function get_persistent_salt(): string { return (string) ($GLOBALS['convoca_test_salt'] ?? 'sal-de-prueba'); }
        }
    }
    if (!class_exists('Logger')) {
        class Logger {
            /**
             * Apunta el registro para poder afirmar qué se dijo (y con qué contexto).
             *
             * @param string $message Mensaje.
             * @param string $level   Nivel.
             * @param string $context Contexto.
             * @param int    $id      Entidad.
             * @return void
             */
            public static function anotar(string $message, string $level, string $context, int $id = 0): void {
                $GLOBALS['_wp_logs'][] = compact('message', 'level', 'context', 'id');
            }
            public static function debug(string $message, string $context = '', int $id = 0): void { self::anotar($message, 'debug', $context, $id); }
            public static function info(string $message, string $context = '', int $id = 0): void { self::anotar($message, 'info', $context, $id); }
            public static function warning(string $message, string $context = '', int $id = 0): void { self::anotar($message, 'warning', $context, $id); }
            public static function error(string $message, string $context = '', int $id = 0): void { self::anotar($message, 'error', $context, $id); }
            public static function log(string $message, string $level = 'info', string $context = '', int $id = 0): void { self::anotar($message, $level, $context, $id); }
        }
    }
}

namespace Convoca\Core {

	if ( ! class_exists( 'Convoca\Core\License_Manager' ) ) {

		/**
		 * Licencia: qué funciones PRO están activas. Por defecto ninguna; la prueba enciende las que necesita.
		 */
		class License_Manager {

			/**
			 * ¿Está activa la función?
			 *
			 * @param string $funcion Función.
			 * @return bool
			 */
			public static function has_pro( string $funcion ): bool {
				return (bool) ( $GLOBALS['convoca_test_pro'][ $funcion ] ?? false );
			}
		}
	}
}

namespace Convoca\Core\Admin {

    // El andamiaje de las pantallas de administración vive en el núcleo (convoca-core#16): las
    // pantallas de este plugin heredan de `Pantalla_Admin` o usan su trait, así que el símbolo tiene
    // que existir cuando se declara la clase. Se carga el fichero REAL si el núcleo está clonado al
    // lado (es lo que hay en local y lo que deja el CI); solo si no está, se declara un doble mínimo.
    // Un doble que se queda atrás mediría algo que no existe.
    if ( ! \defined( 'ABSPATH' ) ) {
        \define( 'ABSPATH', \dirname( __DIR__ ) . '/' );
    }

    $convoca_core_admin = \dirname( __DIR__, 2 ) . '/convoca-core/includes/Admin/Andamiaje_Admin.php';
    if ( \file_exists( $convoca_core_admin ) && ! \trait_exists( 'Convoca\\Core\\Admin\\Andamiaje_Admin' ) ) {
        require_once $convoca_core_admin;
        require_once \dirname( __DIR__, 2 ) . '/convoca-core/includes/Admin/Pantalla_Admin.php';
    }

    if ( ! \trait_exists( 'Convoca\\Core\\Admin\\Andamiaje_Admin' ) ) {
        trait Andamiaje_Admin
        {
            abstract protected function ganchos(): array;
            protected function editor_propio(): ?array { return null; }
            protected function nodo_barra(): ?array { return null; }
            public function __construct() {}
            protected function registrar_subpagina( ?string $padre, string $titulo, string $menu, string $capacidad, string $slug, callable $callback ): string { return ''; }
            protected function encolar_estilo_comun(): void {}
            /**
             * Guarda real: nonce válido y capacidad. Era un no-op, así que ninguna prueba podía comprobar que un
             * guardado sin nonce o sin permiso se corta.
             *
             * @param string $nonce     Acción del nonce.
             * @param string $capacidad Capacidad exigida.
             * @param string $mensaje   Mensaje del `wp_die`.
             * @return void
             */
            protected function exigir_guardado( string $nonce, string $capacidad, string $mensaje ): void {
                check_admin_referer( $nonce );
                if ( ! current_user_can( $capacidad ) ) {
                    wp_die( $mensaje );
                }
            }
            public function redirigir_editor_propio(): void {}
            public function apuntar_nodo_barra( $wp_admin_bar ): void {}
        }

        abstract class Pantalla_Admin
        {
            use Andamiaje_Admin;
        }
    }
}

namespace { // global namespace for all stubs

$GLOBALS['_wp_stores'] = [
    'options'    => [],
    'post_meta'  => [],
    'transients' => [],
    'user_meta'  => [],
    'sent_mail'  => [],
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

// Sales: cifrar y descifrar tokens se apoyan en wp_salt() (issue #21). Aquí no hay WordPress,
// así que las sales son fijas y CONOCIDAS: lo que se prueba es el esquema de cifrado, no el secreto.
if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        $sales = array(
            'auth'        => 'sal-de-prueba-para-autenticacion',
            'secure_auth' => 'sal-de-prueba-para-autenticacion-segura',
            'nonce'       => 'sal-de-prueba-para-los-nonces-1234',
        );
        return $sales[$scheme] ?? $sales['auth'];
    }
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
    /**
     * Guarda la meta como lo hace WordPress: los escalares se leen de la base como texto.
     *
     * Sin esto, el producto compara `=== '1'` contra un entero y la validación nunca entra: la prueba mediría un
     * camino que en producción no existe.
     */
    function update_post_meta($id, $k, $v) {
        $GLOBALS['_wp_stores']['post_meta'][$id][$k] = is_scalar($v) ? (string) $v : $v;
        return true;
    }
    function delete_post_meta($id, $k) { unset($GLOBALS['_wp_stores']['post_meta'][$id][$k]); return true; }
}

// Core WP
if (!function_exists('__')) { function __($t, $d = 'default') { return $t; } }
if (!function_exists('esc_html__')) { function esc_html__($t, $d = 'default') { return htmlspecialchars((string) $t, ENT_QUOTES); } }
if (!function_exists('esc_attr__')) { function esc_attr__($t, $d = 'default') { return htmlspecialchars((string) $t, ENT_QUOTES); } }
if (!function_exists('esc_html_e')) { function esc_html_e($t, $d = 'default') { echo htmlspecialchars((string) $t, ENT_QUOTES); } }
if (!function_exists('esc_attr_e')) { function esc_attr_e($t, $d = 'default') { echo htmlspecialchars((string) $t, ENT_QUOTES); } }
if (!function_exists('_e')) { function _e($t, $d = 'default') { echo $t; } }
if (!function_exists('wpautop')) {
    /**
     * Párrafos como WordPress: los bloques separados por línea en blanco van en `<p>` y los saltos sueltos, en `<br>`.
     * Si el texto ya trae párrafos, se respeta.
     */
    function wpautop($texto) {
        $texto = (string) $texto;
        if ('' === trim($texto) || false !== strpos($texto, '<p')) { return $texto; }
        $bloques = preg_split('/\n\s*\n/', trim($texto));
        $salida  = '';
        foreach ($bloques as $bloque) {
            $salida .= '<p>' . nl2br($bloque) . "</p>\n";
        }
        return $salida;
    }
}

if (!function_exists('get_post_modified_time')) {
    /** Marca de modificación del post (para la clave de caché). */
    function get_post_modified_time($formato = 'U', $gmt = false, $post = null, $traducir = false) {
        $obj  = is_object($post) ? $post : ( $GLOBALS['_wp_stores']['posts'][ (int) $post ] ?? null );
        $fecha = (string) ($obj->post_modified ?? '2026-10-01 00:00:00');
        return 'U' === $formato ? (string) strtotime($fecha) : $fecha;
    }
}

if (!function_exists('get_theme_mod')) {
    /** Ajuste del tema: lo que fije la prueba, o el valor por defecto. */
    function get_theme_mod($clave, $por_defecto = false) {
        return $GLOBALS['_convoca_theme_mods'][ $clave ] ?? $por_defecto;
    }
}
if (!function_exists('set_theme_mod')) {
    function set_theme_mod($clave, $valor) {
        $GLOBALS['_convoca_theme_mods'][ $clave ] = $valor;
        return true;
    }
}
if (!function_exists('get_permalink')) {
    /** Enlace permanente del post. */
    function get_permalink($post = 0) {
        $id = is_object($post) ? (int) $post->ID : (int) ($post ?: get_the_ID());
        return 'https://example.test/?p=' . $id;
    }
}

if (!function_exists('wp_cache_get')) {
    /** Caché de objetos en memoria para la prueba (sin persistencia). */
    function wp_cache_get($clave, $grupo = '') {
        return $GLOBALS['_convoca_cache'][ $grupo ][ $clave ] ?? false;
    }
    function wp_cache_set($clave, $valor, $grupo = '', $ttl = 0) {
        $GLOBALS['_convoca_cache'][ $grupo ][ $clave ] = $valor;
        return true;
    }
    function wp_cache_add($clave, $valor, $grupo = '', $ttl = 0) {
        if (isset($GLOBALS['_convoca_cache'][ $grupo ][ $clave ])) { return false; }
        return wp_cache_set($clave, $valor, $grupo, $ttl);
    }
    function wp_cache_delete($clave, $grupo = '') {
        unset($GLOBALS['_convoca_cache'][ $grupo ][ $clave ]);
        return true;
    }
}

if (!function_exists('bloginfo')) {
    /** Imprime un dato del sitio (`charset`, `name`…). */
    function bloginfo($clave = 'name') {
        echo 'charset' === $clave ? 'UTF-8' : (string) ( $GLOBALS['convoca_test_blogname'] ?? 'Sitio de ejemplo' );
    }
}
if (!function_exists('language_attributes')) {
    /** Atributos del `<html>`: el idioma del sitio. */
    function language_attributes() {
        echo 'lang="es-ES"';
    }
}
if (!function_exists('wp_head')) {
    /** Cabecera del tema: en la prueba no hay nada que imprimir. */
    function wp_head() {}
}

if (!function_exists('wp_trim_words')) {
    /** Recorta a N palabras con puntos suspensivos. */
    function wp_trim_words($texto, $num = 55, $mas = '…') {
        $palabras = preg_split('/\s+/', trim(wp_strip_all_tags((string) $texto)));
        if (count($palabras) <= $num) { return implode(' ', $palabras); }
        return implode(' ', array_slice($palabras, 0, $num)) . $mas;
    }
}
if (!function_exists('get_post_field')) {
    /** Campo del post; el autor lo fija la prueba en `convoca_test_autores`. */
    function get_post_field($campo, $post = null, $contexto = 'display') {
        $id = is_object($post) ? (int) $post->ID : (int) $post;
        if ('post_author' === $campo) { return (int) ($GLOBALS['convoca_test_autores'][ $id ] ?? 1); }
        $obj = $GLOBALS['_wp_stores']['posts'][ $id ] ?? null;
        return is_object($obj) ? ($obj->$campo ?? '') : '';
    }
}
if (!function_exists('has_post_thumbnail')) {
    /** ¿Tiene imagen destacada? */
    function has_post_thumbnail($id = 0) {
        return (int) ($GLOBALS['convoca_test_thumbnail'][ (int) $id ] ?? 0) > 0;
    }
}
if (!function_exists('set_post_thumbnail')) {
    /** Fija la imagen destacada y lo apunta. */
    function set_post_thumbnail($id, $adjunto) {
        $GLOBALS['convoca_test_thumbnail'][ (int) $id ] = (int) $adjunto;
        $GLOBALS['_convoca_thumbnails_fijados'][] = array( (int) $id, (int) $adjunto );
        return true;
    }
}
if (!function_exists('get_term_by')) {
    /** Término por campo; la prueba fija los que existen en `convoca_test_terms`. */
    function get_term_by($campo, $valor, $taxonomia = '') {
        foreach ((array) ($GLOBALS['convoca_test_terms'] ?? array()) as $term) {
            if ((string) ($term->$campo ?? '') === (string) $valor) { return $term; }
        }
        return false;
    }
}
if (!function_exists('wp_insert_term')) {
    /** Crea un término; devuelve id y lo apunta. */
    function wp_insert_term($nombre, $taxonomia, $args = array()) {
        $id = (int) ($GLOBALS['convoca_test_term_id'] ?? 99);
        $GLOBALS['_convoca_terms_creados'][] = compact('nombre', 'taxonomia', 'args');
        return array('term_id' => $id);
    }
}
if (!function_exists('wp_check_filetype')) {
    /** Tipo de fichero por extensión. */
    function wp_check_filetype($ruta) {
        $ext = strtolower(pathinfo((string) $ruta, PATHINFO_EXTENSION));
        return array('ext' => $ext, 'type' => 'image/' . $ext);
    }
}
if (!function_exists('media_handle_sideload')) {
    /** Adjunto desde un fichero: devuelve el id que fije la prueba. */
    function media_handle_sideload($fichero, $post_id = 0) {
        $GLOBALS['_convoca_sideloads'][] = array($fichero, $post_id);
        return (int) ($GLOBALS['convoca_test_sideload_id'] ?? 500);
    }
}
if (!function_exists('wp_strip_all_tags')) {
    /** Quita etiquetas y espacios sobrantes. */
    function wp_strip_all_tags($texto, $quitar_saltos = false) {
        // `strip_tags(null)` es deprecado en PHP 8.5: se castea antes.
        $texto = strip_tags((string) $texto);
        return $quitar_saltos ? trim(preg_replace('/\s+/', ' ', $texto)) : trim($texto);
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    /** Imagen destacada: la que fije la prueba en `convoca_test_thumbnail`. */
    function get_post_thumbnail_id($id = 0) {
        return (int) ($GLOBALS['convoca_test_thumbnail'][ (int) $id ] ?? 0);
    }
}
if (!function_exists('wp_get_attachment_image_url')) {
    /** URL de la imagen (sin tocar disco). */
    function wp_get_attachment_image_url($id, $size = 'full') {
        return $GLOBALS['convoca_test_thumbnail_url'] ?? '';
    }
}
if (!function_exists('get_attached_file')) {
    /** Fichero en disco del adjunto: ninguno por defecto. */
    function get_attached_file($id) {
        return $GLOBALS['convoca_test_attached'][ (int) $id ] ?? '';
    }
}

if (!function_exists('trailingslashit')) {
    /** Añade la barra final si no la tiene. */
    function trailingslashit($cadena) {
        return rtrim((string) $cadena, '/\\') . '/';
    }
}
if (!function_exists('untrailingslashit')) {
    /** Quita la barra final. */
    function untrailingslashit($cadena) {
        return rtrim((string) $cadena, '/\\');
    }
}

if (!function_exists('wp_hash')) {
    /** Hash con la sal del sitio; para el nombre de fichero basta con que sea estable. */
    function wp_hash($texto, $esquema = 'auth') {
        return hash_hmac('md5', (string) $texto, 'sal-de-prueba-' . $esquema);
    }
}

if (!function_exists('get_temp_dir')) {
    /** Directorio temporal del sistema, con barra final (como WordPress). */
    function get_temp_dir() {
        return rtrim(sys_get_temp_dir(), '/') . '/';
    }
}
if (!function_exists('wp_delete_file')) {
    /** Borra un fichero; devuelve si existia. */
    function wp_delete_file($ruta) {
        return file_exists($ruta) ? @unlink($ruta) : false;
    }
}

if (!function_exists('wp_mkdir_p')) {
    /** Crea el directorio (y sus padres) como WordPress. */
    function wp_mkdir_p($dir) {
        return is_dir($dir) || @mkdir($dir, 0775, true);
    }
}

if (!function_exists('wp_upload_dir')) {
    /**
     * Directorio de subidas bajo control de la prueba (`$GLOBALS['convoca_test_uploads']`), por defecto en el
     * directorio temporal: generar carteles no debe escribir dentro del repo.
     */
    function wp_upload_dir($time = null) {
        $base = $GLOBALS['convoca_test_uploads'] ?? (sys_get_temp_dir() . '/convoca-enroll-uploads');
        wp_mkdir_p($base);
        return array(
            'path'    => $base,
            'url'     => 'https://example.test/uploads',
            'basedir' => $base,
            'baseurl' => 'https://example.test/uploads',
            'error'   => false,
        );
    }
}

if (!function_exists('is_email')) { function is_email($e) { return (bool) filter_var($e, FILTER_VALIDATE_EMAIL); } }
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        $GLOBALS['_wp_stores']['sent_mail'][] = array('to' => $to, 'subject' => $subject, 'message' => $message);
        return true;
    }
}
if (!function_exists('esc_html')) { function esc_html($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_attr')) { function esc_attr($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($u) { return filter_var($u, FILTER_SANITIZE_URL); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return trim(strip_tags((string) $s)); } }
if (!function_exists('sanitize_key')) { function sanitize_key($k) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)); } }
if (!function_exists('date_i18n')) {
    /**
     * `date_i18n` con el locale del sitio (`es_ES`): los nombres de mes y día salen en castellano.
     *
     * Se traduce el resultado de `date()` porque el formato que usa el producto es el de WordPress, no `strftime`.
     *
     * @param string   $formato Formato.
     * @param int|null $marca   Marca de tiempo.
     * @return string
     */
    function date_i18n($formato, $marca = null) {
        $salida = date($formato, $marca ?? time());
        $meses  = array('January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril',
            'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
            'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre',
            'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves',
            'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo');
        return strtr($salida, $meses);
    }
}
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); } }
if (!function_exists('sanitize_title')) {
    /** Como el de WordPress: minúsculas, sin acentos y solo letras, números y guiones (un «ñ» no rompe el slug). */
    function sanitize_title($t) {
        $t = strtolower(trim((string) $t));
        $t = strtr($t, array(
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ));
        $t = preg_replace('/[^a-z0-9]+/', '-', $t);

        return trim((string) $t, '-');
    }
}
if (!function_exists('sanitize_email')) { function sanitize_email($e) { return filter_var($e, FILTER_SANITIZE_EMAIL); } }
if (!function_exists('absint')) { function absint($v) { return abs((int)$v); } }
if (!function_exists('wp_unslash')) { function wp_unslash($s) { return is_string($s) ? stripslashes($s) : $s; } }

// Hooks — registro REAL de filtros: con un `apply_filters` que devolvía el valor tal
// cual, cualquier contrato basado en filtros pasaba sin comprobar nada (y `add_filter`
// era un no-op). Un check que no puede fallar no es un check.
$GLOBALS['_wp_filters'] = array();
if (!function_exists('apply_filters')) {
    function apply_filters($t, $v, ...$x) {
        foreach ($GLOBALS['_wp_filters'][$t] ?? array() as $nivel) {
            ksort($nivel);
            foreach ($nivel as $cb) { $v = $cb($v, ...$x); }
        }
        return $v;
    }
}
if (!function_exists('remove_all_filters')) { function remove_all_filters($t) { unset($GLOBALS['_wp_filters'][$t]); } }
if (!function_exists('__return_false')) { function __return_false() { return false; } }
if (!function_exists('__return_true')) { function __return_true() { return true; } }
if (!function_exists('do_action')) { function do_action($t, ...$a) {} }
if (!function_exists('add_action')) {
    /**
     * Apunta el hook en un registro pasivo (`_convoca_acciones`). `do_action` no lo consume: esto sirve para decir
     * **qué** se engancha, no para dispararlo.
     */
    function add_action($t, $c, $p = 10, $a = 1) {
        $GLOBALS['_convoca_acciones'][$t][] = $c;
        return true;
    }
}
if (!function_exists('add_filter')) { function add_filter($t, $c, $p = 10, $a = 1) { $GLOBALS['_wp_filters'][$t][$p][] = $c; return true; } }

// Shortcodes — registro mínimo pero real, para poder comprobar qué se pinta.
$GLOBALS['_wp_shortcodes'] = array();
if (!function_exists('add_shortcode')) { function add_shortcode($t, $cb) { $GLOBALS['_wp_shortcodes'][$t] = $cb; return true; } }
if (!function_exists('shortcode_exists')) { function shortcode_exists($t) { return isset($GLOBALS['_wp_shortcodes'][$t]); } }
if (!function_exists('has_shortcode')) {
    function has_shortcode($c, $t) { return is_string($c) && (bool) preg_match('/\[' . preg_quote($t, '/') . '[\s\]]/', $c); }
}
if (!function_exists('do_shortcode')) {
    function do_shortcode($c) {
        return preg_replace_callback('/\[([a-z0-9_]+)([^\]]*)\]/i', function ($m) {
            if (!isset($GLOBALS['_wp_shortcodes'][$m[1]])) { return $m[0]; }
            $atts = array();
            if (preg_match_all('/([a-z0-9_]+)="([^"]*)"/i', $m[2], $pares, PREG_SET_ORDER)) {
                foreach ($pares as $par) { $atts[$par[1]] = $par[2]; }
            }
            return (string) call_user_func($GLOBALS['_wp_shortcodes'][$m[1]], $atts, '', '');
        }, (string) $c);
    }
}

// Contexto de consulta: cada test decide si está en una ficha, en el bucle principal, etc.
$GLOBALS['convoca_test_query'] = array(
    'es_singular' => false, 'en_loop' => true, 'principal' => true, 'queried_id' => 0, 'tema_soporta' => false,
);
if (!function_exists('is_singular')) { function is_singular($t = '') { return (bool) $GLOBALS['convoca_test_query']['es_singular']; } }

// Tipos de los posts usados en los tests: id => post_type.
$GLOBALS['convoca_test_tipos'] = array();
if (!function_exists('get_post_type')) {
    function get_post_type($post = null) {
        $id = is_object($post) ? (int) $post->ID : (int) $post;
        return $GLOBALS['convoca_test_tipos'][$id] ?? false;
    }
}
if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '') {
        $atts = (array) $atts;
        $out = array();
        foreach ($pairs as $name => $default) { $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default; }
        return $out;
    }
}
if (!function_exists('in_the_loop')) { function in_the_loop() { return (bool) $GLOBALS['convoca_test_query']['en_loop']; } }
if (!function_exists('is_main_query')) { function is_main_query() { return (bool) $GLOBALS['convoca_test_query']['principal']; } }
if (!function_exists('get_queried_object_id')) { function get_queried_object_id() { return (int) $GLOBALS['convoca_test_query']['queried_id']; } }
if (!function_exists('current_theme_supports')) { function current_theme_supports($f = '') { return (bool) $GLOBALS['convoca_test_query']['tema_soporta']; } }

// Auth
// Los permisos se pueden fijar por prueba: por defecto se mantiene el «todo permitido»
// de siempre para no cambiar el sentido de los tests que ya existían.
$GLOBALS['convoca_test_caps']  = array();
$GLOBALS['convoca_test_roles'] = array();
if (!function_exists('current_user_can')) {
    function current_user_can($c, ...$a) {
        if (array_key_exists($c, $GLOBALS['convoca_test_caps'])) {
            return (bool) $GLOBALS['convoca_test_caps'][$c];
        }
        return true;
    }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        // Si la prueba ha dado sesión (`_wp_stores['current_user']` o `_test_users`), se devuelve ESA persona:
        // antes el usuario actual SIEMPRE tenía ID 0, así que cualquier comprobación de identidad («¿ya evaluó
        // esta persona?», «¿es suya esta inscripción?») era inmedible.
        if (!empty($GLOBALS['_wp_stores']['current_user']->ID)) {
            return $GLOBALS['_wp_stores']['current_user'];
        }
        $id = (int) ($GLOBALS['convoca_test_current_user_id'] ?? 0);
        if ($id > 0 && !empty($GLOBALS['_test_users'][$id])) {
            return $GLOBALS['_test_users'][$id];
        }
        $u = new WP_User();
        $u->ID = 0;
        $u->roles = (array) $GLOBALS['convoca_test_roles'];
        return $u;
    }
}
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 1; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a = -1) { return 'nonce-' . $a; } }
if (!function_exists('wp_verify_nonce')) {
    /** Con `$GLOBALS['nonce_invalido']` a true se simula el nonce caducado. */
    function wp_verify_nonce($n, $a = -1) {
        unset($n, $a);
        return empty($GLOBALS['nonce_invalido']);
    }
}

// HTTP
if (!function_exists('wp_remote_get')) { function wp_remote_get($u, $a = []) { return ['response' => ['code' => 200], 'body' => '{}']; } }
if (!function_exists('wp_remote_post')) {
    /**
     * POST remoto bajo control de la prueba (`$GLOBALS['convoca_test_http']`): código, error o cuerpo. Se apunta
     * la llamada -URL y argumentos- porque lo que hay que afirmar es **qué** se envía y con qué cabeceras.
     */
    function wp_remote_post($u, $a = []) {
        $GLOBALS['_wp_http_posts'][] = array('url' => $u, 'args' => $a);
        $r = $GLOBALS['convoca_test_http'] ?? array('code' => 200, 'body' => '{}');
        if (!empty($r['error'])) { return new WP_Error('http_request_failed', $r['error']); }
        return array('response' => array('code' => (int) ($r['code'] ?? 200)), 'body' => (string) ($r['body'] ?? '{}'));
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    /** Código HTTP de la respuesta. */
    function wp_remote_retrieve_response_code($r) {
        return is_array($r) ? (int) ($r['response']['code'] ?? 0) : 0;
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    /** Cuerpo de la respuesta HTTP (el proveedor de Meta lo decodifica para leer `id` y `error`). */
    function wp_remote_retrieve_body($r) {
        return is_array($r) ? (string) ($r['body'] ?? '') : '';
    }
}
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
if (!function_exists('get_the_title')) { /** `selected()`: pinta `selected='selected'` si coinciden. Los `<select>` de los filtros lo usan. */
if (!function_exists('selected')) {
    function selected($a, $b = true, $echo = true) {
        $out = ((string) $a === (string) $b) ? " selected='selected'" : '';
        if ($echo) { echo $out; }
        return $out;
    }
}
/** Enlace de edición: la prueba lo fija por id; sin él, `null` (como un post no editable). */
if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($id = 0, $contexto = 'display') { return $GLOBALS['_wp_stores']['edit_links'][(int) $id] ?? null; }
}
/** Añade parámetros a una URL. */
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        $args = is_array($args) ? $args : array($args => func_get_arg(1));
        $sep  = (false === strpos((string) $url, '?')) ? '?' : '&';
        return $url . $sep . http_build_query($args);
    }
}
/** URL con nonce. */
if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($url, $accion = -1, $nombre = '_wpnonce') {
        return $url . (false === strpos((string) $url, '?') ? '?' : '&') . $nombre . '=nonce-' . $accion;
    }
}
if (!function_exists('get_bloginfo')) {
    /** Datos del sitio: se siembran en `_wp_stores['bloginfo']`. */
    function get_bloginfo($que = 'name', $filtro = 'raw') {
        unset($filtro);
        $datos = array('name' => 'Convoca', 'description' => 'Asociación', 'url' => 'https://example.test', 'admin_email' => 'hola@example.test');
        return $GLOBALS['_wp_stores']['bloginfo'][ $que ] ?? ( $datos[ $que ] ?? '' );
    }
}
if (!function_exists('checked')) {
    /** Marca el atributo `checked` comparando (como el de WordPress). */
    function checked($a, $b = true, $echo = true) {
        $si = ( is_array( $b ) ? in_array( $a, $b, true ) : (string) $a === (string) $b );
        $salida = $si ? " checked='checked'" : '';
        if ( $echo ) { echo $salida; }
        return $salida;
    }
}
if (!function_exists('esc_textarea')) { function esc_textarea($t) { return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('wp_nonce_field')) {
    /** Campo oculto del nonce, con el nombre que se le pida (el de por defecto es `_wpnonce`). */
    function wp_nonce_field($a = -1, $n = '_wpnonce', $ref = true, $echo = true) {
        $s = '<input type="hidden" name="' . $n . '" value="nonce">';
        if ($echo) { echo $s; }
        return $s;
    }
}

if (!function_exists('wp_editor')) {
    /** Editor clásico: en la prueba basta con el área de texto y su contenido. */
    function wp_editor($contenido, $id, $ajustes = array()) {
        unset($ajustes);
        echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($id) . '">' . esc_textarea((string) $contenido) . '</textarea>';
    }
}

if (!function_exists('wp_generate_password')) {
    /** Contraseña/token aleatorio: basta con que sea largo y distinto cada vez. */
    function wp_generate_password($largo = 12, $especial = true, $extra = false) {
        unset($especial, $extra);
        // Sin 0/O ni 1/I: son los caracteres que el producto evita porque se confunden al leerlos de un papel.
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $salida   = '';
        for ($i = 0; $i < (int) $largo; $i++) { $salida .= $alfabeto[ random_int(0, strlen($alfabeto) - 1) ]; }
        return $salida;
    }
}

if (!function_exists('esc_url_raw')) {
    /** URL limpia: solo http(s) (y rutas relativas); un `javascript:` no pasa. */
    function esc_url_raw($u) {
        $u = trim((string) $u);
        if ('' === $u) { return ''; }
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $u) && !preg_match('#^https?://#i', $u)) { return ''; }
        return $u;
    }
}
if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($t) { return trim(strip_tags((string) $t)); } }

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($h, $s = '', $d = array(), $v = '', $pie = false) {
        unset($d, $v, $pie);
        $GLOBALS['_wp_encolados']['scripts'][] = $h;
    }
}
if (!function_exists('wp_localize_script')) { function wp_localize_script($h, $n, $d) { $GLOBALS['_wp_encolados']['localizados'][$n] = $d; return true; } }
if (!function_exists('wp_enqueue_media')) { function wp_enqueue_media($a = array()) { $GLOBALS['_wp_encolados']['media'] = true; } }
if (!function_exists('wp_add_dashboard_widget')) { function wp_add_dashboard_widget($id, $t, $cb = null) { $GLOBALS['_wp_widgets'][] = array('id' => $id, 'titulo' => $t); } }

if (!class_exists('WP_Query')) {
    /** Consulta: se apoya en `get_posts()` del arnés para que los filtros de la prueba sigan valiendo. */
    class WP_Query {
        /** Posts. */
        public $posts = array();
        /** Total. */
        public $found_posts = 0;

        /**
         * Constructor.
         *
         * @param array $args Argumentos.
         */
        public function __construct($args = array()) {
            $this->posts = get_posts($args);
            // Con `fields => 'ids'` WordPress devuelve ids, no objetos: el producto hace `(int) $post`.
            if (isset($args['fields']) && 'ids' === $args['fields']) {
                $this->posts = array_map(static function ($p) { return is_object($p) ? (int) $p->ID : (int) $p; }, $this->posts);
            }
            $this->found_posts = count($this->posts);
        }
    }
}

if (!function_exists('get_pages')) {
    /** Páginas del sitio: se siembran en `_wp_stores['pages']` (por omisión, ninguna). */
    function get_pages($args = array()) {
        unset($args);
        return (array) ($GLOBALS['_wp_stores']['pages'] ?? array());
    }
}

if (!function_exists('wp_schedule_single_event')) {
    /** Evento único de cron: se apunta como los demás. */
    function wp_schedule_single_event($marca, $hook, $args = array()) {
        $GLOBALS['_wp_cron']['programados'][ $hook ][] = array( 'marca' => $marca, 'args' => $args );
        return true;
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt($id = 0) {
        unset($id);
        return (string) ($GLOBALS['_wp_stores']['excerpt'] ?? '');
    }
}

if (!function_exists('get_the_ID')) { function get_the_ID() { return (int) ($GLOBALS['_wp_current_id'] ?? 0); } }

if (!function_exists('update_meta_cache')) { function update_meta_cache($tipo = 'post', $ids = array()) { unset($tipo); return is_array($ids) ? count($ids) : 0; } }

if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
if (!defined('ARRAY_N')) { define('ARRAY_N', 'ARRAY_N'); }
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }

if (!function_exists('number_format_i18n')) {
    /** Número con separador decimal del sitio: coma, como en español. */
    function number_format_i18n($numero, $decimales = 0) {
        return number_format((float) $numero, (int) $decimales, ',', '.');
    }
}

if (!function_exists('attachment_url_to_postid')) {
    function attachment_url_to_postid($url) {
        return (int) ($GLOBALS['_wp_attachment_ids'][ (string) $url ] ?? 0);
    }
}

if (!function_exists('_n')) {
    /** Plural de WordPress: el singular para uno, el plural para el resto. */
    function _n($singular, $plural, $numero = 0, $dominio = null) {
        unset($dominio);
        return 1 === (int) $numero ? $singular : $plural;
    }
}

if (!function_exists('get_site_icon_url')) {
    /** Icono del sitio: lo fija la prueba con `convoca_test_site_icon`. */
    function get_site_icon_url($size = 512) {
        unset($size);
        return (string) ($GLOBALS['convoca_test_site_icon'] ?? '');
    }
}
if (!function_exists('get_query_var')) {
    /** Variables de la consulta: se fijan en `$GLOBALS['_wp_query_vars']`. */
    function get_query_var($var, $defecto = '') {
        return $GLOBALS['_wp_query_vars'][ $var ] ?? $defecto;
    }
}

if (!function_exists('language_attributes')) { function language_attributes() { echo 'lang="es-ES"'; } }
if (!function_exists('body_class')) { function body_class($c = '') { return 'class="' . esc_attr((string) $c) . '"'; } }

if (!class_exists('WP_List_Table')) {
    /**
     * Tabla de administración de WordPress: el mínimo que usan las pantallas. Existe para que los ficheros que la
     * requieren (`ABSPATH . wp-admin/includes/...`) no intenten abrir un fichero que aquí no está.
     */
    class WP_List_Table {
        /** Filas de la tabla. */
        public $items = array();

        /** Cabeceras de columna (el real las declara; asignarla sin declarar era deprecado en PHP 8.5). */
        public $_column_headers = array();

        /**
         * Constructor.
         *
         * @param array $args Argumentos.
         */
        public function __construct($args = array()) {
            unset($args);
        }

        /**
         * Columnas.
         *
         * @return array
         */
        public function get_columns() { return array(); }

        /**
         * Pinta la tabla.
         *
         * @return void
         */
        public function display() {}

        /**
         * Acciones de fila (el real las pinta bajo el título).
         *
         * @param array $acciones Acciones.
         * @return string
         */
        public function row_actions($acciones = array()) {
            return '<div class="row-actions">' . implode(' | ', (array) $acciones) . '</div>';
        }

        /**
         * Página actual del listado.
         *
         * @return int
         */
        public function get_pagenum() {
            return max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        }

        /**
         * Argumentos de paginación.
         *
         * @param array $args Argumentos.
         * @return void
         */
        public function set_pagination_args( $args ) {
            $GLOBALS['_wp_paginacion'] = $args;
        }

        /**
         * Caja de búsqueda.
         *
         * @param string $texto      Texto del botón.
         * @param string $input_id   Id del campo.
         * @return void
         */
        public function search_box( $texto, $input_id ) {
            unset( $texto );
            echo '<input type="search" name="s" id="' . esc_attr( $input_id ) . '">';
        }
    }
}

if (!function_exists('submit_button')) {
    function submit_button($texto = 'Guardar', $tipo = 'primary', $nombre = 'submit', $wrap = true, $otros = array()) {
        unset($tipo, $otros);
        $boton = '<button type="submit" name="' . esc_attr($nombre) . '" class="button">' . esc_html($texto) . '</button>';
        if ($wrap) { echo $boton; }
        return $boton;
    }
}

if (!function_exists('esc_js')) {
    /** Escapa para JavaScript (dentro de comillas simples o dobles). */
    function esc_js($texto) {
        return str_replace(array("\\", "'", '"', "\n", "\r", '<', '>', '&'), array('\\\\', "\\'", '&quot;', '\\n', '\\r', '&lt;', '&gt;', '&amp;'), (string) $texto);
    }
}

/** Corta el flujo cuando el producto responde por ajax (el real acaba en `wp_die`). */
/**
 * Corta el flujo como el `wp_die()` real de `wp_send_json_*`.
 *
 * Extiende **`Error`, no `Exception`**: el `wp_die()` de WordPress no es una excepcion, y un producto con
 * `try { ... wp_send_json_success(); ... } catch ( \Exception $e )` se tragaba nuestro corte y respondia **error**
 * despues de haber respondido bien. Es el mismo motivo por el que `exit` no se puede capturar.
 */
class Convoca_Json_Cortado extends \Error {

    /** Respuesta enviada. */
    public $respuesta = null;

    /**
     * Constructor.
     *
     * @param mixed $respuesta Respuesta.
     */
    public function __construct( $respuesta = null ) {
        parent::__construct( 'json' );
        $this->respuesta = $respuesta;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($datos = null, $codigo = 200) {
        unset($codigo);
        $GLOBALS['_wp_json'][] = array('ok' => true, 'data' => $datos);
        throw new Convoca_Json_Cortado(array('ok' => true, 'data' => $datos));
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($datos = null, $codigo = 200) {
        unset($codigo);
        $GLOBALS['_wp_json'][] = array('ok' => false, 'data' => $datos);
        throw new Convoca_Json_Cortado(array('ok' => false, 'data' => $datos));
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($accion = -1, $campo = '_wpnonce') {
        unset($campo);
        if (!empty($GLOBALS['nonces_invalidos'][ $accion ])) {
            throw new Convoca_Json_Cortado('nonce');
        }
        return true;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($accion = -1, $campo = false, $morir = true) {
        unset($campo, $morir);
        if (!empty($GLOBALS['nonces_invalidos'][ $accion ])) {
            throw new Convoca_Json_Cortado('nonce');
        }
        return true;
    }
}

/** Quita claves de la URL actual (los botones «Volver» y «Panel normal» del CRM). */
if (!function_exists('remove_query_arg')) {
    function remove_query_arg($claves, $url = '') {
        $claves = (array) $claves;
        $url    = (string) $url;
        if ('' === $url) { $url = $GLOBALS['_test_url_actual'] ?? 'https://example.test/wp-admin/admin.php'; }
        $partes = parse_url($url);
        $query  = array();
        if (!empty($partes['query'])) {
            parse_str($partes['query'], $query);
            foreach ($claves as $c) { unset($query[$c]); }
        }
        $base = ($partes['scheme'] ?? 'https') . '://' . ($partes['host'] ?? 'example.test') . ($partes['path'] ?? '');

        return empty($query) ? $base : $base . '?' . http_build_query($query);
    }
}
function get_the_title($id = 0) {
        // Acepta id **o** el propio post, como WordPress: pasar el objeto es lo normal en los temas y en el motor
        // de carteles (`get_the_title( $post )`), y castearlo a int reventaba.
        if (is_object($id)) { return (string) ($id->post_title ?? ''); }
        // El título del post del almacén manda; «Post N» es el respaldo cuando la prueba no lo ha sembrado.
        $p = $GLOBALS['_wp_stores']['posts'][(int) $id] ?? null;
        if (is_object($p) && isset($p->post_title) && '' !== (string) $p->post_title) { return (string) $p->post_title; }

        return "Post $id";
    } }
if (!function_exists('get_post_status')) { function get_post_status($id) { return 'publish'; } }
if (!function_exists('get_post')) {
    // El tipo se puede fijar por id con `$GLOBALS['convoca_test_post_types']`; por
    // defecto sigue siendo 'post', como antes.
    function get_post($id = null) {
        if (!$id) return null;
        // El post del almacén manda: inventar siempre «Actividad {id}» de tipo `post` hacía que las pantallas de una
        // actividad dijeran «no encontrada» teniendo el post sembrado con su título.
        if (isset($GLOBALS['_wp_stores']['posts'][(int) $id])) { return $GLOBALS['_wp_stores']['posts'][(int) $id]; }
        // Un id que no está en el almacén **no existe**: `get_post()` devuelve null, como WordPress. Inventarlo hacía
        // que las guardas «si no hay post, no hagas nada» fueran decorativas.
        return null;
    }
}
if (!function_exists('post_type_exists')) {
    /** ¿Existe el tipo? La prueba puede fijar la lista con `convoca_test_post_types`. */
    function post_type_exists($t) {
        $tipos = $GLOBALS['convoca_test_post_types'] ?? ['post','page','actividad','miembro','inscripcion'];
        return in_array((string) $t, $tipos, true);
    }
}
if (!function_exists('wp_insert_post')) {
    /** Alta: devuelve un id nuevo y lo apunta (`_wp_posts_creados`) para poder afirmar sobre él. */
    function wp_insert_post($d) {
        // Una prueba puede pedir que el alta falle o reviente: es el único modo de recorrer el `catch`
        // de la transacción sin tocar la base.
        if (!empty($GLOBALS['_wp_insert_lanza'])) { throw new RuntimeException('fallo simulado al insertar'); }
        if (!empty($GLOBALS['_wp_insert_falla'])) { return 0; }
        static $c = 100;
        $c++;
        $GLOBALS['_wp_posts_creados'][] = $c;
        $GLOBALS['_wp_insert_args'][]   = $d;
        return $c;
    }
}
if (!function_exists('wp_update_post')) {
    /** Actualiza: apunta los datos y los aplica al post del almacén. */
    function wp_update_post($d) {
        $GLOBALS['_wp_post_updates'][] = $d;
        $id = (int) ($d['ID'] ?? 0);
        if ($id && isset($GLOBALS['_wp_stores']['posts'][ $id ])) {
            foreach ($d as $campo => $valor) {
                if ('ID' !== $campo) { $GLOBALS['_wp_stores']['posts'][ $id ]->$campo = $valor; }
            }
        }
        return $id ?: wp_insert_post($d);
    }
}
if (!function_exists('is_user_logged_in')) {
    /**
     * ¿Hay sesión? La prueba la fija en `_wp_stores['current_user']` (WP_User con ID).
     *
     * @return bool
     */
    function is_user_logged_in() {
        return !empty($GLOBALS['_wp_stores']['current_user']->ID);
    }
}

if (!function_exists('current_datetime')) {
    /**
     * Fecha y hora actuales del sitio (el `DateTimeImmutable` que devuelve WordPress).
     *
     * @return DateTimeImmutable
     */
    function current_datetime() {
        return new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
    }
}

if (!function_exists('plugins_url')) {
    /**
     * URL dentro de plugins/.
     *
     * @param string $ruta Ruta relativa.
     * @param string $base Base.
     * @return string
     */
    function plugins_url($ruta = '', $base = '') {
        unset($base);
        return 'https://example.test/wp-content/plugins/convoca-enroll/' . ltrim((string) $ruta, '/');
    }
}

if (!function_exists('_convoca_meta_encaja')) {
    /**
     * ¿La meta de una entrada cumple la consulta? Entiende `=`, `!=`, `LIKE`, `EXISTS`, `NOT EXISTS`, `IN` y
     * `BETWEEN` (fechas), con `relation` AND/OR — que es lo que usan las consultas del plugin.
     *
     * @param int   $id Entrada.
     * @param array $mq Consulta de meta.
     * @return bool
     */
    function _convoca_meta_encaja($id, $mq) {
        $mq = (array) $mq;
        $relacion = strtoupper((string) ($mq['relation'] ?? 'AND'));

        $resultados = array();
        foreach ($mq as $clave => $clausula) {
            if ('relation' === $clave || !is_array($clausula) || empty($clausula['key'])) { continue; }
            $meta  = $GLOBALS['_wp_stores']['post_meta'][$id] ?? array();
            $tiene = array_key_exists((string) $clausula['key'], $meta);
            $valor = $tiene ? $meta[(string) $clausula['key']] : null;
            $esperado = $clausula['value'] ?? null;
            $comp = strtoupper((string) ($clausula['compare'] ?? (is_array($esperado) ? 'IN' : '=')));

            if ('NOT EXISTS' === $comp) { $resultados[] = !$tiene; continue; }
            if ('EXISTS' === $comp) { $resultados[] = $tiene; continue; }
            if ('BETWEEN' === $comp) {
                $v = $tiene ? strtotime((string) $valor) : false;
                $resultados[] = $v && is_array($esperado) && $v >= strtotime((string) $esperado[0]) && $v <= strtotime((string) $esperado[1]);
                continue;
            }
            if ('IN' === $comp) { $resultados[] = $tiene && in_array((string) $valor, array_map('strval', (array) $esperado), true); continue; }
            if ('!=' === $comp) { $resultados[] = !$tiene || (string) $valor !== (string) $esperado; continue; }
            if ('LIKE' === $comp) { $resultados[] = $tiene && false !== strpos((string) $valor, (string) $esperado); continue; }
            $resultados[] = $tiene && (string) $valor === (string) $esperado;
        }

        if (empty($resultados)) { return true; }

        return 'OR' === $relacion ? in_array(true, $resultados, true) : !in_array(false, $resultados, true);
    }
}

if (!function_exists('get_posts')) {
    /**
     * Posts según lo que fije la prueba en `convoca_test_posts`; por defecto, ninguno — que es lo
     * que el resto de pruebas de este arnés espera.
     */
    function get_posts($a) {
        $GLOBALS['convoca_test_get_posts_args'][] = $a;
        // Cola de respuestas: dos consultas distintas en la misma función (correo y luego DNI) no pueden ver lo mismo.
        if (isset($GLOBALS['convoca_test_get_posts_cola']) && is_array($GLOBALS['convoca_test_get_posts_cola'])) {
            return (array) (array_shift($GLOBALS['convoca_test_get_posts_cola']) ?? array());
        }
        $todo = $GLOBALS['convoca_test_posts'] ?? array();
        if (empty($todo)) { return array(); }
        // Se devuelve **lo que la prueba fijó**, tal cual: ids si son ids, objetos si son objetos. Convertir los
        // objetos a id dejaba a los informes leyendo `->ID` de un entero (filas vacías).
        $salida = array();
        foreach ((array) $todo as $post) {
            // Si el post dice su tipo, se respeta el `post_type` de la consulta: dos `WP_Query` seguidas (actividades
            // y luego inscripciones) tienen que ver cosas distintas.
            $tipo = is_object($post) ? (string) ($post->post_type ?? '') : '';
            if ('' !== $tipo && !empty($a['post_type']) && $a['post_type'] !== $tipo) { continue; }
            // `meta_query`: por defecto el doble devuelve todo lo del tipo (32 pruebas del repo dependen de eso
            // hoy, ver enroll#44). Con `convoca_test_meta_estricto` se filtra por meta, que es el contrato real.
            if (!empty($a['meta_query']) && is_object($post) && !empty($GLOBALS['convoca_test_meta_estricto'])) {
                if (!_convoca_meta_encaja((int) $post->ID, (array) $a['meta_query'])) { continue; }
            }
            $salida[] = $post;
        }
        // `fields => 'ids'`: WordPress devuelve solo identificadores. Sin esto, quien recorre el resultado
        // esperando un id recibía un objeto (y las tareas de mantenimiento que borran no se podían probar).
        if (isset($a['fields']) && 'ids' === $a['fields']) {
            return array_map(static fn($p) => (int) (is_object($p) ? $p->ID : $p), $salida);
        }
        return $salida;
    }
}

// URLs
if (!function_exists('home_url')) { function home_url($p = '') { return "https://example.com$p"; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return "/wp-admin/$p"; } }
if (!function_exists('get_post_type_archive_link')) {
    // El archivo del CPT: por defecto existe, y se puede apagar con
    // `$GLOBALS['convoca_test_archive_link'] = ''` para probar el caso sin listado.
    function get_post_type_archive_link($t) {
        return $GLOBALS['convoca_test_archive_link'] ?? "https://example.com/{$t}/";
    }
}

// Registro de menús: se captura en vez de pintarse, para poder comprobar a qué
// pantalla apunta cada entrada sin arrancar WordPress.
$GLOBALS['_convoca_menu_prueba']    = array();
$GLOBALS['_convoca_submenu_prueba'] = array();
if (!function_exists('add_menu_page')) {
    function add_menu_page(...$a) { $GLOBALS['_convoca_menu_prueba'][] = $a; return (string) ($a[3] ?? ''); }
}
if (!function_exists('add_submenu_page')) {
    function add_submenu_page(...$a) { $GLOBALS['_convoca_submenu_prueba'][] = $a; return (string) ($a[4] ?? ''); }
}
if (!function_exists('wp_safe_redirect')) {
    /**
     * En producción el `exit` inmediato termina la petición: aquí se corta igual, para poder afirmar que se redirige
     * sin que el proceso de PHPUnit muera.
     */
    function wp_safe_redirect($u, $s = 302) {
        $GLOBALS['_convoca_redirects'][] = array($u, $s);
        throw new Convoca_Json_Cortado(array('redirect' => $u));
    }
}
if (!function_exists('wp_redirect')) { function wp_redirect($u, $s = 302) { $GLOBALS['_convoca_redirects'][] = array($u, $s); return true; } }
if (!function_exists('plugin_basename')) { function plugin_basename($f) { return basename($f); } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return dirname($f) . '/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f) { return 'https://example.com/wp-content/plugins/' . basename(dirname($f)) . '/'; } }

// Schedule
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h) { return false; } }
if (!function_exists('wp_schedule_event')) { function wp_schedule_event($ts, $r, $h, $a = []) { return true; } }
if (!function_exists('wp_clear_scheduled_hook')) { function wp_clear_scheduled_hook($h) { return true; } }

// Misc
if (!function_exists('register_post_type')) {
    /** Apunta el tipo y sus argumentos: hay que poder afirmar cómo se registra, no solo que no explota. */
    function register_post_type($s, $a) {
        $GLOBALS['_convoca_tipos_registrados'][$s] = $a;
        return null;
    }
}
if (!function_exists('add_meta_box')) {
    /** Apunta la caja: hay que poder afirmar dónde se pinta y con qué callback. */
    function add_meta_box($id, $titulo, $cb, $pantalla = 'post', $contexto = 'normal', $prioridad = 'default') {
        $GLOBALS['_convoca_cajas'][] = compact('id', 'titulo', 'cb', 'pantalla', 'contexto', 'prioridad');
        return null;
    }
}

if (!function_exists('register_post_meta')) {
    /** Apunta las metas registradas por tipo: hay que poder afirmar **cómo** se registran, no solo que no explota. */
    function register_post_meta($t, $k, $a) {
        $GLOBALS['_convoca_metas_registrados'][ $t ][ $k ] = $a;
        return true;
    }
}
if (!function_exists('register_taxonomy')) { function register_taxonomy($s, $t, $a) { return null; } }
if (!function_exists('register_rest_route')) {
    /**
     * Apunta las rutas en vez de descartarlas: una prueba tiene que poder decir qué endpoints hay,
     * con qué método y con qué permiso.
     */
    function register_rest_route($n, $r, $a = array()) {
        $GLOBALS['_enr_routes'][] = array_merge(array('namespace' => $n, 'route' => $r), (array) $a);
        return true;
    }
}
if (!function_exists('wp_redirect')) { function wp_redirect($u) {} }
if (!function_exists('wp_die')) {
    /**
     * `wp_die` termina la petición: aquí corta con la excepción del arnés. Devolver sin más dejaba que el código
     * siguiera ejecutándose después de una guarda, que es justo lo que no pasa en producción.
     */
    function wp_die($m = '', $t = '', $a = []) {
        unset($t, $a);
        throw new Convoca_Json_Cortado(array('die' => $m));
    }
}
if (!function_exists('wp_cache_delete')) { function wp_cache_delete($k, $g = '') { return true; } }
if (!function_exists('flush_rewrite_rules')) { function flush_rewrite_rules() {} }
if (!function_exists('remove_action')) { function remove_action($h, $c, $p = 10) { return true; } }
if (!function_exists('load_plugin_textdomain')) { function load_plugin_textdomain($d, $dep, $p) {} }
if (!function_exists('get_user_by')) {
    /** Usuario por campo: la prueba siembra en `convoca_test_users[email]`. Sin siembra, no hay. */
    function get_user_by($campo, $valor) {
        if ('email' === $campo) { return $GLOBALS['convoca_test_users'][ (string) $valor ] ?? false; }
        return false;
    }
}
if (!function_exists('get_users')) {
    /** Lista de usuarios que fije la prueba en `convoca_test_users_lista`. */
    function get_users($a = []) { return (array) ($GLOBALS['convoca_test_users_lista'] ?? []); }
}
if (!function_exists('get_userdata')) {
    /** Usuario con los roles que fije la prueba (por defecto, suscriptor). */
    function get_userdata($id) {
        if ($id <= 0) return false;
        // Si la prueba dio de alta la persona, se usa ESA (roles, capacidades, nombre y correo). Antes
        // se devolvía siempre un usuario inventado, así que ninguna regla de permisos sobre usuarios
        // era medible: la prueba pasaba con un usuario que existía solo dentro del arnés.
        if (!empty($GLOBALS['_test_users'][(int) $id])) {
            return $GLOBALS['_test_users'][(int) $id];
        }
        $u = new stdClass();
        $u->ID = $id;
        $u->display_name = "User $id";
        $u->user_email = "user$id@test.com";
        // El `WP_User` real trae estas propiedades (las lee el compromiso y el exportador de evaluaciones).
        $u->first_name = '';
        $u->last_name = '';
        $u->user_login = "user$id";
        // El `WP_User` real trae roles: sin ellos, cualquier producto que pregunte por un rol revienta.
        $u->roles = array('administrator');
        $u->user_nicename = "user$id";
        $u->roles = (array) ($GLOBALS['convoca_test_roles'][ $id ] ?? array( 'subscriber' ));
        return $u;
    }
}
if (!function_exists('get_current_screen')) {
    /** Pantalla actual: la prueba la fija en `$GLOBALS['_wp_screen']` (por omisión, `dashboard`). */
    function get_current_screen() {
        $screen      = new stdClass();
        $screen->id  = $GLOBALS['_wp_screen'] ?? 'dashboard';
        return $screen;
    }
}
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style($h, $s = '', $d = [], $v = '', $m = 'all') {} }
if (!function_exists('wp_register_style')) { function wp_register_style($h, $s, $d = [], $v = '', $m = 'all') { return true; } }
if (!function_exists('wp_set_script_translations')) { function wp_set_script_translations($h, $d, $p) {} }
if (!function_exists('wp_kses_post')) {
    /**
     * Como el real en lo que importa aquí: el HTML permitido se queda y un `<script>` (o un `on*=`) no pasa.
     * Devolver el texto tal cual hacía que ninguna prueba de XSS pudiera fallar.
     */
    function wp_kses_post($s) {
        $s = preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) $s);
        $s = preg_replace('#<style\b[^>]*>.*?</style>#is', '', (string) $s);
        return preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $s);
    }
}
if (!function_exists('wp_rand')) { function wp_rand($min = 0, $max = PHP_INT_MAX) { return rand($min, $max); } }
if (!function_exists('user_can')) {
    /**
     * Permisos que la prueba puede negar por capacidad. Por defecto concede, como antes.
     */
    function user_can($user, $cap) {
        unset($user);
        return (bool) ($GLOBALS['convoca_test_user_can'][ $cap ] ?? true);
    }
}

// WP_Error
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = []; private $error_data = [];
        public function __construct($code = '', $message = '', $data = '') {
            if ($code) { $this->errors[$code] = [$message]; $this->error_data[$code] = $data; }
        }
        /**
         * Datos del error (status HTTP y demás), como el `WP_Error` real.
         *
         * @param int|string $codigo Código.
         * @return mixed
         */
        public function get_error_data($codigo = '') {
            if ('' === $codigo) { return $this->error_data; }
            return $this->error_data[ $codigo ] ?? null;
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
        public $post_excerpt = ''; public $post_date = '';
        // Las usa la clave de caché de los carteles: crearlas al vuelo es deprecado en PHP 8.5.
        public $post_modified = '';
    }
}

// WP_User
if (!class_exists('WP_User')) {
    class WP_User {
        public $ID = 0;
        public $roles = ['administrator'];
        /** Nombre visible y correo: los tiene el `WP_User` real y las pruebas los fijan. */
        public $display_name = '';
        public $user_email = '';
        /** Capacidades sueltas (las que no vienen del rol). */
        public $caps = [];
        public function exists() { return $this->ID > 0; }

        /**
         * ¿Tiene la capacidad? El `WP_User` real la trae; sin ella, cualquier producto que pregunte
         * por una capacidad del usuario reventaba al probarse.
         *
         * @param string $cap Capacidad.
         * @return bool
         */
        public function has_cap($cap) { return ! empty($this->caps[(string) $cap]); }
    }
}

// $wpdb
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_'; public $posts = 'wp_posts'; public $users = 'wp_users'; public $usermeta = 'wp_usermeta';
        public $postmeta = 'wp_postmeta';
        public $options = 'wp_options'; public $rows_affected = 1; // filas afectadas por la última consulta, como `wpdb`.
        public $insert_id = 42;
        /**
         * Respuestas por consulta: `get_var_mapa`/`results_mapa` casan por aguja en el SQL. Sin esto, cualquier
         * informe que cuente filas devolvía siempre `'0'` y sus series salían a cero: la prueba pasaba sin medir.
         */
        public function get_var($q = null, $x = 0, $y = 0) {
            $sql = (string) (is_string($q) ? $q : '');
            // Cola: dos consultas distintas en la misma función (antes y dentro de la transacción) no ven lo mismo.
            if (isset($GLOBALS['_wp_stores']['get_var_cola']) && is_array($GLOBALS['_wp_stores']['get_var_cola'])) {
                return array_shift($GLOBALS['_wp_stores']['get_var_cola']);
            }
            foreach (($GLOBALS['_wp_stores']['get_var_mapa'] ?? []) as $aguja => $valor) {
                if ('' !== $aguja && false !== strpos(str_replace('\\\\', '', $sql), (string) $aguja)) { return $valor; }
            }
            if (is_string($q) && preg_match("/SHOW TABLES LIKE '?([A-Za-z0-9_]+)'?/i", $q, $m)) { return $m[1]; }

            return (string) ($GLOBALS['_wp_stores']['get_var'] ?? '0');
        }
        public function get_results($q = null, $o = 'OBJECT') {
            $sql = (string) (is_string($q) ? $q : '');
            $GLOBALS['db_reads'][] = $sql;
            foreach (($GLOBALS['_wp_stores']['results_mapa'] ?? []) as $aguja => $filas) {
                if ('' !== $aguja && false !== strpos($sql, (string) $aguja)) { return $filas; }
            }

            return (array) ($GLOBALS['_wp_stores']['results'] ?? []);
        }
        public function get_col($q = null, $x = 0) {
            $GLOBALS['db_reads'][] = (string) $q;
            if (!empty($GLOBALS['_wp_stores']['col_cola'])) { return array_shift($GLOBALS['_wp_stores']['col_cola']); }

            return (array) ($GLOBALS['_wp_stores']['col'] ?? []);
        }
        public function get_row($q = null) {
            foreach (($GLOBALS['_wp_stores']['get_row_mapa'] ?? []) as $aguja => $valor) {
                if (false !== strpos((string) $q, (string) $aguja)) { return $valor; }
            }

            return null;
        }
        public function query($q) {
            $GLOBALS['_wp_stores']['queries'][] = (string) $q;
            $lanza = $GLOBALS['convoca_test_query_lanza'] ?? false;
            if (true === $lanza || (is_string($lanza) && false !== strpos((string) $q, $lanza))) {
                throw new RuntimeException('fallo simulado en la consulta');
            }
            // Retorno por patrón: el UPDATE de plazas tiene que poder devolver 0 («no había plazas»).
            foreach ((array) ($GLOBALS['convoca_test_query_retorno'] ?? []) as $aguja => $valor) {
                if (false !== strpos((string) $q, (string) $aguja)) { return $valor; }
            }

            return 1;
        }
        /** `esc_like()`: escapa `%` y `_` para un LIKE, como WordPress. */
        public function esc_like($texto) { return addcslashes((string) $texto, '_%\\\\'); }
        public function insert($t, $d, $f = []) {
            // Se apunta la fila: sin esto no se puede afirmar **qué** se ha encolado (a quién, con qué asunto).
            $GLOBALS['db_inserts'][] = array('tabla' => $t, 'datos' => $d, 'formatos' => $f);
            $this->insert_id = 42;
            return 1;
        }
        public function update($t, $d, $w) {
            // Se apunta qué se actualiza y con qué: sin esto no se puede afirmar que el reintento vuelve a «pending».
            $GLOBALS['db_updates'][] = array('tabla' => $t, 'datos' => $d, 'donde' => $w);
            return 1;
        }
        public function delete($t, $w) {
            // Se apunta qué se borra y de dónde: un `delete` que no deja rastro no permite afirmar que la baja
            // de una cuenta borra la fila correcta.
            $GLOBALS['db_deletes'][] = array('tabla' => $t, 'donde' => $w);
            return isset($GLOBALS['convoca_test_delete_afectadas']) ? (int) $GLOBALS['convoca_test_delete_afectadas'] : 1;
        }
        public function prepare($q, ...$a) {
            if (1 === count($a) && is_array($a[0])) { $a = array_values($a[0]); }
            $i = 0;
            return preg_replace_callback(
                '/%%|%[sdf]/',
                function ($m) use (&$i, $a) {
                    if ('%%' === $m[0]) { return '%'; }
                    $v = $a[$i++] ?? '';
                    if ('%s' !== $m[0]) { return (string) $v; }

                    return "'" . addslashes((string) $v) . "'";
                },
                (string) $q
            );
        }
        public function escape($d) { return addslashes($d); }
        public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
    };
    // Se guarda una referencia: alguna prueba sustituye `$GLOBALS['wpdb']` por un doble mudo y, a partir de ahí,
    // las siguientes leen `[]` sin saber por qué. Las que necesitan el doble completo lo restauran.
    $GLOBALS['_wpdb_doble'] = $GLOBALS['wpdb'];
}

// WP_REST_Response
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data; private $status; private $headers = array();
        public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
        /** Cabeceras de la respuesta: el listado pagina con X-WP-Total y compañía. */
        public function header($k, $v) { $this->headers[$k] = $v; return $this; }
        public function get_headers() { return $this->headers; }
    }
}


// Lo que le falta a enroll para probar su API.
if (!function_exists('get_permalink')) {
    /**
     * Enlace de una entrada, con el prefijo que fije la prueba.
     */
    function get_permalink($p = 0) {
        $id = is_object($p) ? ($p->ID ?? 0) : (int) $p;
        return 'https://example.org/?p=' . $id;
    }
}
if (!function_exists('get_the_date')) {
    /**
     * Fecha de una entrada, la que fije la prueba.
     */
    function get_the_date($formato = '', $p = null) {
        unset($formato, $p);
        return '2026-10-23 18:00';
    }
}
if (!class_exists('WP_REST_Request')) {
    /**
     * Petición de mentira: los parámetros que la prueba le meta.
     */
    class WP_REST_Request implements ArrayAccess {
        private $params;
        private $headers = array();
        public function __construct($params = array()) { $this->params = (array) $params; }
        public function get_param($k) { return $this->params[$k] ?? null; }
        public function get_params() { return $this->params; }
        public function set_param($k, $v) { $this->params[$k] = $v; }
        // `get_header()` devolvía siempre '' y `set_json_params()` no existía: la mitad de las guardas de las
        // rutas REST (cabeceras y cuerpo JSON) no se podían probar.
        public function get_header($k) { return (string) ($this->headers[ strtolower((string) $k) ] ?? ''); }
        public function set_header($k, $v) { $this->headers[ strtolower((string) $k) ] = (string) $v; }
        public function set_json_params($p) { $this->params = (array) $p; }
        public function get_json_params() { return $this->params; }
        // WordPress permite `$req['id']`, y el código de la API lo usa: sin esto, la petición no se lee.
        public function offsetExists($k): bool { return isset($this->params[$k]); }
        #[\ReturnTypeWillChange]
        public function offsetGet($k) { return $this->params[$k] ?? null; }
        public function offsetSet($k, $v): void { $this->params[$k] = $v; }
        public function offsetUnset($k): void { unset($this->params[$k]); }
    }
}

// Load Composer autoloader LAST
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

date_default_timezone_set('Europe/Madrid');

// Dobles de contratos que viven en el núcleo (el CI clona este repositorio solo).
require_once __DIR__ . '/StubMailer.php';
require_once __DIR__ . '/dobles/member-identity.php';
require_once __DIR__ . '/dobles/license-manager.php';
require_once __DIR__ . '/dobles/email-layout.php';
require_once __DIR__ . '/dobles/google-client.php';
} // namespace


namespace Convoca\Enroll {
    if (!function_exists('Convoca\\Enroll\\checkdnsrr')) {
        /**
         * Resolución DNS controlada por la prueba: `$GLOBALS['convoca_test_dns']` con la lista de dominios que sí
         * resuelven. Sin lista, `.test` no resuelve y el resto sí — nunca se sale a la red desde una prueba.
         *
         * @param string $dominio Dominio.
         * @param string $tipo    MX|A.
         * @return bool
         */
        function checkdnsrr($dominio, $tipo = 'MX') {
            $lista = $GLOBALS['convoca_test_dns'] ?? null;
            $dominio = strtolower((string) $dominio);
            if (is_array($lista)) {
                return in_array($dominio, array_map('strtolower', $lista), true);
            }
            return '.test' !== substr($dominio, -5);
        }
    }

if (!function_exists('wp_count_posts')) {
    /**
     * Recuento por estado, como WordPress (`publish`, `draft`, ...).
     *
     * @param string $tipo Tipo de post.
     * @return object
     */
    function wp_count_posts($tipo = 'post') {
        $cuentas = (array) ($GLOBALS['convoca_test_count_posts'][ $tipo ] ?? array());
        return (object) $cuentas;
    }
}
if (!function_exists('is_admin')) {
    /** Modo administración, controlable por la prueba. */
    function is_admin() { return (bool) ($GLOBALS['convoca_test_is_admin'] ?? false); }
}

if (!function_exists('get_user_meta')) {
    /**
     * Meta de usuario, con el valor por defecto cuando no hay nada.
     *
     * @param int    $id      Usuario.
     * @param string $clave   Clave.
     * @param bool   $unico   ¿Un valor?
     * @return mixed
     */
    function get_user_meta($id, $clave = '', $unico = true) {
        if ('' === $clave) { return (array) ($GLOBALS['_wp_stores']['usermeta'][ $id ] ?? []); }
        $v = $GLOBALS['_wp_stores']['usermeta'][ $id ][ $clave ] ?? '';
        return $v;
    }
}

if (!function_exists('rest_url')) {
    /**
     * URL de la API.
     *
     * @param string $ruta Ruta.
     * @return string
     */
    function rest_url($ruta = '') { return 'http://sitio.example.test/wp-json/' . ltrim((string) $ruta, '/'); }
}

if (!function_exists('wp_delete_post')) {
    /**
     * Borrar un post: lo apunta para poder afirmar que el borrador huérfano se retiró.
     *
     * @param int  $id      Id.
     * @param bool $forzar  ¿Saltar papelera?
     * @return \WP_Post|null
     */
    function wp_delete_post($id, $forzar = false) {
        unset($forzar);
        $GLOBALS['_wp_posts_borrados'][] = (int) $id;
        unset($GLOBALS['_wp_stores']['posts'][ $id ], $GLOBALS['_wp_stores']['post_meta'][ $id ]);
        return null;
    }
}
}

namespace Convoca\Core {

    // El gestor de actualizaciones del núcleo: Enroll y Shifts heredan de él. El arnés no lo tenía, así que
	// los dos subclases no se podían ni construir y sus migraciones de datos quedaban sin medir. El doble
	// copia el contrato real (init/maybe_upgrade/force_version_check y las cuatro consultas abstractas).
    if ( ! class_exists( 'Convoca\Core\Upgrade_Manager' ) ) {

        /**
         * Gestor de actualizaciones (doble fiel del núcleo).
         */
        abstract class Upgrade_Manager {

            /**
             * Engancha la comprobación de versión.
             *
             * @return void
             */
            public function init(): void {
                add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
            }

            /**
             * Ejecuta las migraciones que falten según la versión guardada.
             *
             * @return void
             */
            public function maybe_upgrade(): void {
                $guardada = (string) get_option( $this->get_option_name(), '0.0.0' );
                $actual   = $this->get_db_version();

                if ( version_compare( $guardada, $actual, '>=' ) ) {
                    return;
                }

                foreach ( $this->get_upgrade_callbacks() as $version => $callback ) {
                    if ( version_compare( (string) $version, $guardada, '>' ) && version_compare( (string) $version, $actual, '<=' ) ) {
                        call_user_func( $callback );
                    }
                }

                update_option( $this->get_option_name(), $actual );
            }

            /**
             * Fuerza la comprobación (tras actualizar el plugin).
             *
             * @return void
             */
            public function force_version_check(): void {
                $this->maybe_upgrade();
            }

            /**
             * Versión de la base que pide el plugin.
             *
             * @return string
             */
            abstract protected function get_db_version(): string;

            /**
             * Clave de la opción donde vive la versión.
             *
             * @return string
             */
            abstract protected function get_option_name(): string;

            /**
             * Prefijo de los transients del gestor.
             *
             * @return string
             */
            abstract protected function get_transient_prefix(): string;

            /**
             * Migraciones por versión.
             *
             * @return array<string, callable>
             */
            abstract protected function get_upgrade_callbacks(): array;
        }
    }
}
