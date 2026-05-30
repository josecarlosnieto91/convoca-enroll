const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Intercept the POST response to check headers
  page.on('response', resp => {
    if (resp.url().includes('wp-login.php') && resp.request().method() === 'POST') {
      const headers = resp.headers();
      console.log('POST response headers:');
      Object.entries(headers).forEach(([k, v]) => {
        if (k.toLowerCase().includes('cookie') || k.toLowerCase().includes('set')) {
          console.log(`  ${k}: ${v.substring(0, 100)}`);
        }
      });
    }
    if (resp.url().includes('wp-admin') && resp.status() === 302) {
      console.log('wp-admin 302 headers:');
      Object.entries(resp.headers()).forEach(([k, v]) => {
        if (k.toLowerCase().includes('cookie') || k.toLowerCase().includes('set') || k === 'location') {
          console.log(`  ${k}: ${v.substring(0, 100)}`);
        }
      });
    }
  });
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('input[name="log"]');
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.click('input[type="submit"]');
  await page.waitForTimeout(2000);
  
  console.log('Final URL:', page.url());
  
  const cookies = await context.cookies();
  console.log('Cookies after login:', cookies.length);
  cookies.forEach(c => {
    if (c.name.includes('wordpress')) {
      console.log(`  ${c.name}: ${c.value.substring(0, 30)}... (domain:${c.domain}, secure:${c.secure})`);
    }
  });
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
