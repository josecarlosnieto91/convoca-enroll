const { chromium } = require('playwright');

const cookie_value = 'admin|1781371601|auth_65Et7Cb5kaOu6FLMwt8qEdOU4j6KcIbQrVXYgbL|e1236ef188c74fe6343cc3989b197b4e0731a0951914b69334d24c7b92d1a442';

async function main() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  
  await context.addCookies([{
    name: 'wordpress_logged_in_12ce7f849505e073301a882aa8b39e41',
    value: cookie_value,
    domain: 'getconvoca.app',
    path: '/',
    httpOnly: true,
    secure: true,
    sameSite: 'Lax'
  }]);
  
  // Check what cookies are stored
  const cookies = await context.cookies();
  console.log('Stored cookies:', cookies.length);
  cookies.forEach(c => {
    console.log(`  ${c.name}: ${c.value.substring(0, 30)}... domain=${c.domain} path=${c.path} secure=${c.secure}`);
  });
  
  const hasAuth = cookies.some(c => c.name.includes('wordpress_logged_in'));
  console.log('Auth cookie stored:', hasAuth);
  
  await browser.close();
}
main().catch(e => { console.error(e.message); process.exit(1); });
