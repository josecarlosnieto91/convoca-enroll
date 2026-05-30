import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';
const OUT = '/tmp/convoca-noolap-shots';
mkdirSync(OUT, { recursive: true });
async function run() {
  const browser = await chromium.launch({ headless: true });
  const pals = ['mint','peach'];
  const tpls = ['nature-classic','minimal-corporate','modern-ngo'];
  let all_ok = true;
  
  for (const pal of pals) {
    for (const tpl of tpls) {
      const key = `${pal}-${tpl}`;
      const page = await browser.newPage({ viewport: { width: 800, height: 600 } });
      await page.goto('file:///tmp/convoca-noolap-previews/' + key + '.html', { waitUntil: 'networkidle', timeout: 15000 });
      await page.waitForTimeout(500);
      
      // Take full render
      const pw = page.locator('.pw');
      await pw.screenshot({ path: `${OUT}/${key}.png` });
      
      // Check for overlapping via pixel analysis
      // Count unique color regions - more = less overlap
      await page.close();
      
      const kb = Math.round(readFileSync(`${OUT}/${key}.png`).length / 1024);
      console.log(`  ${key}: ${kb}KB`);
    }
  }
  await browser.close();
}
run().catch(e => { console.error(e); process.exit(1); });
