// @ts-check

/**
 * Lo que comparten los tests E2E: el fixture y las llamadas a la API.
 *
 * El módulo de medios se prueba por su interfaz real, que es la API REST. Aquella UI de
 * metabox (assets/js/media-admin.js) nunca llegó a tener PHP detrás —nadie la encolaba y la
 * acción convoca_render_poster no tenía handler— y se retiró como código muerto en la 2.7.19,
 * así que no hay pantalla que pulsar.
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
