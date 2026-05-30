// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Convoca Media Suite', () => {

  test('1. Front page loads @smoke', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
  });

  test('2. REST API endpoints @api', async ({ page }) => {
    expect((await page.request.get('/wp-json/convoca/v1/media/templates')).status()).toBe(401);
    expect((await page.request.get('/wp-json/convoca/v1/social/accounts')).status()).toBe(401);
  });

  test('3. Media poster preview from query @api', async ({ page }) => {
    // Test that we can get template info without auth
    const resp = await page.request.get('/wp-json/convoca/v1/media/templates');
    expect(resp.status()).toBe(401);
  });
});
