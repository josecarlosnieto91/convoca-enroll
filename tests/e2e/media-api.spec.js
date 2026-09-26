// @ts-check
/**
 * El módulo de medios, por su interfaz real: la API REST.
 *
 * Sustituye a la prueba del metabox, que buscaba `.convoca-media-metabox` en una pantalla
 * que ningún PHP registra. Aquí sí se ejerce lo que hay: listar plantillas reales y generar
 * carteles de verdad, comprobando que la imagen se sirve.
 */
const { test, expect } = require('@playwright/test');
const { fixture, auth } = require('./helpers');

const fx = fixture();
const PNG = Buffer.from([0x89, 0x50, 0x4e, 0x47]); // firma de un PNG de verdad

test.describe('Carteles por API @media @api', () => {
  // Sin la cookie de sesión: una petición con cookie y sin nonce la rechaza WordPress con un
  // 401 (rest_cookie_invalid_nonce) aunque mandes la contraseña de aplicación. La API se
  // autentica con la contraseña, no con la sesión del navegador.
  test.use({ storageState: { cookies: [], origins: [] } });

  test('la API exige autenticación', async ({ request }) => {
    const r = await request.get('/wp-json/convoca/v1/media/templates');
    expect(r.status()).toBe(401);
  });

  test('ofrece las plantillas que existen, no nombres inventados', async ({ request }) => {
    const r = await request.get('/wp-json/convoca/v1/media/templates', { headers: auth() });
    expect(r.status()).toBe(200);

    const plantillas = await r.json();
    expect(Array.isArray(plantillas)).toBe(true);
    expect(plantillas.length).toBeGreaterThan(0);

    const slugs = plantillas.map((t) => t.slug);
    expect(slugs).toContain(fx.templates[0]);
    // Cada plantilla trae lo que hace falta para pintarla.
    for (const t of plantillas) {
      expect(t).toHaveProperty('slug');
      expect(t).toHaveProperty('name');
    }
  });

  test('genera un cartel y lo sirve como imagen', async ({ request }) => {
    const r = await request.post('/wp-json/convoca/v1/media/poster/render', {
      headers: auth(),
      data: { actividad_id: fx.actividadId, template: fx.templates[0], format: 'square' },
    });
    expect(r.status()).toBe(200);

    const cuerpo = await r.json();
    expect(cuerpo.url).toContain('/convoca-posters/');
    expect(typeof cuerpo.cached).toBe('boolean');

    // Y la imagen está de verdad ahí, no es una ruta que no lleva a nada.
    const imagen = await request.get(cuerpo.url);
    expect(imagen.status()).toBe(200);
    expect(imagen.headers()['content-type']).toContain('image/png');
    expect((await imagen.body()).subarray(0, 4)).toEqual(PNG);
  });

  test('reutiliza el cartel ya generado y no lo repinta', async ({ request }) => {
    const datos = { actividad_id: fx.actividadId, template: fx.templates[0], format: 'square' };

    await request.post('/wp-json/convoca/v1/media/poster/render', { headers: auth(), data: datos });
    const otra = await request.post('/wp-json/convoca/v1/media/poster/render', { headers: auth(), data: datos });

    expect(otra.status()).toBe(200);
    expect((await otra.json()).cached).toBe(true);
  });

  test('regenerar fuerza el repintado', async ({ request }) => {
    const r = await request.post('/wp-json/convoca/v1/media/poster/regenerate', {
      headers: auth(),
      data: { actividad_id: fx.actividadId, template: fx.templates[0] },
    });

    expect(r.status()).toBe(200);
    expect((await r.json()).cached).toBe(false);
  });

  test('un error de generación se explica, no se queda en un 500 pelado', async ({ request }) => {
    const r = await request.post('/wp-json/convoca/v1/media/poster/render', {
      headers: auth(),
      data: { actividad_id: 999999, template: 'no-existe', format: 'square' },
    });

    expect([400, 404]).toContain(r.status());
    expect(JSON.stringify(await r.json())).toMatch(/error/i);
  });
});
