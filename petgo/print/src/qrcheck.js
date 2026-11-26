// QR-коды читаются со страницы: берём готовую картинку, вырезаем каждый QR и распознаём jsQR (как телефон)
const { chromium } = require('playwright'); const fs = require('fs');
(async () => {
  const br = await chromium.launch(); const p = await (await br.newContext()).newPage();
  const png = fs.readFileSync(process.argv[2]).toString('base64');
  await p.setContent('<canvas id="c"></canvas>');
  await p.addScriptTag({ path: '/home/user/qqq/petgo/petgo-cabinet/assets/vendor/jsqr.min.js' });
  const res = await p.evaluate(async (b64) => {
    const img = new Image(); img.src = 'data:image/png;base64,' + b64; await img.decode();
    const out = [];
    /* три QR в нижнем блоке; масштаб — вся страница в пикселях / 794 */
    const k = img.width / 794, codes = [];
    for (const scale of [1, 0.35]) {   /* 0.35 — как маленькая фотография с телефона (~100 dpi) */
      const c = document.createElement('canvas'); c.width = img.width * scale; c.height = img.height * scale;
      const x = c.getContext('2d'); x.imageSmoothingEnabled = true; x.drawImage(img, 0, 0, c.width, c.height);
      const y0 = Math.round(820 * k * scale), h = Math.round(240 * k * scale), w3 = c.width / 3;
      for (let i = 0; i < 3; i++) {
        const d = x.getImageData(Math.round(i * w3), y0, Math.round(w3), h);
        const r = window.jsQR(d.data, d.width, d.height);
        out.push((scale === 1 ? 'full ' : 'small ') + (i + 1) + ': ' + (r ? r.data : 'NOT READ'));
      }
    }
    return out;
  }, png);
  console.log(res.join('\n')); await br.close();
})();
