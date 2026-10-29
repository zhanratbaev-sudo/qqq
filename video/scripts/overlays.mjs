// Рендерит графику для каждой сцены из scenes.json в PNG 1080x1920 через Chromium:
//   bg-NN.png   — фон с градиентом и рамкой телефона
//   text-NN.png — надпись (прозрачный фон, анимируется в build.mjs)
//   mask.png    — скругления экрана
// Слова в *звёздочках* выделяются акцентным цветом.
import { createRequire } from 'node:module';
const { chromium } = createRequire(import.meta.url)('playwright');
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'build', 'overlays');
const cfg = JSON.parse(fs.readFileSync(path.join(ROOT, 'scenes.json'), 'utf8'));

// Безопасные зоны: 150 px сверху и снизу не трогаем (там кнопки Reels/TikTok).
export const LAYOUT = {
  W: 1080, H: 1920, SAFE: 150,
  caption: { top: 175, height: 272 },               // 175..447, тень не залезает на рамку
  screen: { x: 250, y: 486, w: 580, h: 1255, r: 46 }, // 390x844 * 1.487, низ рамки ≈ 1755 < 1770
  bezel: 14,
};
const { W, H, caption, screen: S, bezel: B } = LAYOUT;
const { colorFrom, colorTo, accent } = cfg.brand;

const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
// короткие предлоги/союзы не оставляем висеть в конце строки
const nbsp = (s) => s.replace(/(^|[\s*])([а-яёА-ЯЁ]{1,2}) /g, '$1$2\u00A0');
const rich = (s) => esc(nbsp(s)).replace(/\*(.+?)\*/g, `<span class="acc">$1</span>`);

const PAW = `<svg viewBox="0 0 64 64" width="100%" height="100%"><g fill="currentColor">
  <ellipse cx="32" cy="42" rx="14" ry="12"/><ellipse cx="15" cy="27" rx="6" ry="8"/><ellipse cx="49" cy="27" rx="6" ry="8"/>
  <ellipse cx="24" cy="15" rx="5.5" ry="7.5"/><ellipse cx="40" cy="15" rx="5.5" ry="7.5"/></g></svg>`;

const base = `
  *{margin:0;padding:0;box-sizing:border-box}
  html,body{width:${W}px;height:${H}px;overflow:hidden;font-family:'Inter Display','Inter',sans-serif;-webkit-font-smoothing:antialiased}
  .acc{color:${accent}}
`;

const bgHtml = () => `<style>${base}
  body{background:linear-gradient(160deg,${colorFrom} 0%,${colorTo} 100%);position:relative}
  .blob{position:absolute;border-radius:50%;filter:blur(2px);background:rgba(255,255,255,.07)}
  .paw{position:absolute;color:rgba(255,255,255,.06)}
  .phone{position:absolute;left:${S.x - B}px;top:${S.y - B}px;width:${S.w + 2 * B}px;height:${S.h + 2 * B}px;
    border-radius:${S.r + B}px;background:#0b1620;box-shadow:0 40px 90px rgba(0,0,0,.45),0 0 0 2px rgba(255,255,255,.18)}
  .scr{position:absolute;left:${B}px;top:${B}px;width:${S.w}px;height:${S.h}px;border-radius:${S.r}px;background:#fff}
</style>
<div class="blob" style="left:-220px;top:900px;width:620px;height:620px"></div>
<div class="blob" style="right:-260px;top:120px;width:560px;height:560px"></div>
<div class="paw" style="left:40px;top:1480px;width:190px;height:190px;transform:rotate(-20deg)">${PAW}</div>
<div class="paw" style="right:50px;top:620px;width:150px;height:150px;transform:rotate(18deg)">${PAW}</div>
<div class="phone"><div class="scr"></div></div>`;

const textHtml = (title) => `<style>${base}
  body{background:transparent}
  .cap{position:absolute;left:60px;right:60px;top:${caption.top}px;height:${caption.height}px;display:flex;align-items:center;justify-content:center;text-align:center}
  h1{font-weight:900;color:#fff;letter-spacing:-1.5px;line-height:1.06;text-wrap:balance;text-shadow:0 4px 18px rgba(0,0,0,.25)}
</style>
<div class="cap"><h1 id="t">${rich(title)}</h1></div>
`;

const finalBgHtml = () => `<style>${base}
  body{background:radial-gradient(120% 80% at 50% 30%,${colorFrom} 0%,${colorTo} 100%)}
  .blob{position:absolute;border-radius:50%;background:rgba(255,255,255,.07)}
</style>
<div class="blob" style="left:-260px;top:1250px;width:700px;height:700px"></div>
<div class="blob" style="right:-240px;top:60px;width:600px;height:600px"></div>`;

const finalTextHtml = (s) => `<style>${base}
  body{background:transparent;color:#fff}
  .wrap{position:absolute;left:70px;right:70px;top:${LAYOUT.SAFE + 60}px;bottom:${LAYOUT.SAFE + 60}px;
    display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:56px}
  .logo{width:220px;height:220px;border-radius:60px;background:#fff;color:${colorFrom};padding:42px;box-shadow:0 30px 70px rgba(0,0,0,.3)}
  h1{font-size:104px;font-weight:900;letter-spacing:-2px;line-height:1.04;text-wrap:balance;text-shadow:0 4px 18px rgba(0,0,0,.25)}
  ul{list-style:none;display:flex;flex-direction:column;gap:22px;font-size:54px;font-weight:700}
  .cta{background:${accent};color:#10202a;font-weight:800;font-size:50px;padding:30px 56px;border-radius:999px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
</style>
<div class="wrap">
  <div class="logo">${PAW}</div>
  <h1>${rich(s.title)}</h1>
  <ul>${(s.contacts || []).map((c) => `<li>${esc(c)}</li>`).join('')}</ul>
  ${s.cta ? `<div class="cta">${esc(s.cta)}</div>` : ''}
</div>`;

const maskHtml = () => `<style>*{margin:0}body{background:#000;width:${S.w}px;height:${S.h}px}
  div{width:100%;height:100%;background:#fff;border-radius:${S.r}px}</style><div></div>`;

fs.rmSync(OUT, { recursive: true, force: true });
fs.mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: W, height: H } });
const shot = async (html, file, transparent = false, clip) => {
  await page.setContent(`<!doctype html><meta charset="utf-8">${html}`, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  // подбираем кегль, чтобы надпись влезла в зону подписи
  await page.evaluate((maxH) => {
    const t = document.getElementById('t');
    if (!t) return;
    let fs = 96;
    do { t.style.fontSize = fs + 'px'; fs -= 2; } while (t.getBoundingClientRect().height > maxH && fs > 54);
  }, caption.height);
  await page.screenshot({ path: path.join(OUT, file), omitBackground: transparent, ...(clip ? { clip } : {}) });
};

await shot(maskHtml(), 'mask.png', false, { x: 0, y: 0, width: S.w, height: S.h });
await shot(bgHtml(), 'bg-phone.png');
await shot(finalBgHtml(), 'bg-final.png');
for (const [i, s] of cfg.scenes.entries()) {
  const nn = String(i).padStart(2, '0');
  await shot(s.final ? finalTextHtml(s) : textHtml(s.title), `text-${nn}.png`, true);
}
await browser.close();
fs.writeFileSync(path.join(OUT, 'layout.json'), JSON.stringify(LAYOUT, null, 2));
console.log(`✓ графика для ${cfg.scenes.length} сцен → ${path.relative(process.cwd(), OUT)}`);
