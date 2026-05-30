const { chromium } = require('playwright');
const { writeFileSync, readFileSync } = require('fs');

// Cookie values from the PHP script
const cookie_value = 'admin|1781371149|v3pdsarHoUlwW5RyuEM4ODa4Tp6wJLX4liXxDaWbaXB|5e3bb3f45da5b5af3e044cb75069096a4ed52c94e1c3df1cd9a893b65d932e55';

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
  
  if (!page.url().includes('wp-login')) {
    console.log('ACCESS GRANTED!');
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
    console.log('auth.json saved');
  } else {
    console.log('Still on login page');
    const msg = await page.locator('#login_error').textContent().catch(() => 'none');
    console.log('Error:', msg?.substring(0, 200));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
