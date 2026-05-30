import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';

const OUT = '/tmp/convoca-collab-screenshots';
mkdirSync(OUT, { recursive: true });

async function run() {
  const browser = await chromium.launch({ headless: true });
  
  for (const tpl of ['nature-classic', 'minimal-corporate', 'modern-ngo']) {
    const key = `${tpl}-collab`;
    const page = await browser.newPage({ viewport: { width: 800, height: 800 } });
    await page.goto('file:///tmp/convoca-collab-previews/' + key + '.html', { waitUntil: 'networkidle', timeout: 15000 });
    await page.waitForTimeout(300);
    
    const pw = page.locator('.pw');
    await pw.screenshot({ path: `${OUT}/${key}.png` });
    await page.close();
    
    const kb = Math.round(readFileSync(`${OUT}/${key}.png`).length / 1024);
    console.log(`  ${key}: ${kb}KB`);
  }
  
  await browser.close();
  console.log('Done');
}

run().catch(e => { console.error(e); process.exit(1); });
