const { test, expect } = require('@playwright/test');

test.describe('Convoca Media Suite — Public Pages', () => {

  test('1. Front page loads @smoke', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    const title = await page.title();
    console.log('Page title:', title);
  });

  test('2. REST API templates endpoint @api', async ({ page }) => {
    const resp = await page.request.get('/wp-json/convoca/v1/media/templates');
    expect(resp.status()).toBe(401);
    console.log('Templates endpoint: 401 (expected - no auth)');
  });

  test('3. REST API social endpoint @api', async ({ page }) => {
    const resp = await page.request.get('/wp-json/convoca/v1/social/accounts');
    expect(resp.status()).toBe(401);
    console.log('Social endpoint: 401 (expected - no auth)');
  });
});
