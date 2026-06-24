<?php
// Required constants for wp-phpunit
define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Blog');
define('WP_PHP_BINARY', 'php');
define('WP_TESTS_TABLE_PREFIX', 'wptests_');

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Load wp-phpunit  
require_once '/var/www/html/wp-content/plugins/convoca-core/vendor/wp-phpunit/wp-phpunit/includes/functions.php';
require_once '/var/www/html/wp-content/plugins/convoca-core/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';

require_once dirname(__DIR__) . '/convoca-enroll.php';
