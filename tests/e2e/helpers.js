// @ts-check

/**
 * Lo que comparten los tests E2E: el fixture y las llamadas a la API.
 *
 * El módulo de medios se prueba por su interfaz real, que es la API REST: la UI del
 * metabox (assets/js/media-admin.js) no está enganchada a ningún PHP, así que no hay
 * pantalla que pulsar.
 */

const fs = require('fs');
const path = require('path');

const FIXTURE = path.join(__dirname, '.fixture.json');

/**
 * Lee el fixture que prepara `npm run test:seed`. Falla con un mensaje útil si no está.
 */
function fixture() {
  if (!fs.existsSync(FIXTURE)) {
    throw new Error(
      `Falta ${path.basename(FIXTURE)}. Prepara el entorno con: npm run test:seed`
    );
  }
  return JSON.parse(fs.readFileSync(FIXTURE, 'utf8'));
}

/**
 * Cabecera de autenticación con la contraseña de aplicación (no necesita nonce).
 */
function auth() {
  const { appUser, appPass } = fixture();
  return { Authorization: 'Basic ' + Buffer.from(`${appUser}:${appPass}`).toString('base64') };
}

module.exports = { fixture, auth, FIXTURE };
