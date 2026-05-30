import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';
const OUT = '/tmp/cv5-shots';
mkdirSync(OUT, { recursive: true });
async function run() {
  const browser = await chromium.launch({ headless: true });
  for (const pal of ['mint','peach']) {
    for (const tpl of ['nature-classic','minimal-corporate','modern-ngo']) {
      const key = `${pal}-${tpl}`;
      const page = await browser.newPage({ viewport: { width: 700, height: 500 } });
      await page.goto('file:///tmp/cv5-previews/' + key + '.html', { waitUntil: 'networkidle', timeout: 15000 });
      await page.waitForTimeout(300);
      const ok = (await page.locator('.qr-abs').isVisible().catch(()=>false))
            && (await page.locator('.poster-brands-footer').isVisible().catch(()=>false))
            && (await page.locator('.title, .main-title').first().isVisible().catch(()=>false));
      await page.locator('.pw').screenshot({ path: `${OUT}/${key}.png` });
      await page.close();
      const kb = Math.round(readFileSync(`${OUT}/${key}.png`).length / 1024);
      console.log(`  ${key}: ${kb}KB ${ok ? "OK" : "MISSING"}`);
    }
  }
  await browser.close();
}
run().catch(e => { console.error(e); process.exit(1); });
