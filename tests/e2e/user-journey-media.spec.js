// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Convoca Media Suite — Full User Journey', () => {

  test('1. Front page loads @smoke', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
  });

  test('2. REST API endpoints @api', async ({ page }) => {
    expect((await page.request.get('/wp-json/convoca/v1/media/templates')).status()).toBe(401);
    expect((await page.request.get('/wp-json/convoca/v1/social/accounts')).status()).toBe(401);
  });

  test('3. Login to admin @auth', async ({ page }) => {
    await page.goto('/wp-admin/');
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 15000 });
    console.log('Logged in');
  });

  test('4. Media dashboard loads @auth', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=convoca-media');
    await page.waitForSelector('.convoca-media-dashboard, .wrap', { timeout: 10000 });
    const items = await page.locator('.convoca-media-dashboard table tbody tr').count();
    console.log('Dashboard items:', items);
  });

  test('5. Poster metabox on new actividad @auth', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php?post_type=actividad');
    await page.waitForSelector('#title', { timeout: 10000 });
    await page.fill('#title', 'E2E Test ' + Date.now());
    // Check poster metabox
    await expect(page.locator('#convoca-media-poster')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('#convoca-template-select')).toBeVisible();
    await expect(page.locator('.convoca-generate-poster')).toBeVisible();
    console.log('Metabox: template select + generate button OK');
  });
});
