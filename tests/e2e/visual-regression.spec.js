// @ts-check
const { test, expect } = require('@playwright/test');

const TEMPLATES = ['nature-classic', 'nature-minimal', 'urban-workshop', 'family-day', 'digital-event', 'corporate-talk', 'sport-challenge', 'wellness-retreat'];
const FORMATS = ['square', 'story', 'facebook'];
const MAIN_FORMATS = ['square'];

test.describe('Poster Visual Regression @visual', () => {

  test.describe('Main templates × formats', () => {
    for (const template of TEMPLATES.slice(0, 3)) {
      for (const format of FORMATS) {
        test(`${template} - ${format}`, async ({ page }) => {
          await page.goto(`/wp-admin/admin.php?page=convoca-media-preview&template=${template}&format=${format}&post_id=100`);
          await page.waitForSelector('#poster-preview img, .convoca-poster-canvas', { timeout: 20000 });
          
          // Wait for image to load
          const img = page.locator('#poster-preview img');
          await expect(img).toBeVisible({ timeout: 15000 });
          
          // Visual comparison
          const screenshotTarget = (await img.count()) > 0 ? img : page.locator('.convoca-poster-canvas');
          await expect(screenshotTarget).toHaveScreenshot(`${template}-${format}.png`);
        });
      }
    }
  });

  test.describe('All templates — render smoke test', () => {
    for (const template of TEMPLATES) {
      test(`${template} renders without error @media`, async ({ page }) => {
        await page.goto(`/wp-admin/admin.php?page=convoca-media-preview&template=${template}&format=square&post_id=100`);
        await page.waitForSelector('#poster-preview img, .convoca-poster-canvas', { timeout: 20000 });

        // No error notice visible
        const errorNotice = page.locator('.notice-error, .convoca-error');
        await expect(errorNotice).toHaveCount(0);

        // Preview container exists
        await expect(page.locator('#poster-preview, .convoca-poster-preview')).toBeVisible();
      });
    }
  });
});