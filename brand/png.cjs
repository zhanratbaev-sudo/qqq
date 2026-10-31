// SVG → PNG той же папки (прозрачный фон). node png.cjs <папка>
const { chromium } = require('playwright');
const fs = require('fs'), path = require('path');
(async () => {
  const dir = process.argv[2];
  const b = await chromium.launch();
  const p = await b.newPage();
  for (const f of fs.readdirSync(dir).filter(f => f.endsWith('.svg'))) {
    const svg = fs.readFileSync(path.join(dir, f), 'utf8');
    const [, w, h] = svg.match(/width="(\d+)" height="(\d+)"/).map(Number);
    await p.setViewportSize({ width: w, height: h });
    await p.setContent(`<html><body style="margin:0;background:transparent">${svg}</body></html>`);
    await p.screenshot({ path: path.join(dir, f.replace('.svg', '.png')), omitBackground: true, clip: { x: 0, y: 0, width: w, height: h } });
  }
  await b.close();
})();
