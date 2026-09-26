// @ts-check
const fs = require('fs');
const { defineConfig } = require('@playwright/test');

/**
 * Destino del E2E.
 *
 * OJO: esta suite ESCRIBE contenido (crea carteles, entradas de blog y actividades
 * «E2E Test …»). Por eso el destino por defecto es el contenedor de desarrollo, NUNCA
 * un sitio en vivo: apuntarla a un dominio real se hace a propósito, con E2E_BASE_URL.
 * Antes apuntaba sin más a https://getconvoca.app, así que un `npm test` despistado
 * creaba contenido en la web de ventas.
 */
const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8080';

/**
 * Sesión de administrador para las páginas de wp-admin.
 *
 * `auth.json` (ignorado por git) se mintea sin contraseñas dentro del contenedor, con
 * cookies de sesión de WordPress. Sin él, los specs que entran en wp-admin no pasan.
 */
const authFile = process.env.E2E_AUTH || 'auth.json';
const storageState = fs.existsSync(authFile) ? authFile : undefined;

console.log(`[e2e] destino: ${baseURL}${storageState ? ` · sesión: ${authFile}` : ' · SIN sesión (auth.json no existe)'}`);

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  expect: {
    timeout: 10000,
    toHaveScreenshot: { maxDiffPixels: 100 },
  },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['html', { outputFolder: 'tests/results/html' }],
    ['list'],
  ],
  use: {
    baseURL: baseURL,
    storageState: storageState,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 15000,
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
  snapshotDir: './tests/snapshots',
  outputDir: 'tests/results',
});
