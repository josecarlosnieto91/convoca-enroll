const { chromium } = require('playwright');

const cookie_value = 'admin|1781371601|auth_65Et7Cb5kaOu6FLMwt8qEdOU4j6KcIbQrVXYgbL|e1236ef188c74fe6343cc3989b197b4e0731a0951914b69334d24c7b92d1a442';

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  
  await context.addCookies([{
    name: 'wordpress_logged_in_12ce7f849505e073301a882aa8b39e41',
    value: cookie_value,
    domain: 'getconvoca.app',
    path: '/',
    httpOnly: true,
    secure: true,
    sameSite: 'Lax'
  }]);
  
  const page = await context.newPage();
  await page.goto('https://getconvoca.app/wp-admin/');
  console.log('URL:', page.url());
  
  const hasBar = await page.locator('#wpadminbar').isVisible().catch(() => false);
  console.log('Admin bar:', hasBar);
  
  if (hasBar) {
    console.log('AUTH SUCCESS!');
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  } else {
    console.log('AUTH FAILED');
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
