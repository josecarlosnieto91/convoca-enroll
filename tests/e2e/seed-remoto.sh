#!/usr/bin/env bash
#
# Prepara el fixture de los tests E2E en un WordPress VIVO (enroll#24).
#
# Los tests apuntaban a un contenedor de desarrollo (`convoca-dev-wp-1`, retirado en julio de 2026) y su
# `pretest` moría antes de empezar: no se podían ejecutar en ninguna parte. Ahora el sembrador se lanza
# donde de verdad hay un sitio, y el fixture vuelve a `tests/e2e/.fixture.json`.
#
# Por defecto apunta a la DEMO. Para otro sitio, por variables de entorno:
#
#   E2E_SSH=andromeda E2E_WP_PATH=/var/www/demo.getconvoca.app/public E2E_WP_USER=http \
#   E2E_BASE_URL=https://demo.getconvoca.app npm run test:seed
#
# NO se apunta a producción: la suite ESCRIBE (crea carteles y toca su actividad de fixture).
set -euo pipefail

SITIO="${E2E_SSH:-andromeda}"
RUTA="${E2E_WP_PATH:-/var/www/demo.getconvoca.app/public}"
USUARIO_WP="${E2E_WP_USER:-http}"
BASE_URL="${E2E_BASE_URL:-https://demo.getconvoca.app}"
FIXTURE_LOCAL="${E2E_FIXTURE:-tests/e2e/.fixture.json}"
REMOTO_SEED="/tmp/convoca-e2e-seed.php"
REMOTO_FIXTURE="/tmp/convoca-e2e-fixture.json"

echo "[e2e] sembrando en ${SITIO}:${RUTA} (${BASE_URL})"

scp -q tests/e2e/seed.php "${SITIO}:${REMOTO_SEED}"
ssh "${SITIO}" "chmod 644 ${REMOTO_SEED} && sudo -u ${USUARIO_WP} env E2E_BASE_URL='${BASE_URL}' E2E_FIXTURE='${REMOTO_FIXTURE}' wp --path=${RUTA} eval-file ${REMOTO_SEED}"

scp -q "${SITIO}:${REMOTO_FIXTURE}" "${FIXTURE_LOCAL}"
# El fichero lo escribió el usuario de WordPress, así que se retira con él (no con el mío).
ssh "${SITIO}" "sudo rm -f ${REMOTO_SEED} ${REMOTO_FIXTURE}"

echo "[e2e] fixture en ${FIXTURE_LOCAL}"
