const { chromium } = require('playwright');
const { writeFileSync } = require('fs');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  
  // Add the auth cookie that was just set by wp_set_auth_cookie
  await context.addCookies([
    {
      name: 'wordpress_logged_in_12ce7f849505e073301a882aa8b39e41',
      value: 'admin|' + Math.floor(Date.now()/1000 + 86400*14) + '|' + 'somehash',
      domain: 'getconvoca.app',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax'
    }
  ]);
  
  const page = await context.newPage();
  await page.goto('https://getconvoca.app/wp-admin/');
  console.log('URL:', page.url());
  
  // Check if we got through
  if (!page.url().includes('wp-login')) {
    console.log('ACCESS GRANTED');
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  } else {
    console.log('Still on login page');
    const error = await page.locator('#login_error').textContent().catch(() => 'none');
    console.log('Error:', error?.toString().substring(0, 300));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
