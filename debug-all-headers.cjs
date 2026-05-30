const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Capture ALL response headers
  page.on('response', resp => {
    const url = resp.url();
    if (url.includes('wp-login') || url.includes('wp-admin')) {
      const headers = resp.headers();
      console.log(`\n${resp.status()} ${url.substring(0, 70)}:`);
      Object.entries(headers).forEach(([k, v]) => {
        if (k.includes('cookie') || k.includes('set') || k === 'location') {
          console.log(`  ${k}: ${v.substring(0, 150)}`);
        }
      });
    }
  });
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('input[name="log"]');
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.click('input[type="submit"]');
  await page.waitForTimeout(3000);
  
  console.log('\nFinal URL:', page.url());
  
  const cookies = await context.cookies();
  console.log('Cookies after:', cookies.filter(c => c.name.includes('wordpress')).map(c => c.name).join(', '));
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
