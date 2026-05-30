const { chromium } = require('playwright');
const { writeFileSync } = require('fs');

const cookie_value = process.argv[2];
if (!cookie_value) { console.error('No cookie value'); process.exit(1); }

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  
  // First navigate to site to establish context
  const p = await context.newPage();
  await p.goto('https://getconvoca.app/');
  await p.close();
  
  // Add cookie
  await context.addCookies([{
    name: 'wordpress_logged_in_12ce7f849505e073301a882aa8b39e41',
    value: cookie_value,
    domain: 'getconvoca.app',
    path: '/',
    httpOnly: true,
    secure: true,
    sameSite: 'Lax'
  }]);
  
  // Try navigating to wp-admin
  const page = await context.newPage();
  await page.goto('https://getconvoca.app/wp-admin/');
  
  if (!page.url().includes('wp-login')) {
    console.log('AUTH SUCCESS');
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
    console.log('auth.json saved');
  } else {
    console.log('AUTH FAILED, URL:', page.url());
  }
  
  await browser.close();
}

main().catch(e => console.error(e.message));
