const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('#user_login', { timeout: 15000 });
  
  // Check what the login form looks like
  const html = await page.content();
  const formAction = await page.locator('#loginform').getAttribute('action').catch(() => 'no form');
  console.log('Form action:', formAction);
  
  // Check if there's a custom login URL
  const loginUrl = page.url();
  console.log('Login URL:', loginUrl);
  
  // Try filling and clicking
  await page.fill('#user_login', 'josecnr91');
  await page.fill('#user_pass', 'SEPTIEMBRE91');
  await page.click('#wp-submit');
  
  // Wait a bit and check URL
  await page.waitForTimeout(3000);
  console.log('After submit URL:', page.url());
  
  // Check for error messages
  const errorText = await page.locator('#login_error').textContent().catch(() => 'no error');
  console.log('Error:', errorText?.substring(0, 200));
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
