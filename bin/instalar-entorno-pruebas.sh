#!/usr/bin/env bash
#
# Monta el entorno de pruebas de integración de convoca-enroll (WordPress real + WP_UnitTestCase).
#
# Por qué existe: la suite de tests/Integration llevaba 39 pruebas y 0 aserciones — se saltaban
# todas porque faltaba el entorno, y el CI contaba «OK, but some tests were skipped» como éxito
# (issue #10). Esto es lo que hace falta, medido:
#
#   1. PHPUnit 9.6 (lo fija composer.json): wp-phpunit usa
#      PHPUnit\Util\Test::parseTestMethodAnnotations(), que PHPUnit 10 eliminó. Con PHPUnit 10
#      salen 33 errores que parecen del código y son del instrumento.
#   2. WordPress + la biblioteca de pruebas de la versión ETIQUETADA (no la del trunk, que no
#      empareja con wp-phpunit).
#   3. Convoca Core presente y con su vendor de PRODUCCIÓN (composer update --no-dev): con el
#      vendor de desarrollo, su PHPUnit se mezcla con el del plugin y el arranque revienta.
#   4. La activación del plugin tiene que ejecutarse: ahí se crean las tablas propias (cola de
#      correo, webhooks, códigos de reserva, medios). De eso se encarga tests/bootstrap.php.
#
# Uso:  bash bin/instalar-entorno-pruebas.sh [directorio-de-datos]
#   Variables: WP_VERSION (por defecto latest), DB_NAME/DB_USER/DB_PASSWORD/DB_HOST (por defecto
#   los del CI), CONVOCA_CORE_DIR (ruta del clon de convoca-core).
#   Al terminar imprime las dos variables que necesita la suite:
#     CONVOCA_WP_ROOT=/ruta/wp  WP_PHPUNIT__TESTS_CONFIG=/ruta/wp-tests-config.php WP_TESTS_DIR=/ruta/wptests
set -euo pipefail

DATOS="${1:-/tmp/convoca-entorno-pruebas}"
WP_VERSION="${WP_VERSION:-latest}"
DB_NAME="${DB_NAME:-wordpress_test}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-password}"
DB_HOST="${DB_HOST:-127.0.0.1:3306}"
CORE_DIR="${CONVOCA_CORE_DIR:-../convoca-core}"

mkdir -p "$DATOS"

echo "==> 1/4 WordPress ($WP_VERSION) y la biblioteca de pruebas en $DATOS"
if [ ! -f "$DATOS/wp/wp-load.php" ]; then
	url="https://wordpress.org/latest.tar.gz"
	[ "$WP_VERSION" != "latest" ] && url="https://wordpress.org/wordpress-$WP_VERSION.tar.gz"
	curl -sSL "$url" -o "$DATOS/wp.tgz"
	rm -rf "$DATOS/wp" && mkdir -p "$DATOS/wp"
	tar xzf "$DATOS/wp.tgz" -C "$DATOS/wp" --strip-components=1
fi
VERSION_REAL="$(grep -o "wp_version = '[^']*'" "$DATOS/wp/wp-includes/version.php" | head -1 | cut -d"'" -f2)"
echo "    WordPress $VERSION_REAL"

# La biblioteca de pruebas va EMPAREJADA con la versión de WordPress: la del trunk no vale.
if [ ! -f "$DATOS/wptests/includes/functions.php" ]; then
	rm -rf "$DATOS/wptests"
	svn export --quiet "https://develop.svn.wordpress.org/tags/$VERSION_REAL/tests/phpunit/" "$DATOS/wptests"
fi
echo "    biblioteca: $DATOS/wptests"

echo "==> 2/4 Convoca Core como en producción (sin dependencias de desarrollo)"
if [ -d "$CORE_DIR" ] && [ ! -d "$CORE_DIR/vendor/dompdf" ]; then
	( cd "$CORE_DIR" && composer update --no-dev --no-interaction --ignore-platform-req=ext-iconv >/dev/null )
fi

echo "==> 3/4 wp-tests-config.php"
cat > "$DATOS/wp-tests-config.php" <<PHP
<?php
define( 'ABSPATH', '$DATOS/wp/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Convoca pruebas' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_TESTS_DIR', '$DATOS/wptests' );
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASSWORD' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';

// El plugin exige Convoca Core activo, y sus pruebas necesitan el CPT y las tablas del núcleo.
\$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array( 'convoca-core/convoca-core.php', 'convoca-enroll/convoca-enroll.php' ),
);
PHP

echo "==> 4/4 el plugin a la vista de WordPress"
ln -sfn "$(cd "$(dirname "$0")/.." && pwd)" "$DATOS/wp/wp-content/plugins/convoca-enroll"
if [ -d "$CORE_DIR" ]; then ln -sfn "$(cd "$CORE_DIR" && pwd)" "$DATOS/wp/wp-content/plugins/convoca-core"; fi

cat <<FIN

Listo. Para ejecutar la suite:

  CONVOCA_WP_ROOT=$DATOS/wp \\
  WP_PHPUNIT__TESTS_CONFIG=$DATOS/wp-tests-config.php \\
  WP_TESTS_DIR=$DATOS/wptests \\
  vendor/bin/phpunit -c phpunit-integration.xml
FIN
