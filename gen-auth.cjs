const { chromium } = require('playwright');
const { writeFileSync } = require('fs');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('#user_login', { timeout: 15000 });
  
  const username = process.env.WP_USER || 'josecnr91';
  const password = process.env.WP_PASS || '';
  
  if (!password) {
    console.log('Please set WP_PASS environment variable');
    await browser.close();
    process.exit(1);
  }
  
  await page.fill('#user_login', username);
  await page.fill('#user_pass', password);
  await page.click('#wp-submit');
  
  await page.waitForURL(/wp-admin/, { timeout: 15000 });
  
  await context.storageState({ path: '/home/josecnr91/convoca-enroll/auth.json' });
  console.log('auth.json created');
  
  await browser.close();
}

main().catch(e => { console.error('Error:', e.message); process.exit(1); });
