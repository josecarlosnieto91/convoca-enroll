const { chromium } = require('playwright');

const cookie_value = 'admin|1781371601|auth_65Et7Cb5kaOu6FLMwt8qEdOU4j6KcIbQrVXYgbL|e1236ef188c74fe6343cc3989b197b4e0731a0951914b69334d24c7b92d1a442';

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  
  // First navigate to establish context
  const setupPage = await context.newPage();
  await setupPage.goto('https://getconvoca.app/');
  await setupPage.close();
  
  // Add cookie WITHOUT secure flag (since we're using HTTPS, it still works)
  await context.addCookies([{
    name: 'wordpress_logged_in_12ce7f849505e073301a882aa8b39e41',
    value: cookie_value,
    domain: 'getconvoca.app',
    path: '/',
    httpOnly: true,
    secure: false,  // Try without secure
    sameSite: 'None', // Or 'Lax'
  }]);
  
  const page = await context.newPage();
  page.on('request', req => {
    if (req.url() === 'https://getconvoca.app/wp-admin/') {
      const c = req.headers()['cookie'] || '';
      console.log(`wp-admin/ request - cookie length: ${c.length}, has auth: ${c.includes('logged_in')}`);
    }
  });
  
  await page.goto('https://getconvoca.app/wp-admin/');
  console.log('Final URL:', page.url());
  
  if (!page.url().includes('wp-login')) {
    console.log('SUCCESS!');
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
