const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Go directly to wp-admin - it should redirect to login, then auto-redirect back
  await page.goto('https://getconvoca.app/wp-admin/', { waitUntil: 'networkidle' });
  console.log('Initial URL:', page.url());
  
  // Fill login
  await page.waitForSelector('input[name="log"]', { timeout: 10000 });
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.check('input[name="rememberme"]');
  await page.click('input[type="submit"]');
  
  // Wait for redirect back to wp-admin
  try {
    await page.waitForURL('**/wp-admin/**', { timeout: 15000 });
    console.log('LOGIN SUCCESS, on:', page.url());
    
    // Save auth state
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
    const cookies = await context.cookies();
    console.log('Cookies with logged_in:', cookies.filter(c => c.name.includes('logged_in')).length);
    console.log('auth.json saved!');
  } catch (e) {
    console.log('Still on:', page.url());
    const body = await page.locator('body').textContent().catch(() => '');
    console.log('Body (first 200):', body.substring(0, 200));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
