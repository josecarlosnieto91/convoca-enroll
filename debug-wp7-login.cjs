const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Load storage state
  await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  
  await page.goto('https://getconvoca.app/wp-admin/');
  console.log('URL after navigation:', page.url());
  
  // Check if wpadminbar is visible or redirect
  const adminBar = await page.locator('#wpadminbar').isVisible().catch(() => false);
  console.log('Admin bar visible:', adminBar);
  
  if (!adminBar) {
    const title = await page.title();
    console.log('Page title:', title);
    // Check body for any useful text
    const bodyText = await page.locator('body').textContent().catch(() => '');
    console.log('Body preview:', bodyText?.substring(0, 200));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
