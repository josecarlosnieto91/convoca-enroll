const { chromium } = require('playwright');
const { writeFileSync } = require('fs');

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
  });
  
  // DON'T follow redirects - capture the cookie from the POST response
  const page = await context.newPage();
  
  // Disable auto redirects
  await page.route('**/*', async (route) => {
    const request = route.request();
    if (request.url().includes('wp-login') && request.method() === 'POST') {
      const response = await route.fetch();
      // Get the Set-Cookie header(s)
      const headers = response.headers();
      console.log('POST response status:', response.status());
      console.log('Location:', headers['location']);
      
      // Check if set-cookie exists (multiple values may be comma-separated in HTTP/2)
      const setCookie = headers['set-cookie'];
      if (setCookie) {
        console.log('Set-Cookie found!');
        console.log('  Full value (first 300):', setCookie.substring(0, 300));
        
        // Parse the logged_in cookie
        const cookies = setCookie.split(/, (?=wordpress_logged_in)/);
        const loginCookie = cookies.find(c => c.includes('wordpress_logged_in'));
        if (loginCookie) {
          console.log('\nAuth cookie found!');
          console.log('  Raw:', loginCookie.substring(0, 200));
          
          // Extract value and attributes
          const parts = loginCookie.split(';')[0];
          const eqPos = parts.indexOf('=');
          const name = parts.substring(0, eqPos);
          const value = parts.substring(eqPos + 1);
          console.log('  Name:', name);
          console.log('  Value (decoded):', decodeURIComponent(value).substring(0, 100));
          
          // Now construct auth.json with this cookie
          const authState = {
            cookies: [{
              name: name,
              value: decodeURIComponent(value),
              domain: 'getconvoca.app',
              path: '/',
              httpOnly: true,
              secure: true,
              sameSite: 'Lax'
            }],
            origins: []
          };
          writeFileSync('/home/josecnr91/convoca-enroll/auth.json', JSON.stringify(authState, null, 2));
          console.log('\nauth.json saved!');
        }
      } else {
        console.log('No Set-Cookie header found');
        console.log('All headers:', Object.keys(headers).join(', '));
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
