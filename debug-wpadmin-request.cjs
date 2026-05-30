const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Check cookies BEFORE navigating
  page.on('request', req => {
    if (req.url() === 'https://getconvoca.app/wp-admin/') {
      console.log('REQUEST to wp-admin/:');
      console.log('  Cookie header:', req.headers()['cookie']?.substring(0, 200));
    }
  });
  
  // Check cookies after login POST
  page.on('response', resp => {
    if (resp.url().includes('wp-login') && resp.request().method() === 'POST') {
      console.log('Cookies after POST (via context):');
      context.cookies().then(cookies => {
        cookies.filter(c => c.name.includes('wordpress')).forEach(c => {
          console.log(`  ${c.name}: ${c.value.substring(0, 40)}...`);
        });
      });
    }
  });
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('input[name="log"]');
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.click('input[type="submit"]');
  await page.waitForTimeout(2000);
  
  const cookies = await context.cookies();
  const authCookies = cookies.filter(c => c.name.includes('logged_in'));
  console.log('\nFinal auth cookies:', authCookies.length);
  authCookies.forEach(c => console.log(`  ${c.name} (${c.domain}${c.path})`));
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
