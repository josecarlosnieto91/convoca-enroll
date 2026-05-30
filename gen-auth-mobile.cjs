const { chromium } = require('playwright');
async function main() {
  // Try with mobile-like user agent to bypass any device detection
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120.0.6099.230 Mobile Safari/537.36'
  });
  const page = await context.newPage();
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('input[name="log"]', { timeout: 10000 });
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.click('input[type="submit"]');
  
  try {
    await page.waitForURL(/wp-admin/, { timeout: 15000 });
    console.log('LOGIN SUCCESS:', page.url());
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
    const c = await context.cookies();
    console.log('Cookies:', c.length, 'login:', c.filter(x => x.name.includes('logged_in')).length);
  } catch (e) {
    console.log('FAILED, URL:', page.url());
    const err = await page.locator('#login_error').textContent().catch(() => 'none');
    console.log('Error:', err?.substring(0, 300));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
