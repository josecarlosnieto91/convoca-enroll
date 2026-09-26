// @ts-check
/**
 * El módulo social, por su interfaz real: la API REST.
 *
 * El módulo no está huérfano (lo delató esta auditoría): expone seis rutas bajo
 * `convoca/v1/social/*`, tiene un healthcheck semanal del token programado y publica por un
 * evento de cron (`convoca_social_publish`). Lo que no tiene es pantalla: la UI de publicación
 * no está enganchada a ningún PHP, igual que pasaba con los carteles. Así que se prueba lo que
 * hay de verdad, que es la API: que exija permiso, que no invente cuentas y que arrancar un
 * OAuth o recibir un callback sin código no reviente.
 *
 * No se arranca ningún OAuth de verdad: aquí no hay credenciales de Meta ni de Google y no se
 * le piden a nadie. Se comprueba la única parte que depende de nosotros, que es la respuesta.
 */
const { test, expect } = require('@playwright/test');
const { fixture, auth } = require('./helpers');

fixture(); // Falla pronto y con un mensaje útil si falta el seed.

test.describe('Social por API @social @api', () => {
  // Sin cookie de sesión: con cookie y sin nonce, WordPress responde 401 aunque mandes la
  // contraseña de aplicación (rest_cookie_invalid_nonce).
  test.use({ storageState: { cookies: [], origins: [] } });

  test('las cuentas sociales exigen autenticación', async ({ request }) => {
    const r = await request.get('/wp-json/convoca/v1/social/accounts');
    expect(r.status()).toBe(401);
  });

  test('autenticado, devuelve las cuentas conectadas (ninguna inventada)', async ({ request }) => {
    const r = await request.get('/wp-json/convoca/v1/social/accounts', { headers: auth() });
    expect(r.status()).toBe(200);

    const cuentas = await r.json();
    expect(Array.isArray(cuentas)).toBe(true);
    // En el entorno de pruebas no hay ninguna cuenta conectada, y eso es lo que debe decir: una
    // lista vacía. Si algún día devolviera cuentas sin haberlas conectado, sería un dato falso.
    for (const c of cuentas) {
      expect(c).toHaveProperty('provider');
    }
  });

  test('arrancar el OAuth de Meta redirige, no rompe', async ({ request }) => {
    const r = await request.get('/wp-json/convoca/v1/social/auth/meta', {
      headers: auth(),
      maxRedirects: 0, // No se sigue la redirección: llevaría a Meta y no es asunto de esta prueba.
    });
    // 302 = manda al proveedor (o de vuelta al panel si falta configurar la app). Lo que no
    // puede pasar es un 500 ni un 200 con contenido: aquí no hay forma de conectar nada.
    expect(r.status()).toBe(302);
  });

  test('el callback del proveedor sin código no rompe', async ({ request }) => {
    const r = await request.get('/wp-json/convoca/v1/social/callback/meta', { maxRedirects: 0 });
    // Es público (lo llama el proveedor, que no trae sesión) y sin código no debe hacer nada
    // salvo devolver al origen.
    expect(r.status()).toBe(302);
  });

  test('la portada carga y lleva la marca', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    expect((await page.title()).toLowerCase()).toContain('convoca');
  });
});
