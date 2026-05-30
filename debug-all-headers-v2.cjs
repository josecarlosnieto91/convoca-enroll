const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  page.on('response', resp => {
    if (resp.url().includes('wp-login') && resp.status() === 302) {
      console.log('ALL headers from POST login 302:');
      Object.entries(resp.headers()).forEach(([k, v]) => {
        console.log(`  ${k}: ${v.substring(0, 120)}`);
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
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
