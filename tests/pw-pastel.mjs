import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';
const OUT = '/tmp/convoca-pastel-shots';
mkdirSync(OUT, { recursive: true });
async function run() {
  const browser = await chromium.launch({ headless: true });
  for (const pal of ['mint','peach']) {
    for (const tpl of ['nature-classic','minimal-corporate','modern-ngo']) {
      const key = `${pal}-${tpl}`;
      const page = await browser.newPage({ viewport: { width: 700, height: 700 } });
      await page.goto('file:///tmp/convoca-pastel-previews/' + key + '.html', { waitUntil: 'networkidle', timeout: 15000 });
      await page.waitForTimeout(300);
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
