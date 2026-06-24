<?php
// Define constants before anything
define("WP_TESTS_DOMAIN", "example.org");
define("WP_TESTS_EMAIL", "admin@example.org");
define("WP_TESTS_TITLE", "Test Blog");
define("WP_PHP_BINARY", "php");
define("WP_TESTS_DB_NAME", "wordpress_test");
define("WP_TESTS_DB_USER", "convoca_dev");
define("WP_TESTS_DB_PASS", "convoca_dev_pass");
define("WP_TESTS_DB_HOST", "db");
define("WP_TESTS_TABLE_PREFIX", "wptests_");

// These MUST be defined before wp-phpunit loads
$_tests_dir = "/var/www/html/wp-content/plugins/convoca-core/vendor/wp-phpunit/wp-phpunit";

// Load WordPress
require_once "/var/www/html/wp-load.php";

// Load wp-phpunit test framework
require_once $_tests_dir . "/includes/functions.php";

// Install test DB
require_once $_tests_dir . "/includes/bootstrap.php";
