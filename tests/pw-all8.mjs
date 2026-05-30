import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'fs';
const OUT = '/tmp/all8-shots';
mkdirSync(OUT,{recursive:true});
async function run() {
  const browser = await chromium.launch({headless:true});
  const tpls = ['nature-classic','minimal-corporate','modern-ngo','educational-workshop','volunteer-campaign','kids-family','full-photo-hero','story-focused'];
  for (const tpl of tpls) {
    for (const fmt of ['square','story','facebook']) {
      const key = `${tpl}-${fmt}`;
      const page = await browser.newPage({viewport:{width:700,height:500}});
      await page.goto('file:///tmp/all8-previews/'+key+'.html',{waitUntil:'networkidle',timeout:15000});
      await page.waitForTimeout(300);
      const cta = await page.locator('.cta').isVisible().catch(()=>false);
      const title = await page.locator('.title').isVisible().catch(()=>false);
      const qr = await page.locator('.qr-abs').isVisible().catch(()=>false);
      await page.locator('.pw').screenshot({path:`${OUT}/${key}.png`});
      await page.close();
      const kb = Math.round(readFileSync(`${OUT}/${key}.png`).length/1024);
      console.log(`  ${key}: ${kb}KB CTA=${cta} Title=${title} QR=${qr}`);
    }
  }
  await browser.close();
}
run().catch(e=>{console.error(e);process.exit(1)});
