// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Poster Generation Flow', () => {

  test('metabox is visible on actividad edit screen @poster @media', async ({ page }) => {
    await page.goto('/wp-admin/post.php?post=100&action=edit');
    await page.waitForSelector('.convoca-media-metabox', { timeout: 10000 });
    await expect(page.locator('.convoca-media-metabox')).toBeVisible();
    await expect(page.locator('.convoca-media-metabox h2')).toContainText('Cartel');
  });

  test('generate poster via metabox button @poster @media', async ({ page }) => {
    await page.goto('/wp-admin/post.php?post=100&action=edit');
    await page.waitForSelector('.convoca-generate-poster', { timeout: 10000 });

    // Select template
    await page.selectOption('#convoca-template-select', 'nature-classic');
    // Click generate
    await page.click('.convoca-generate-poster');
    
    // Wait for success message
    await page.waitForSelector('.convoca-media-message.success', { timeout: 15000 });
    const msg = await page.textContent('.convoca-media-message');
    expect(msg).toContain('Cartel generado');
  });

  test('social checkboxes present in metabox @media', async ({ page }) => {
    await page.goto('/wp-admin/post.php?post=100&action=edit');
    await page.waitForSelector('.convoca-social-checkboxes', { timeout: 10000 });
    
    const checkboxes = page.locator('.convoca-social-checkboxes input[type="checkbox"]');
    const count = await checkboxes.count();
    expect(count).toBeGreaterThanOrEqual(1);
    
    // Should have Facebook and/or Google Business Profile options
    const labels = await page.locator('.convoca-social-checkboxes label').allTextContents();
    const joined = labels.join(' ');
    expect(joined).toMatch(/facebook|meta|google|gbp/i);
  });

  test('create blog post from metabox @media', async ({ page }) => {
    await page.goto('/wp-admin/post.php?post=100&action=edit');
    await page.waitForSelector('.convoca-create-blog-post', { timeout: 10000 });
    
    await page.click('.convoca-create-blog-post');
    await page.waitForSelector('.convoca-media-message.success', { timeout: 15000 });
    const msg = await page.textContent('.convoca-media-message');
    expect(msg).toContain('Entrada creada');
  });

  test('media dashboard shows activities @media', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=convoca-media');
    await page.waitForSelector('.convoca-media-dashboard', { timeout: 10000 });
    
    // Should have a table or grid of activities
    const rows = page.locator('.convoca-media-dashboard table tbody tr, .convoca-media-grid .card');
    const count = await rows.count();
    expect(count).toBeGreaterThanOrEqual(1);
    
    // Column/header for poster status
    await expect(page.locator('.convoca-media-dashboard')).toContainText(/cartel|poster|imagen/i);
  });
});