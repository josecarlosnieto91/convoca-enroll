const { chromium } = require('playwright');
const { writeFileSync } = require('fs');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  
  await page.route('**/*', async (route) => {
    const request = route.request();
    if (request.url().includes('wp-login') && request.method() === 'POST') {
      const response = await route.fetch();
      const headers = response.headers();
      const setCookie = headers['set-cookie'];
      if (setCookie) {
        // Split into individual cookies
        const cookieStrings = setCookie.split(/\n(?=wordpress|wordpress_test)/);
        console.log('Total cookie strings:', cookieStrings.length);
        cookieStrings.forEach((cs, i) => {
          const namePart = cs.split('=')[0].split(';')[0].trim();
          const valuePart = cs.split(';')[0].split('=').slice(1).join('=');
          const hasExpired = cs.includes('Max-Age=0');
          console.log(`  Cookie ${i}: ${namePart}${hasExpired ? ' (CLEARING)' : ' (SETTING)'} value_len=${valuePart.length}`);
          if (namePart.includes('logged_in') && !hasExpired) {
            console.log('    *** THIS IS THE AUTH COOKIE ***');
            // Extract just the cookie value
            const decoded = decodeURIComponent(valuePart);
            console.log('    Value:', decoded.substring(0, 100));
          }
        });
        
        // Check for the actual logged_in cookie specifically
        const loggedInMatch = setCookie.match(/wordpress_logged_in_[^=]+=([^;]+)/);
        if (loggedInMatch) {
          const raw = loggedInMatch[1];
          const hasExpired = setCookie.includes('wordpress_logged_in') && setCookie.includes('Max-Age=0');
          console.log('\nLogged-in cookie found:');
          console.log('  Raw:', raw.substring(0, 100));
          console.log('  Decoded:', decodeURIComponent(raw).substring(0, 100));
          console.log('  Expired:', hasExpired);
        } else {
          console.log('\nNO wordpress_logged_in cookie in response!');
        }
      }
      await route.fulfill({ response });
    } else {
      await route.continue();
    }
  });
  
  await page.goto('https://getconvoca.app/wp-login.php');
  await page.waitForSelector('input[name="log"]');
  await page.fill('input[name="log"]', 'admin');
  await page.fill('input[name="pwd"]', 'test1234');
  await page.click('input[type="submit"]');
  await page.waitForTimeout(2000);
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
