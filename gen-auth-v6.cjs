const { chromium } = require('playwright');
const { writeFileSync } = require('fs');

const cookie_value = 'admin|1781370602|p9ZsFy6WPzJ3P3w6HEqfx0kTxF8wEqOKNPXboy5xCQ3|aa0dcfc20ac697009f516fbdfbbf545d3d7800eed9de9c52858b715d6bc9a6e0';

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  
  // Add the properly signed auth cookie
  await context.addCookies([
    {
      name: 'wordpress_logged_in_12ce7f849505e073301a882aa8b39e41',
      value: cookie_value,
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
  
  if (!page.url().includes('wp-login')) {
    console.log('ACCESS GRANTED!');
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
    const c = await context.cookies();
    console.log('Cookies saved:', c.length);
  } else {
    console.log('Still on login page');
    const msg = await page.locator('#login_error').textContent().catch(() => 'none');
    console.log('Error:', msg?.toString().substring(0, 300));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
