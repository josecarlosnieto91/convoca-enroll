import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';
const OUT = '/tmp/convoca-multi-shots';
mkdirSync(OUT, { recursive: true });
async function run() {
  const browser = await chromium.launch({ headless: true });
  const pals = ['nature','ocean','sunset','wine','charcoal'];
  const tpls = ['nature-classic','minimal-corporate','modern-ngo'];
  for (const pal of pals) {
    for (const tpl of tpls) {
      const key = `${pal}-${tpl}-square`;
      const page = await browser.newPage({ viewport: { width: 800, height: 500 } });
      await page.goto('file:///tmp/convoca-multi-previews/' + key + '.html', { waitUntil: 'networkidle', timeout: 15000 });
      await page.waitForTimeout(200);
      const pw = page.locator('.pw');
      await pw.screenshot({ path: `${OUT}/${key}.png` });
      await page.close();
      const kb = Math.round(readFileSync(`${OUT}/${key}.png`).length / 1024);
      console.log(`  ${key}: ${kb}KB`);
    }
  }
  await browser.close();
}
run().catch(e => { console.error(e); process.exit(1); });
