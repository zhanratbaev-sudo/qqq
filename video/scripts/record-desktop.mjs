// Записывает десктопные клипы PetGo (1440x900, 1.5x) с видимым курсором для ролика Vet.izi.
// Клипы → video/vetizi/assets/clips/<name>.mp4 + <name>.json (метки времени, как в record.mjs).
//
// Запуск:  node video/scripts/record-desktop.mjs                 — все клипы
//          node video/scripts/record-desktop.mjs d-request d-crm  — только указанные
// Нужен локальный стенд: video/demo-site/setup.sh /путь/к/petgo-cabinet.zip
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'vetizi', 'assets', 'clips');
const STATE = path.join(ROOT, '.auth');
const BASE = (process.env.PETGO_BASE_URL || 'http://127.0.0.1:8080').replace(/\/$/, '');
const cab = (pg) => `${BASE}/кабинет/` + (pg ? `?pg=${pg}` : '');
const LOGIN = process.env.PETGO_LOGIN || 'owner';
const PASSWORD = process.env.PETGO_PASSWORD || 'Demo-Owner-2026!';

const DESKTOP = { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1.5 };
const DESKTOP_SIZE = { width: 2160, height: 1350 };
const MOBILE = { ...devices['iPhone 14'], viewport: { width: 390, height: 844 } };
delete MOBILE.defaultBrowserType;
const MOBILE_SIZE = { width: 780, height: 1688 };

// Курсор-стрелка, который рисуется в странице (скринкаст системный курсор не захватывает).
const CURSOR_SCRIPT = `
  addEventListener('DOMContentLoaded', () => {
    const c = document.createElement('div');
    c.id = '__cur';
    c.innerHTML = '<svg width="34" height="40" viewBox="0 0 17 20"><path d="M1 1 L1 16 L5 12.4 L7.6 18.6 L10.2 17.5 L7.7 11.5 L13 11.5 Z" fill="#111" stroke="#fff" stroke-width="1.3" stroke-linejoin="round"/></svg>';
    c.style.cssText = 'position:fixed;left:0;top:0;z-index:2147483647;pointer-events:none;transform-origin:2px 2px;' +
      'transition:transform .12s ease-out;filter:drop-shadow(0 3px 5px rgba(0,0,0,.35));will-change:left,top;';
    const pos = JSON.parse(sessionStorage.getItem('__curpos') || '[-80,-80]');
    c.style.left = pos[0] + 'px'; c.style.top = pos[1] + 'px';
    document.documentElement.appendChild(c);
    addEventListener('mousemove', (e) => {
      c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px';
      sessionStorage.setItem('__curpos', JSON.stringify([e.clientX, e.clientY]));
    }, true);
    addEventListener('mousedown', () => { c.style.transform = 'scale(.82)'; }, true);
    addEventListener('mouseup', () => { c.style.transform = 'scale(1)'; }, true);
  });
`;

const pause = (page, s) => page.waitForTimeout(s * 1000);
const mousePos = new WeakMap();

// Плавное движение курсора по кривой easeInOutCubic.
async function moveTo(page, x, y, seconds = 0.7) {
  const [x0, y0] = mousePos.get(page) || [1500, 700];
  const steps = Math.max(8, Math.round(seconds * 60));
  for (let i = 1; i <= steps; i++) {
    const k = i / steps;
    const e = k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2;
    await page.mouse.move(x0 + (x - x0) * e, y0 + (y - y0) * e);
    await page.waitForTimeout(1000 / 60);
  }
  mousePos.set(page, [x, y]);
}

async function center(locator) {
  const el = locator.first();
  await el.scrollIntoViewIfNeeded();
  const b = await el.boundingBox();
  return [b.x + b.width / 2, b.y + b.height / 2];
}

// События для звука в ролике (клики и набор текста), время — по часам записи
let events = [];
const now = () => Date.now() / 1000;

async function typeText(page, text, delay) {
  const t = now();
  await page.keyboard.type(text, { delay });
  events.push({ type: 'type', t, dur: +(now() - t).toFixed(3), chars: text.length });
}

async function click(page, locator, seconds = 0.6) {
  const [x, y] = await center(typeof locator === 'string' ? page.locator(locator) : locator);
  await moveTo(page, x, y, seconds);
  await pause(page, 0.12);
  events.push({ type: 'click', t: now() });
  await page.mouse.down();
  await pause(page, 0.08);
  await page.mouse.up();
  await pause(page, 0.15);
}

async function typeAt(page, selector, text, delay = 45) {
  await click(page, selector, 0.45);
  await typeText(page, text, delay);
  await pause(page, 0.15);
}

// Кабинет открывается с раскрытым боковым меню; оно сворачивается после движения мыши в окне.
async function settle(page) {
  await page.mouse.move(1381, 861);
  await page.mouse.move(1380, 860);
  mousePos.set(page, [1380, 860]);
  await pause(page, 0.8);
}

async function smoothScroll(page, distance, seconds = 1.6) {
  await page.evaluate(async ([dist, ms]) => {
    const el = document.scrollingElement, start = el.scrollTop, t0 = performance.now();
    await new Promise((res) => {
      const step = (now) => {
        const k = Math.min(1, (now - t0) / ms);
        el.scrollTop = start + dist * (k < 0.5 ? 4 * k * k * k : 1 - Math.pow(-2 * k + 2, 3) / 2);
        k < 1 ? requestAnimationFrame(step) : res();
      };
      requestAnimationFrame(step);
    });
  }, [distance, seconds * 1000]);
}

// ---------- Сценарии ----------
const flows = {
  // Сайт клиники (пример — PetGo): первый экран и прокрутка
  async 'd-landing'(page, mark) {
    await page.goto(BASE + '/', { waitUntil: 'networkidle' });
    mark();
    await pause(page, 0.4);
    await moveTo(page, 900, 520, 0.9);
    await pause(page, 0.5);
    await smoothScroll(page, 820, 1.8);
    await pause(page, 0.5);
    await smoothScroll(page, 820, 1.8);
    await pause(page, 0.8);
  },

  // Клиент оставляет заявку на сайте
  async 'd-request'(page, mark) {
    await page.goto(BASE + '/', { waitUntil: 'networkidle' });
    await page.evaluate(() => {
      const f = document.getElementById('petgo-request');
      scrollTo(0, f.getBoundingClientRect().top + scrollY - 90);
    });
    await pause(page, 3.1); // форма принимает заявку не раньше чем через 3 с после загрузки
    mark();
    const f = '#petgo-request ';
    await typeAt(page, f + 'input[name="name"]', 'Жанар');
    await typeAt(page, f + 'input[name="phone"]', '+7 700 123 45 67', 30);
    await click(page, page.locator(f + 'label:has(input[name="contact"][value="write"])'), 0.45);
    await click(page, page.locator(f + 'label:has(input[name="species"][value="dog"])'), 0.45);
    await typeAt(page, f + 'input[name="weight"]', '11');
    await typeAt(page, f + 'input[name="breed"]', 'Корги');
    await click(page, f + 'input[name="consent"]', 0.45);
    mark('ready-submit');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), click(page, f + 'button[type="submit"]', 0.5)]);
    await page.evaluate(() => {
      const f = document.getElementById('petgo-request');
      scrollTo(0, f.getBoundingClientRect().top + scrollY - 90);
    });
    mark('submitted');
    await pause(page, 2.5);
  },

  async 'd-login'(page, mark, ctx) {
    await page.goto(cab(), { waitUntil: 'networkidle' });
    mark();
    await typeAt(page, 'input[name="log"]', LOGIN, 60);
    await typeAt(page, 'input[name="pwd"]', PASSWORD, 25);
    await Promise.all([page.waitForLoadState('networkidle'), click(page, 'button[type="submit"]:has-text("Войти")', 0.5)]);
    mark('in');
    await pause(page, 2);
    await ctx.storageState({ path: path.join(STATE, 'desktop.json') });
  },

  // CRM: новая заявка с сайта уже наверху списка
  async 'd-crm'(page, mark) {
    await page.goto(cab('requests'), { waitUntil: 'networkidle' });
    await settle(page);
    mark();
    await pause(page, 0.5);
    await moveTo(page, 640, 260, 0.8);
    await pause(page, 0.6);
    const [x, y] = await center(page.locator('a, button', { hasText: 'WhatsApp' }));
    await moveTo(page, x, y, 0.7); // только наводим: ссылка ведёт на wa.me
    await pause(page, 1.2);
  },

  // Меню кабинета раскрывается только при наведении курсора (как в PetGo 27)
  async 'd-menu'(page, mark) {
    await page.goto(cab('requests'), { waitUntil: 'networkidle' });
    await settle(page);
    mark();
    await pause(page, 0.5);
    await moveTo(page, 36, 330, 0.9); // наводим на боковое меню — оно раскрывается
    mark('open');
    await pause(page, 0.5);
    for (const y of [150, 200, 250, 300, 380]) { await moveTo(page, 110, y, 0.28); await pause(page, 0.12); }
    mark('items');
    await moveTo(page, 640, 300, 0.7); // уводим курсор — меню сворачивается
    mark('closed');
    await pause(page, 0.6);
    await moveTo(page, 300, 280, 0.6); // показываем новую заявку
    await pause(page, 1.2);
  },

  async 'd-orders'(page, mark) {
    await page.goto(cab('orders'), { waitUntil: 'networkidle' });
    await settle(page);
    mark();
    await moveTo(page, 760, 360, 0.9);
    await pause(page, 0.6);
    await moveTo(page, 1100, 420, 0.8);
    await pause(page, 0.8);
  },

  async 'd-cal'(page, mark) {
    await page.goto(cab('cal'), { waitUntil: 'networkidle' });
    await settle(page);
    mark();
    await moveTo(page, 1040, 470, 0.9);
    await pause(page, 0.6);
    await moveTo(page, 1180, 520, 0.6);
    await pause(page, 0.8);
  },

  // Сотрудники: добавить и назначить роль
  async 'd-staff'(page, mark) {
    await page.goto(cab('staff'), { waitUntil: 'networkidle' });
    await settle(page);
    mark();
    await pause(page, 0.4);
    await click(page, 'button.pgc-create.desk', 0.7);
    await click(page, 'button.pgc-mi[data-open="pgc-m-staff"]', 0.45);
    await pause(page, 0.3);
    mark('form');
    const f = '#pgc-m-staff ';
    await typeAt(page, f + 'input[name="name"]', 'Марина Ли', 40);
    await typeAt(page, f + 'input[name="login"]', 'marina', 40);
    await typeAt(page, f + 'input[name="pass"]', 'Demo-Staff-2026!', 18);
    const role = page.locator(f + 'select[name="role"]');
    await click(page, role, 0.45);
    await role.selectOption('manager');
    await pause(page, 0.4);
    await Promise.all([page.waitForLoadState('networkidle'), click(page, f + 'button[type="submit"]', 0.5)]);
    mark('saved');
    await pause(page, 2);
  },

  async 'd-chat'(page, mark) {
    await page.goto(cab('home'), { waitUntil: 'networkidle' });
    await settle(page);
    mark();
    await click(page, 'button.pgc-navb[data-chat] svg', 0.7); // в свёрнутом меню видна только иконка
    await moveTo(page, 340, 180, 0.5); // с меню прямо на список чатов: меню сворачивается, список остаётся
    await pause(page, 0.7);
    await click(page, page.locator('.pgc-cr', { hasText: 'Дана Ким' }), 0.5);
    await pause(page, 0.5);
    mark('open');
    const box = page.locator('textarea:visible').last();
    await click(page, box, 0.5);
    await typeText(page, 'Отлично! Завтра в 10:00 снова Рекс', 35);
    events.push({ type: 'send', t: now() });
    await page.keyboard.press('Enter');
    mark('sent');
    await pause(page, 2);
  },

  // Телефон сотрудника: приложение PWA, главная кабинета
  async 'm-app'(page, mark) {
    await page.goto(cab('home'), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 6);
  },
};

const NEEDS_LOGIN = new Set(['d-menu', 'd-crm', 'd-orders', 'd-cal', 'd-staff', 'd-chat', 'm-app']);

async function record(browser, name) {
  const mobile = name.startsWith('m-');
  const stateFile = NEEDS_LOGIN.has(name) && path.join(STATE, 'desktop.json');
  if (stateFile && !fs.existsSync(stateFile)) throw new Error('Сначала запишите вход: d-login');
  const size = mobile ? MOBILE_SIZE : DESKTOP_SIZE;
  const tmp = path.join(OUT, '.tmp-' + name);
  fs.rmSync(tmp, { recursive: true, force: true });
  fs.mkdirSync(tmp, { recursive: true });
  const ctx = await browser.newContext({
    ...(mobile ? MOBILE : DESKTOP), locale: 'ru-RU', timezoneId: 'Asia/Almaty',
    ...(stateFile ? { storageState: stateFile } : {}),
  });
  if (!mobile) await ctx.addInitScript(CURSOR_SCRIPT);
  const page = await ctx.newPage();
  // мышь стоит в пустом правом нижнем углу: в точке (0,0) она «наводится» на боковое меню и раскрывает его
  if (!mobile) { await page.mouse.move(1380, 860); mousePos.set(page, [1380, 860]); }
  const cdp = await ctx.newCDPSession(page);
  const frames = [];
  cdp.on('Page.screencastFrame', ({ data, sessionId }) => {
    const file = path.join(tmp, `f${String(frames.length).padStart(6, '0')}.jpg`);
    fs.writeFileSync(file, Buffer.from(data, 'base64'));
    frames.push({ file, t: Date.now() / 1000 });
    cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
  });
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: size.width, maxHeight: size.height });
  const marks = {};
  const mark = (label = 'ready') => { marks[label] = Date.now() / 1000; };
  events = [];
  try {
    await flows[name](page, mark, ctx);
  } finally {
    const end = Date.now() / 1000;
    await cdp.send('Page.stopScreencast').catch(() => {});
    await ctx.close();
    if (frames.length) {
      const list = frames.map((f, i) => `file '${f.file}'\nduration ${((frames[i + 1]?.t ?? end) - f.t).toFixed(4)}`);
      fs.writeFileSync(path.join(tmp, 'list.txt'), list.join('\n') + `\nfile '${frames.at(-1).file}'\n`);
      execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', path.join(tmp, 'list.txt'),
        '-vf', `fps=30,scale=${size.width}:${size.height}:force_original_aspect_ratio=decrease,pad=${size.width}:${size.height}:-1:-1:white`,
        '-c:v', 'libx264', '-crf', '16', '-preset', 'fast', '-pix_fmt', 'yuv420p', '-g', '15', path.join(OUT, `${name}.mp4`)]);
    }
    fs.rmSync(tmp, { recursive: true, force: true });
    const t0 = frames[0]?.t ?? end;
    const rel = Object.fromEntries(Object.entries(marks).map(([k, v]) => [k, +Math.max(0, v - t0).toFixed(3)]));
    const ev = events.map((e) => ({ ...e, t: +Math.max(0, e.t - t0).toFixed(3) }));
    fs.writeFileSync(path.join(OUT, `${name}.json`), JSON.stringify({ marks: rel, length: +(end - t0).toFixed(3), events: ev }, null, 2));
    console.log(`✓ ${name}.mp4 (${(end - t0).toFixed(1)} c, метки: ${Object.entries(rel).map(([k, v]) => `${k}=${v}`).join(', ')})`);
  }
}

const wanted = process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(flows);
fs.mkdirSync(OUT, { recursive: true });
fs.mkdirSync(STATE, { recursive: true });
const browser = await chromium.launch();
try {
  for (const name of wanted) {
    if (!flows[name]) throw new Error(`Нет сценария ${name}. Есть: ${Object.keys(flows).join(', ')}`);
    await record(browser, name);
  }
} finally {
  await browser.close();
}
