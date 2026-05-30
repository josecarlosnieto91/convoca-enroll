const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Monitor cookies being set
  page.on('response', resp => {
    const setCookie = resp.headers()['set-cookie'];
    if (setCookie && setCookie.includes('wordpress_logged_in')) {
      console.log('AUTH COOKIE SET in response!');
    }
  });
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('input[name="log"]');
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.click('input[type="submit"]');
  
  // Wait for redirect chain to complete
  await page.waitForTimeout(3000);
  
  console.log('Final URL:', page.url());
  
  // Check all cookies
  const cookies = await context.cookies();
  const authCookies = cookies.filter(c => c.name.includes('wordpress_logged_in'));
  console.log('Auth cookies found:', authCookies.length);
  authCookies.forEach(c => console.log(`  ${c.name}: ${c.value.substring(0, 30)}...`));
  
  // Try to save storage state
  if (authCookies.length > 0) {
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
    console.log('auth.json saved');
  } else {
    console.log('No auth cookies to save');
    
    // Debug: check if we at least got redirected
    const cookies2 = await context.cookies();
    console.log('All cookies:', cookies2.map(c => c.name).join(', '));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
