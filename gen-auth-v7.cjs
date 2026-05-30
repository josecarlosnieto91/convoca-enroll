const { chromium } = require('playwright');
const { readFileSync, writeFileSync } = require('fs');

// Get the cookie value from the last successful PHP generation
const cookie_value = 'admin|1781371601|auth_65Et7Cb5kaOu6FLMwt8qEdOU4j6KcIbQrVXYgbL|e1236ef188c74fe6343cc3989b197b4e0731a0951914b69334d24c7b92d1a442';

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  
  // First, navigate to the site to establish the context
  const setupPage = await context.newPage();
  await setupPage.goto('https://getconvoca.app/');
  await setupPage.close();
  
  // Now add the cookie
  await context.addCookies([{
    name: 'wordpress_logged_in_12ce7f849505e073301a882aa8b39e41',
    value: cookie_value,
    domain: 'getconvoca.app',
    path: '/',
    httpOnly: true,
    secure: true,
    sameSite: 'Lax'
  }]);
  
  // Verify cookie is stored
  const cookies = await context.cookies();
  const hasAuth = cookies.some(c => c.name.includes('logged_in'));
  console.log('Auth cookie stored:', hasAuth);
  
  if (!hasAuth) {
    console.log('Failed to store cookie');
    await browser.close();
    return;
  }
  
  // Now navigate to wp-admin using the SAME page (same context)
  const page = await context.newPage();
  
  // Intercept request to check cookies
  page.on('request', req => {
    if (req.url().includes('wp-admin')) {
      const c = req.headers()['cookie'] || '';
      console.log(`Req to wp-admin, cookie length: ${c.length}, has auth: ${c.includes('logged_in')}`);
    }
  });
  
  await page.goto('https://getconvoca.app/wp-admin/');
  console.log('Final URL:', page.url());
  
  if (!page.url().includes('wp-login')) {
    console.log('SUCCESS!');
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  } else {
    // Check for error
    const err = await page.locator('#login_error').textContent().catch(() => 'none');
    console.log('Error:', err?.substring(0, 200));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
