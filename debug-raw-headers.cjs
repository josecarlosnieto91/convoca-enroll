const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  page.on('response', resp => {
    if (resp.url().includes('wp-login') && resp.status() === 302) {
      // Try to get raw headers
      try {
        const rawHeaders = resp.headersArray();
        console.log('Raw headers (' + rawHeaders.length + '):');
        rawHeaders.forEach(h => {
          if (h.name.toLowerCase().includes('cookie') || h.name.toLowerCase().includes('set')) {
            console.log(`  ${h.name}: ${h.value.substring(0, 120)}`);
          }
        });
      } catch(e) {
        console.log('headersArray not available:', e.message);
        console.log('All headers:', JSON.stringify(resp.headers()));
      }
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
