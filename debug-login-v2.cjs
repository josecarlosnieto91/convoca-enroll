const { chromium } = require('playwright');
async function main() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  
  // Step 1: Go to login page
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('form', { timeout: 10000 });
  
  // Step 2: Get ALL form field names
  const inputs = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('input')).map(i => ({ 
      name: i.name, 
      type: i.type, 
      id: i.id,
      placeholder: i.placeholder 
    }));
  });
  console.log('Form fields:', JSON.stringify(inputs, null, 2));
  
  // Step 3: Try submitting with the correct field names
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  
  // Check for any hidden fields
  const hidden = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('input[type="hidden"]')).map(i => i.name);
  });
  console.log('Hidden fields:', hidden);
  
  // Submit
  await page.click('input[type="submit"]');
  await page.waitForTimeout(3000);
  
  console.log('After submit URL:', page.url());
  
  // Check if we're still on login page
  if (page.url().includes('wp-login.php')) {
    const html = await page.content();
    // Check for error or reauth message
    const errorText = await page.locator('#login_error').textContent().catch(() => 'none');
    console.log('Login error:', errorText?.substring(0, 300));
    
    // Check for reauth message
    const msg = await page.locator('.message').textContent().catch(() => 'none');
    console.log('Message:', msg?.substring(0, 300));
  } else {
    console.log('Login SUCCESS, on page:', page.url());
  }
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
