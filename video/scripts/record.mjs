// Записывает экранные клипы PetGo в мобильной эмуляции iPhone 14 (390x844).
// Каждый сценарий пишется в video/clips/<name>.mp4, рядом — <name>.json с метками времени
// (ready — страница готова, дальше — моменты действий: submitted, saved и т. п.).
// В scenes.json сцена берёт кадры от метки: "clip": "staff", "mark": "saved", "start": -0.3.
//
// Запуск:  node video/scripts/record.mjs              — все клипы
//          node video/scripts/record.mjs chat staff   — только указанные
//
// Адрес и логины — из video/.env (см. .env.example). По умолчанию — локальный стенд demo-site.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CLIPS = process.env.CLIPS_DIR || path.join(ROOT, 'clips');
const STATE = path.join(ROOT, '.auth');
if (fs.existsSync(path.join(ROOT, '.env'))) process.loadEnvFile(path.join(ROOT, '.env'));

const env = (k, def) => {
  const v = process.env[k] ?? def;
  if (v === undefined || v === '') throw new Error(`Не задана переменная ${k} в video/.env`);
  return v;
};
const BASE = env('PETGO_BASE_URL', 'http://127.0.0.1:8080').replace(/\/$/, '');
const CABINET = env('PETGO_CABINET_PATH', '/кабинет/');
const url = (p) => (/^https?:/.test(p) ? p : BASE + (p.startsWith('/') ? p : '/' + p));
const cab = (pg) => url(CABINET) + (pg ? `?pg=${pg}` : '');

const IPHONE = { ...devices['iPhone 14'], viewport: { width: 390, height: 844 } }; // во весь экран, без панелей Safari
delete IPHONE.defaultBrowserType; // в контейнере только Chromium
const VIDEO_SIZE = { width: 780, height: 1688 }; // 2x от 390x844 для чёткости

// Видимый «тап» в фирменном цвете, чтобы на видео было понятно, куда нажали.
const TAP_SCRIPT = `
  addEventListener('pointerdown', (e) => {
    const d = document.createElement('div');
    d.style.cssText = 'position:fixed;z-index:2147483647;pointer-events:none;width:46px;height:46px;border-radius:50%;' +
      'background:rgba(2,76,70,.28);border:3px solid rgba(2,76,70,.85);left:' + (e.clientX - 23) + 'px;top:' + (e.clientY - 23) + 'px;' +
      'transition:transform .45s ease-out,opacity .45s ease-out;transform:scale(.6)';
    document.documentElement.appendChild(d);
    requestAnimationFrame(() => { d.style.transform = 'scale(1.5)'; d.style.opacity = '0'; });
    setTimeout(() => d.remove(), 600);
  }, true);
`;

const pause = (page, s) => page.waitForTimeout(s * 1000);

// Плавно прокручивает страницу (или самый большой прокручиваемый блок, если кабинет скроллит внутри себя).
async function smoothScroll(page, distance, seconds = 2.5) {
  await page.evaluate(async ([dist, ms]) => {
    let el = document.scrollingElement;
    if (el.scrollHeight <= el.clientHeight + 5) {
      const all = [...document.querySelectorAll('*')].filter((x) => {
        const o = getComputedStyle(x).overflowY;
        return (o === 'auto' || o === 'scroll') && x.scrollHeight > x.clientHeight + 5 && x.offsetParent;
      });
      all.sort((a, b) => b.clientHeight - a.clientHeight);
      if (all[0]) el = all[0];
    }
    const start = el.scrollTop, t0 = performance.now();
    await new Promise((res) => {
      const step = (now) => {
        const k = Math.min(1, (now - t0) / ms);
        const e = k < 0.5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2; // easeInOutQuad
        el.scrollTop = start + dist * e;
        k < 1 ? requestAnimationFrame(step) : res();
      };
      requestAnimationFrame(step);
    });
  }, [distance, seconds * 1000]);
}

// Плавно подводит элемент к верху экрана (с отступом сверху, чтобы не прятался под шапкой).
async function scrollToEl(page, locator, seconds = 1.5, offset = 90) {
  const box = await locator.first().evaluate((el) => el.getBoundingClientRect().top);
  await smoothScroll(page, box - offset, seconds);
}

async function tap(page, locator) {
  const el = typeof locator === 'string' ? page.locator(locator).first() : locator.first();
  await el.scrollIntoViewIfNeeded();
  await pause(page, 0.35);
  await el.click();
}

async function type(page, selector, text, delay = 65) {
  const el = page.locator(selector).first();
  await el.scrollIntoViewIfNeeded();
  await el.click();
  await el.pressSequentially(text, { delay });
  await pause(page, 0.25);
}

// ---------- Сценарии ----------
const flows = {
  // Лендинг: главный экран и прокрутка по услугам
  async home(page, mark) {
    await page.goto(url('/'), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 3);
    await smoothScroll(page, 760, 3);
    await pause(page, 1.2);
    await smoothScroll(page, 900, 3);
    await pause(page, 1.5);
  },

  // Заявка с сайта: клиент заполняет форму и отправляет
  async 'site-request'(page, mark) {
    await page.goto(url('/'), { waitUntil: 'networkidle' });
    await page.evaluate(() => {
      const f = document.getElementById('petgo-request');
      scrollTo(0, f.getBoundingClientRect().top + scrollY - 70);
    });
    await pause(page, 3.2); // форма принимает заявку не раньше чем через 3 с после загрузки
    mark();
    await pause(page, 0.6);
    await type(page, '#petgo-request input[name="name"]', 'Жанар');
    await type(page, '#petgo-request input[name="phone"]', '+7 700 123 45 67', 55);
    await tap(page, page.locator('#petgo-request label:has(input[name="contact"][value="write"])'));
    await tap(page, page.locator('#petgo-request label:has(input[name="species"][value="dog"])'));
    await type(page, '#petgo-request input[name="weight"]', '11');
    await type(page, '#petgo-request input[name="breed"]', 'Корги');
    await tap(page, page.locator('#petgo-request input[name="consent"]'));
    await pause(page, 0.4);
    await tap(page, '#petgo-request button[type="submit"]');
    await page.waitForLoadState('networkidle');
    // после отправки страница перезагружается к форме — показываем сообщение «Спасибо! Заявка отправлена»
    await page.evaluate(() => {
      const f = document.getElementById('petgo-request');
      scrollTo(0, f.getBoundingClientRect().top + scrollY - 70);
    });
    await pause(page, 0.3);
    mark('submitted');
    await pause(page, 3.5);
  },

  // Вход в кабинет
  async login(page, mark, ctx) {
    await page.goto(cab(), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 1);
    await type(page, 'input[name="log"]', env('PETGO_LOGIN', 'owner'), 90);
    await type(page, 'input[name="pwd"]', env('PETGO_PASSWORD', 'Demo-Owner-2026!'), 45);
    await pause(page, 0.3);
    await Promise.all([page.waitForLoadState('networkidle'), tap(page, 'button[type="submit"]:has-text("Войти")')]);
    mark('in');
    await pause(page, 3.5);
    await ctx.storageState({ path: path.join(STATE, 'owner.json') });
  },

  // Питомцы: список и анкета питомца
  async pets(page, mark) {
    await page.goto(cab('pets'), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 2.4);
    await tap(page, page.getByText('Рекс', { exact: true }));
    await page.waitForLoadState('networkidle');
    await pause(page, 0.5);
    await scrollToEl(page, page.getByText('Рекс', { exact: true }), 1.4, 150);
    mark('profile');
    await pause(page, 1.6);
    await smoothScroll(page, 420, 2.5);
    await pause(page, 1.5);
  },

  // Чат: открыть переписку и отправить сообщение
  async chat(page, mark) {
    await page.goto(cab('home'), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 0.8);
    await tap(page, 'button[data-chat][aria-label="Чаты"]');
    await pause(page, 1.2);
    await tap(page, page.locator('.pgc-cr', { hasText: 'Дана Ким' }));
    await pause(page, 0.8);
    mark('open');
    await pause(page, 1);
    const box = page.locator('textarea:visible, [contenteditable="true"]:visible').last();
    await box.click();
    await box.pressSequentially('Спасибо! Завтра в 10:00 снова Рекс', { delay: 60 });
    await pause(page, 0.4);
    await box.press('Enter');
    const send = page.locator('button[aria-label="Отправить"]:visible, button:visible:has-text("Отправить")');
    if ((await box.inputValue().catch(() => '')) && (await send.count())) await tap(page, send);
    mark('sent');
    await pause(page, 2.5);
  },

  // Сотрудники: добавить нового и выбрать роль
  async staff(page, mark) {
    await page.goto(cab('staff'), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 1.5);
    await tap(page, 'button.pgc-fab.mob');
    await pause(page, 0.7);
    await tap(page, 'button.pgc-mi[data-open="pgc-m-staff"]');
    await pause(page, 0.7);
    mark('form');
    const f = '#pgc-m-staff ';
    await type(page, f + 'input[name="name"]', 'Марина Ли');
    await type(page, f + 'input[name="phone"]', '+7 700 500 60 70', 45);
    await type(page, f + 'input[name="login"]', 'marina', 70);
    await type(page, f + 'input[name="pass"]', 'Demo-Staff-2026!', 35);
    const role = page.locator(f + 'select[name="role"]');
    await role.scrollIntoViewIfNeeded();
    await role.focus();
    await pause(page, 0.4);
    await role.selectOption('manager');
    await pause(page, 0.9);
    await Promise.all([page.waitForLoadState('networkidle'), tap(page, f + 'button[type="submit"]')]);
    await pause(page, 0.4);
    mark('saved');
    await pause(page, 3);
  },

  // Заявки: список новых заявок с сайта
  async requests(page, mark) {
    await page.goto(cab('requests'), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 2.2);
    await smoothScroll(page, 850, 3.2);
    await pause(page, 1);
    await smoothScroll(page, 850, 3.2);
    await pause(page, 1.5);
  },
};

const NEEDS_LOGIN = new Set(['pets', 'chat', 'staff', 'requests']);

// Пишем кадры через CDP-screencast в 2x разрешении (видео Playwright — 1x и мыльное),
// затем склеиваем их ffmpeg с реальными паузами между кадрами.
async function record(browser, name) {
  const stateFile = NEEDS_LOGIN.has(name) && path.join(STATE, 'owner.json');
  if (stateFile && !fs.existsSync(stateFile)) throw new Error('Сначала запишите вход: node video/scripts/record.mjs login');
  const tmp = path.join(CLIPS, '.tmp-' + name);
  fs.rmSync(tmp, { recursive: true, force: true });
  fs.mkdirSync(tmp, { recursive: true });
  const ctx = await browser.newContext({
    ...IPHONE, locale: 'ru-RU', timezoneId: 'Asia/Almaty',
    ...(stateFile ? { storageState: stateFile } : {}),
  });
  await ctx.addInitScript(TAP_SCRIPT);
  const page = await ctx.newPage();
  const cdp = await ctx.newCDPSession(page);
  const frames = [];
  cdp.on('Page.screencastFrame', ({ data, sessionId }) => {
    const file = path.join(tmp, `f${String(frames.length).padStart(6, '0')}.jpg`);
    fs.writeFileSync(file, Buffer.from(data, 'base64'));
    frames.push({ file, t: Date.now() / 1000 });
    cdp.send('Page.screencastFrameAck', { sessionId }).catch(() => {});
  });
  await cdp.send('Page.startScreencast', { format: 'jpeg', quality: 92, maxWidth: VIDEO_SIZE.width, maxHeight: VIDEO_SIZE.height });
  const marks = {};
  const mark = (label = 'ready') => { marks[label] = Date.now() / 1000; };
  try {
    await flows[name](page, mark, ctx);
  } finally {
    const end = Date.now() / 1000;
    await cdp.send('Page.stopScreencast').catch(() => {});
    await ctx.close();
    if (frames.length) {
      // concat-демультиплексор: каждый кадр держится до прихода следующего
      const list = frames.map((f, i) => `file '${f.file}'\nduration ${((frames[i + 1]?.t ?? end) - f.t).toFixed(4)}`);
      fs.writeFileSync(path.join(tmp, 'list.txt'), list.join('\n') + `\nfile '${frames.at(-1).file}'\n`);
      execFileSync('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', path.join(tmp, 'list.txt'),
        '-vf', `fps=30,scale=${VIDEO_SIZE.width}:${VIDEO_SIZE.height}:force_original_aspect_ratio=decrease,pad=${VIDEO_SIZE.width}:${VIDEO_SIZE.height}:-1:-1:white`,
        '-c:v', 'libx264', '-crf', '14', '-preset', 'fast', '-pix_fmt', 'yuv420p', path.join(CLIPS, `${name}.mp4`)]);
    }
    fs.rmSync(tmp, { recursive: true, force: true });
    const t0 = frames[0]?.t ?? end;
    const rel = Object.fromEntries(Object.entries(marks).map(([k, v]) => [k, +Math.max(0, v - t0).toFixed(3)]));
    fs.writeFileSync(path.join(CLIPS, `${name}.json`), JSON.stringify({ marks: rel, length: +(end - t0).toFixed(3), recordedAt: new Date().toISOString() }, null, 2));
    console.log(`✓ ${name}.mp4 (${(end - t0).toFixed(1)} c, метки: ${Object.entries(rel).map(([k, v]) => `${k}=${v}`).join(', ')})`);
  }
}

const wanted = process.argv.slice(2).length ? process.argv.slice(2) : Object.keys(flows);
fs.mkdirSync(CLIPS, { recursive: true });
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
