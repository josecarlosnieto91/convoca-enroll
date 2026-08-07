// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Convoca Social Publisher — Public Checks', () => {

  test('1. REST social accounts endpoint returns 401 without auth @api', async ({ page }) => {
    const resp = await page.request.get('/wp-json/convoca/v1/social/accounts');
    expect(resp.status()).toBe(401);
  });

  test('2. Front page loads and contains Convoca brand @smoke', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    const title = await page.title();
    expect(title.toLowerCase()).toContain('convoca');
  });
});
