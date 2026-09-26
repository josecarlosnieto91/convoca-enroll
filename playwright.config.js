// @ts-check
const fs = require('fs');
const { defineConfig } = require('@playwright/test');

/**
 * Destino del E2E.
 *
 * El módulo de medios se prueba por su API REST, que es su interfaz real: la UI del metabox
 * (`assets/js/media-admin.js`, `convoca-editor.js`) no está enganchada a ningún PHP —nadie
 * la encola y su acción `convoca_render_poster` no tiene handler—, así que no hay pantalla
 * que pulsar. La suite ESCRIBE (genera carteles y toca una actividad de fixture), por eso el
 * destino por defecto es el contenedor de desarrollo y nunca un sitio en vivo: apuntarla a
 * un dominio real se hace a propósito, con E2E_BASE_URL.
 */
const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8080';

/**
 * Sesión de navegador, si hay. `auth.json` (ignorado por git) se mintea sin contraseñas
 * dentro del contenedor. La API se autentica con la contraseña de aplicación del fixture.
 */
const authFile = process.env.E2E_AUTH || 'auth.json';
const storageState = fs.existsSync(authFile) ? authFile : undefined;

console.log(`[e2e] destino: ${baseURL}${storageState ? ` · sesión: ${authFile}` : ' · sin sesión'}`);

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  expect: { timeout: 10000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  // El informe va FUERA de la carpeta de resultados: dentro, Playwright avisa de que las
  // dos salidas chocan y el informe HTML no llega a generarse.
  reporter: [
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ['list'],
  ],
  use: {
    baseURL: baseURL,
    storageState: storageState,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 15000,
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
  snapshotDir: './tests/snapshots',
  outputDir: 'tests/results',
});
