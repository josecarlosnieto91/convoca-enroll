// @ts-check
/**
 * Recorrido completo del módulo de medios, de punta a punta y sin navegador.
 *
 * Antes este spec entraba en `page=convoca-media`, una pantalla que no existe, y creaba
 * actividades «E2E Test …» para nada (de ahí los autodrafts que aparecían). El recorrido real
 * de hoy es: la API está cerrada a quien no se identifica, y quien se identifica puede ver
 * las plantillas, generar el cartel y servirlo.
 */
const { test, expect } = require('@playwright/test');
const { fixture, auth } = require('./helpers');

const fx = fixture();

test.describe('Recorrido del módulo de medios @media', () => {
  // Sin cookie de sesión: la API se autentica con la contraseña de aplicación (cookie sin
  // nonce = 401).
  test.use({ storageState: { cookies: [], origins: [] } });

  test('sin identificarse no se ve nada', async ({ request }) => {
    for (const ruta of [
      '/wp-json/convoca/v1/media/templates',
      '/wp-json/convoca/v1/media/templates/1',
      '/wp-json/convoca/v1/social/accounts',
    ]) {
      expect((await request.get(ruta)).status(), ruta).toBe(401);
    }

    const render = await request.post('/wp-json/convoca/v1/media/poster/render', {
      data: { actividad_id: fx.actividadId, template: fx.templates[0] },
    });
    expect(render.status()).toBe(401);
  });

  test('identificado, de la plantilla al cartel servido', async ({ request }) => {
    // 1. Qué plantillas hay.
    const plantillas = await (
      await request.get('/wp-json/convoca/v1/media/templates', { headers: auth() })
    ).json();
    expect(plantillas.length).toBeGreaterThan(0);

    // 2. Generar el cartel de la primera.
    const render = await request.post('/wp-json/convoca/v1/media/poster/render', {
      headers: auth(),
      data: { actividad_id: fx.actividadId, template: plantillas[0].slug, format: 'square' },
    });
    expect(render.status()).toBe(200);
    const { url } = await render.json();

    // 3. Y que el cartel se pueda ver: está servido y es un PNG con contenido.
    const imagen = await request.get(url);
    expect(imagen.status()).toBe(200);
    expect(imagen.headers()['content-type']).toContain('image/png');
    expect((await imagen.body()).length).toBeGreaterThan(1000);
  });

  test('una plantilla que no existe no rompe la API', async ({ request }) => {
    const r = await request.post('/wp-json/convoca/v1/media/poster/render', {
      headers: auth(),
      data: { actividad_id: fx.actividadId, template: 'plantilla-que-no-existe', format: 'square' },
    });

    expect(r.status()).toBeLessThan(500);
  });
});
