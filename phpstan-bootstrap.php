<?php
/**
 * PHPStan bootstrap: constantes WP de runtime (las define wp-load en producción).
 */
define( 'ABSPATH', '/tmp/wp/' );
define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS );
define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'OBJECT_K', 'OBJECT_K' );
define( 'WP_DEBUG', true );

/**
 * Constantes del plugin convoca-enroll (convoca-enroll.php).
 * Los valores son los que fijaría plugin_dir_path()/plugin_dir_url() en producción.
 */
define( 'CONVOCA_ENROLL_DIR', __DIR__ . '/' );
define( 'CONVOCA_ENROLL_URL', 'https://example.org/wp-content/plugins/convoca-enroll/' );

/**
 * Constantes de convoca-core (convoca-core.php), espejo de su phpstan-bootstrap.php.
 */
define( 'CONVOCA_COMMON_VERSION', '2.1.4' );
define( 'CONVOCA_COMMON_URL', 'https://example.org/wp-content/plugins/convoca-core/' );
define( 'CONVOCA_IMAGES_URL', CONVOCA_COMMON_URL . 'assets/images/' );

/**
 * Constante opcional definida en wp-config.php (API key de Google Sheets).
 */
define( 'CONV_ENROLL_GOOGLE_SHEETS_API_KEY', 'example-sheets-api-key' );
