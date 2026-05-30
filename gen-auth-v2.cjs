const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('#user_login', { timeout: 15000 });
  
  // Use admin/admin credentials
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'SEPTIEMBRE91');
  await page.click('#wp-submit');
  
  await page.waitForURL(/wp-admin/, { timeout: 15000 });
  console.log('Login success, URL:', page.url());
  
  await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  console.log('auth.json created');
  
  await browser.close();
}
main().catch(e => { console.error('Error:', e.message); process.exit(1); });
