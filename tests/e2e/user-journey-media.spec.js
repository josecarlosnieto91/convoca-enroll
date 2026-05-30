// @ts-check
const { test, expect } = require('@playwright/test');

const WP_USER = process.env.WP_USER || 'admin';
const WP_PASS = process.env.WP_PASS || '';

test.describe('Convoca Media Suite — Admin Journey', () => {

  test('1. Front page loads @smoke', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible({ timeout: 15000 });
    console.log('Title:', await page.title());
  });

  test('2. REST API endpoints respond @api', async ({ page }) => {
    const r1 = await page.request.get('/wp-json/convoca/v1/media/templates');
    expect(r1.status()).toBe(401);
    const r2 = await page.request.get('/wp-json/convoca/v1/social/accounts');
    expect(r2.status()).toBe(401);
    console.log('REST endpoints: 401 OK (no auth)');
  });

  test('3. Login and access admin @auth', async ({ page }) => {
    test.skip(!WP_PASS, 'WP_PASS env var required');
    
    await page.goto('/wp-login.php');
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 15000 });
    
    // Check admin bar visible
    await expect(page.locator('#wpadminbar')).toBeVisible();
    console.log('Logged in as:', WP_USER);
  });

  test('4. Media dashboard loads @auth', async ({ page }) => {
    test.skip(!WP_PASS, 'WP_PASS env var required');
    
    // Login first
    await page.goto('/wp-login.php');
    await page.fill('#user_login', WP_USER);
    await page.fill('#user_pass', WP_PASS);
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 15000 });
    
    // Go to media dashboard
    await page.goto('/wp-admin/admin.php?page=convoca-media');
    await page.waitForSelector('.convoca-media-dashboard, .wrap', { timeout: 10000 });
    console.log('Dashboard loaded, URL:', page.url());
  });
});
