import { chromium } from '@playwright/test';
import { readFileSync, mkdirSync } from 'fs';

const OUT = '/tmp/convoca-pw-screenshots';
mkdirSync(OUT, { recursive: true });

async function run() {
  const browser = await chromium.launch({ headless: true });
  const results = [];

  for (const tpl of ['nature-classic', 'minimal-corporate', 'modern-ngo']) {
    for (const fmt of ['square', 'story', 'facebook']) {
      const key = `${tpl}-${fmt}`;
      const htmlPath = `/tmp/convoca-html-previews/${key}.html`;
      const pngPath = `${OUT}/${key}.png`;
      
      const page = await browser.newPage({ viewport: { width: 800, height: 800 } });
      await page.goto('file://' + htmlPath, { waitUntil: 'networkidle', timeout: 15000 });
      await page.waitForTimeout(300);
      
      const preview = page.locator('.preview-wrap');
      await preview.screenshot({ path: pngPath });
      await page.close();
      
      const bytes = readFileSync(pngPath).length;
      results.push({ key, kb: Math.round(bytes/1024) });
      console.log(`  ${key}: ${Math.round(bytes/1024)}KB`);
    }
  }
  
  await browser.close();
  
  console.log('\n=== ALL DONE ===');
  for (const r of results) console.log(`  ${r.key}: ${r.kb}KB`);
}

run().catch(e => { console.error(e); process.exit(1); });
