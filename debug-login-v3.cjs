const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  // Set the test cookie manually first
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('form', { timeout: 10000 });
  
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.check('input[name="rememberme"]');
  
  // Remove redirect_to to stay on login page after auth
  await page.evaluate(() => {
    const r = document.querySelector('input[name="redirect_to"]');
    if (r) r.value = '';
  });
  
  await page.click('input[type="submit"]');
  await page.waitForTimeout(5000);
  
  console.log('URL:', page.url());
  
  if (page.url().includes('reauth')) {
    // Try different approach: clear cookies and try again
    console.log('Still reauth - trying with fresh context without redirect');
    
    const page2 = await browser.newPage();
    await page2.goto('https://getconvoca.app/wp-login.php?redirect_to=');
    await page2.fill('input[name="log"]', 'admin');
    await page2.fill('input[name="pwd"]', 'test1234');
    await page2.check('input[name="rememberme"]');
    await page2.click('input[type="submit"]');
    await page2.waitForTimeout(3000);
    console.log('URL2:', page2.url());
    
    if (!page2.url().includes('reauth')) {
      console.log('LOGIN SUCCESS on page2');
    } else {
      // Get the actual content
      const msg = await page2.locator('.message, .notice, p').first().textContent().catch(() => 'none');
      console.log('Message:', msg?.substring(0, 300));
    }
    await page2.close();
  } else if (!page.url().includes('wp-login')) {
    console.log('LOGIN SUCCESS');
  } else {
    const msg = await page.locator('.message, .notice, p').first().textContent().catch(() => 'none');
    console.log('Message:', msg?.substring(0, 300));
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
