// @ts-check
/**
 * Regresión visual de los carteles.
 *
 * La prueba anterior abría una pantalla de previsualización (`page=convoca-media-preview`)
 * que ningún PHP registra, así que nunca podía pasar. Un cartel es una imagen generada: lo
 * que importa es que **el resultado no cambie** sin querer, y eso se comprueba sobre los
 * píxeles que se sirven, no sobre una pantalla intermedia.
 *
 * La línea base vive en `tests/snapshots/posters.json` (se commitea). Para rehacerla tras un
 * cambio deliberado de diseño: `npm run snapshots:update`.
 */
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { fixture, auth } = require('./helpers');

const fx = fixture();
const BASELINE = path.join(__dirname, '..', 'snapshots', 'posters.json');

/** Genera el cartel de una plantilla y devuelve su URL. */
async function cartel(request, template) {
  const r = await request.post('/wp-json/convoca/v1/media/poster/render', {
    headers: auth(),
    data: { actividad_id: fx.actividadId, template, format: 'square' },
  });
  expect(r.status(), `render de ${template}`).toBe(200);

  return (await r.json()).url;
}

/** Huella de los píxeles que se sirven. */
async function huella(request, url) {
  const imagen = await request.get(url);
  expect(imagen.status()).toBe(200);

  return crypto.createHash('sha256').update(await imagen.body()).digest('hex');
}

/** Línea base guardada (vacía la primera vez). */
function leerBaseline() {
  return fs.existsSync(BASELINE) ? JSON.parse(fs.readFileSync(BASELINE, 'utf8')) : {};
}

test.describe('Carteles: regresión visual @visual @media', () => {
  // La API se autentica con la contraseña de aplicación: con la cookie de sesión y sin nonce,
  // WordPress responde 401 y no serviría de nada.
  test.use({ storageState: { cookies: [], origins: [] } });

  for (const template of fx.templates.slice(0, 2)) {
    test(`${template} no cambia de aspecto`, async ({ request }) => {
      const hash = await huella(request, await cartel(request, template));
      const baseline = leerBaseline();
      const clave = `${template}|square`;

      if (process.env.UPDATE_SNAPSHOTS || !baseline[clave]) {
        baseline[clave] = hash;
        fs.mkdirSync(path.dirname(BASELINE), { recursive: true });
        fs.writeFileSync(BASELINE, JSON.stringify(baseline, null, 2) + '\n');
        test.skip(true, `línea base registrada para ${clave}: ${hash.slice(0, 12)}…`);
        return;
      }

      // Si cambia, el mensaje dice cuál y con qué huella, sin tener que abrir nada.
      expect(hash, `el cartel de ${template} cambió de aspecto`).toBe(baseline[clave]);
    });
  }

  test('el mismo cartel dos veces da los mismos píxeles', async ({ request }) => {
    const url = await cartel(request, fx.templates[0]);

    expect(await huella(request, url)).toBe(await huella(request, url));
  });
});
