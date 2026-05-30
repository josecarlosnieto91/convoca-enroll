const { chromium } = require('playwright');
const { readFileSync, writeFileSync } = require('fs');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('#user_login', { timeout: 15000 });
  
  // Check what login form fields exist
  const fields = await page.locator('input[type="text"], input[type="password"], input[name="log"], input[name="pwd"]').count();
  console.log('Login fields found:', fields);
  
  // Use WP standard field names
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'teste2e123');
  await page.click('input[type="submit"]');
  
  await page.waitForTimeout(3000);
  console.log('After login URL:', page.url());
  console.log('Page title:', await page.title());
  
  // Check cookies
  const cookies = await context.cookies();
  console.log('Cookies:', cookies.length);
  cookies.forEach(c => {
    if (c.name.includes('wordpress')) console.log('  ', c.name, ':', c.value.substring(0, 20));
  });
  
  if (cookies.some(c => c.name.includes('wordpress_logged_in'))) {
    await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
    console.log('auth.json saved with login cookie');
  } else {
    console.log('No login cookie found - login may have failed');
    // Check for error
    const error = await page.locator('#login_error').textContent().catch(() => 'none');
    console.log('Error:', error?.toString().substring(0, 200));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
