const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('#user_login', { timeout: 15000 });
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.click('input[type="submit"]');
  
  await page.waitForURL(/wp-admin/, { timeout: 15000 });
  console.log('Login OK, URL:', page.url());
  
  await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  
  // Verify cookies
  const cookies = await context.cookies();
  const loggedIn = cookies.filter(c => c.name.includes('logged_in'));
  console.log('Login cookies:', loggedIn.length);
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
