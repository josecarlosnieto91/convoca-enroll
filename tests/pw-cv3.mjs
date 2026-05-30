import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';
const OUT = '/tmp/cv3-shots';
mkdirSync(OUT, { recursive: true });
async function run() {
  const browser = await chromium.launch({ headless: true });
  const pals = ['mint','peach'];
  const tpls = ['nature-classic','minimal-corporate','modern-ngo'];
  for (const pal of pals) {
    for (const tpl of tpls) {
      const key = `${pal}-${tpl}`;
      const page = await browser.newPage({ viewport: { width: 800, height: 600 } });
      await page.goto('file:///tmp/cv3-previews/' + key + '.html', { waitUntil: 'networkidle', timeout: 15000 });
      await page.waitForTimeout(500);
      
      // Check specific elements exist and are visible
      const hasQR = await page.locator('.qr-abs').isVisible().catch(() => false);
      const hasBrands = await page.locator('.poster-brands-footer').isVisible().catch(() => false);
      const hasMeta = await page.locator('.meta-row, .meta-strip, .meta-grid').first().isVisible().catch(() => false);
      const hasTitle = await page.locator('.title, .main-title').first().isVisible().catch(() => false);
      
      await page.locator('.pw').screenshot({ path: `${OUT}/${key}.png` });
      await page.close();
      
      const kb = Math.round(readFileSync(`${OUT}/${key}.png`).length / 1024);
      const status = (hasQR && hasBrands && hasMeta && hasTitle) ? 'OK' : 'MISSING';
      console.log(`  ${key}: ${kb}KB QR=${hasQR} Brands=${hasBrands} Meta=${hasMeta} Title=${hasTitle} [${status}]`);
    }
  }
  await browser.close();
}
run().catch(e => { console.error(e); process.exit(1); });
