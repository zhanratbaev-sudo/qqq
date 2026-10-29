// Записывает экранные клипы сайта PetGo в мобильной эмуляции iPhone 14 (390x844).
// Каждый сценарий (flow) пишется в отдельный клип video/clips/<name>.mp4,
// рядом кладётся <name>.json с моментом, когда страница готова (обрезаем белый старт).
//
// Запуск:  node video/scripts/record.mjs            — все клипы
//          node video/scripts/record.mjs chat home  — только указанные
//
// Логины и адреса берутся из video/.env (см. video/.env.example). Не используйте реальные данные клиентов.
import { createRequire } from 'node:module';
const { chromium, devices } = createRequire(import.meta.url)('playwright');
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CLIPS = process.env.CLIPS_DIR || path.join(ROOT, 'clips');
const STATE = path.join(ROOT, '.auth');
if (fs.existsSync(path.join(ROOT, '.env'))) process.loadEnvFile(path.join(ROOT, '.env'));

const env = (k, def) => {
  const v = process.env[k] ?? def;
  if (v === undefined || v === '') throw new Error(`Не задана переменная ${k} в video/.env`);
  return v;
};
const BASE = env('PETGO_BASE_URL', 'https://pet.oscarclinic.kz').replace(/\/$/, '');
const url = (p) => (/^https?:/.test(p) ? p : BASE + (p.startsWith('/') ? p : '/' + p));
// SUBMIT=0 — заполнить формы, но не отправлять (чтобы не создавать записи на боевом сайте).
const SUBMIT = process.env.PETGO_SUBMIT !== '0';

const IPHONE = { ...devices['iPhone 14'], viewport: { width: 390, height: 844 } }; // во весь экран, как просили
delete IPHONE.defaultBrowserType; // в контейнере только Chromium
const VIDEO_SIZE = { width: 780, height: 1688 }; // 2x от 390x844 для чёткости

// Видимый «тап» и плавная прокрутка для красоты на видео.
const TAP_SCRIPT = `
  addEventListener('pointerdown', (e) => {
    const d = document.createElement('div');
    d.style.cssText = 'position:fixed;z-index:2147483647;pointer-events:none;width:44px;height:44px;border-radius:50%;' +
      'background:rgba(14,159,142,.35);border:3px solid rgba(14,159,142,.9);left:' + (e.clientX - 22) + 'px;top:' + (e.clientY - 22) + 'px;' +
      'transition:transform .45s ease-out,opacity .45s ease-out;transform:scale(.6)';
    document.documentElement.appendChild(d);
    requestAnimationFrame(() => { d.style.transform = 'scale(1.5)'; d.style.opacity = '0'; });
    setTimeout(() => d.remove(), 600);
  }, true);
`;

const pause = (page, s) => page.waitForTimeout(s * 1000);

async function smoothScroll(page, distance, seconds = 2.5) {
  await page.evaluate(async ([dist, ms]) => {
    const start = scrollY, t0 = performance.now();
    await new Promise((res) => {
      const step = (now) => {
        const k = Math.min(1, (now - t0) / ms);
        const e = k < 0.5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2; // easeInOutQuad
        scrollTo(0, start + dist * e);
        k < 1 ? requestAnimationFrame(step) : res();
      };
      requestAnimationFrame(step);
    });
  }, [distance, seconds * 1000]);
}

// Находит видимый элемент по тексту / роли из списка вариантов.
async function findByText(page, patterns) {
  for (const re of [].concat(patterns)) {
    for (const loc of [
      page.getByRole('button', { name: re }),
      page.getByRole('link', { name: re }),
      page.getByText(re),
    ]) {
      const n = await loc.count();
      for (let i = 0; i < n; i++) if (await loc.nth(i).isVisible()) return loc.nth(i);
    }
  }
  return null;
}

async function tap(page, patterns, { optional = false } = {}) {
  const el = await findByText(page, patterns);
  if (!el) {
    if (optional) return false;
    throw new Error(`Не найдена кнопка/ссылка: ${[].concat(patterns).join(' | ')} на ${page.url()}`);
  }
  await el.scrollIntoViewIfNeeded();
  await pause(page, 0.4);
  await el.click();
  return true;
}

async function typeInto(page, selectors, text) {
  for (const sel of [].concat(selectors)) {
    const loc = page.locator(sel).first();
    if ((await loc.count()) && (await loc.isVisible())) {
      await loc.scrollIntoViewIfNeeded();
      await loc.click();
      await loc.pressSequentially(text, { delay: 70 });
      return true;
    }
  }
  return false;
}

// Заполняет пустые видимые поля формы тестовыми значениями (по подсказкам label/placeholder/name).
async function fillVisibleForm(page, values) {
  const fields = page.locator('form input:visible, form textarea:visible, form select:visible');
  const n = await fields.count();
  for (let i = 0; i < n; i++) {
    const f = fields.nth(i);
    const info = await f.evaluate((el) => {
      const lbl = (el.id && document.querySelector(`label[for="${el.id}"]`)?.textContent) || el.closest('label')?.textContent || '';
      return { tag: el.tagName, type: el.type, value: el.value, hint: `${el.name} ${el.placeholder} ${lbl}`.toLowerCase() };
    });
    if (['hidden', 'submit', 'button', 'checkbox', 'radio', 'file', 'password'].includes(info.type)) continue;
    if (info.tag === 'SELECT') {
      const opts = await f.locator('option').evaluateAll((o) => o.map((x) => x.value).filter(Boolean));
      if (opts.length) { await f.scrollIntoViewIfNeeded(); await f.selectOption(opts[0]); await pause(page, 0.5); }
      continue;
    }
    if (info.value) continue;
    const match = values.find(([re]) => re.test(info.hint));
    let text = match ? match[1] : values.at(-1)[1];
    if (info.type === 'date') { await f.fill(new Date(Date.now() + 864e5).toISOString().slice(0, 10)); continue; }
    if (info.type === 'time') { await f.fill('11:00'); continue; }
    if (info.type === 'tel') text = '+7 700 000 00 00';
    if (info.type === 'email') text = 'demo@example.com';
    await f.scrollIntoViewIfNeeded();
    await f.click();
    await f.pressSequentially(text, { delay: 45 });
    await pause(page, 0.3);
  }
}

const openPage = (page, p) => page.goto(url(p), { waitUntil: 'networkidle' });

async function doLogin(page, user, pass) {
  const userSel = ['#user_login', 'input[name="log"]', 'input[name="username"]', 'input[type="email"]', 'input[name*="login" i]', 'input[type="text"]'];
  const passSel = ['#user_pass', 'input[name="pwd"]', 'input[name="password"]', 'input[type="password"]'];
  if (!(await typeInto(page, userSel, user))) throw new Error('Не найдено поле логина на ' + page.url());
  await pause(page, 0.3);
  await typeInto(page, passSel, pass);
  await pause(page, 0.5);
  const submit = page.locator('#wp-submit, form button[type="submit"], form input[type="submit"]').first();
  await Promise.all([page.waitForLoadState('networkidle'), submit.click()]);
}

// ---------- Сценарии ----------
const flows = {
  async home(page, mark) {
    await page.goto(url(env('PETGO_HOME_PATH', '/')), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 3);
    await smoothScroll(page, 900, 3);
    await pause(page, 1);
    await smoothScroll(page, 900, 3);
    await pause(page, 2);
  },

  async 'owner-login'(page, mark, ctx) {
    await openPage(page, env('PETGO_OWNER_LOGIN_URL'));
    mark();
    await pause(page, 1);
    await doLogin(page, env('PETGO_OWNER_LOGIN'), env('PETGO_OWNER_PASSWORD'));
    await pause(page, 2.5);
    await smoothScroll(page, 500, 2.5);
    await pause(page, 2);
    await ctx.storageState({ path: path.join(STATE, 'owner.json') });
  },

  async 'owner-request'(page, mark) {
    await page.goto(url(env('PETGO_OWNER_REQUEST_URL', env('PETGO_OWNER_CABINET_URL', '/'))), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 1.5);
    await tap(page, [/новая заявка/i, /создать заявку/i, /записаться/i, /оставить заявку/i], { optional: true });
    await page.waitForLoadState('networkidle');
    await pause(page, 1);
    await fillVisibleForm(page, [
      [/клич|питом|pet|имя жив/, 'Барсик'],
      [/вид|порода|species/, 'Кот, британец'],
      [/имя|name|фио/, 'Тест Демо'],
      [/жалоб|причин|описан|коммент|message|симпт/, 'Плановая вакцинация, демо-заявка'],
      [/./, 'Демо'],
    ]);
    await pause(page, 1);
    if (SUBMIT) {
      await tap(page, [/отправить/i, /создать/i, /записаться/i, /сохранить/i]);
      await page.waitForLoadState('networkidle');
    }
    await pause(page, 3);
  },

  async 'owner-chat'(page, mark) {
    await page.goto(url(env('PETGO_OWNER_CHAT_URL', env('PETGO_OWNER_CABINET_URL', '/'))), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 1);
    await tap(page, [/^чат/i, /сообщения/i, /написать/i], { optional: true });
    await page.waitForLoadState('networkidle');
    await pause(page, 1);
    await typeInto(page, ['textarea', 'input[type="text"][name*="message" i]', 'input[placeholder*="сообщ" i]'], 'Здравствуйте! Можно перенести приём на 11:00?');
    await pause(page, 0.6);
    if (SUBMIT) await tap(page, [/отправить/i, /send/i], { optional: true }) || await page.keyboard.press('Enter');
    await pause(page, 3);
  },

  async 'staff-login'(page, mark, ctx) {
    await openPage(page, env('PETGO_STAFF_LOGIN_URL'));
    mark();
    await pause(page, 1);
    await doLogin(page, env('PETGO_STAFF_LOGIN'), env('PETGO_STAFF_PASSWORD'));
    await pause(page, 3);
    await ctx.storageState({ path: path.join(STATE, 'staff.json') });
  },

  async 'staff-employee'(page, mark) {
    await page.goto(url(env('PETGO_STAFF_EMPLOYEES_URL', env('PETGO_STAFF_CABINET_URL', '/'))), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 1.5);
    await tap(page, [/добавить сотрудника/i, /новый сотрудник/i, /пригласить/i, /^добавить/i], { optional: true });
    await page.waitForLoadState('networkidle');
    await pause(page, 0.8);
    await fillVisibleForm(page, [
      [/mail/, 'demo.vet@example.com'],
      [/тел|phone/, '+7 700 000 00 00'],
      [/имя|name|фио/, 'Анна Демо'],
      [/должн|position/, 'Ветврач'],
      [/./, 'Демо'],
    ]);
    // роль: select или радиокнопка «Врач»
    const role = page.locator('select[name*="role" i]').first();
    if (await role.count()) await role.selectOption({ index: 1 }).catch(() => {});
    else await tap(page, [/врач/i], { optional: true });
    await pause(page, 1);
    if (SUBMIT) await tap(page, [/добавить/i, /сохранить/i, /создать/i, /пригласить/i]);
    await page.waitForLoadState('networkidle');
    await pause(page, 2.5);
  },

  async 'staff-requests'(page, mark) {
    await page.goto(url(env('PETGO_STAFF_REQUESTS_URL', env('PETGO_STAFF_CABINET_URL', '/'))), { waitUntil: 'networkidle' });
    mark();
    await pause(page, 1);
    await tap(page, [/^заявки/i, /все заявки/i], { optional: true });
    await page.waitForLoadState('networkidle');
    await pause(page, 2.5);
    await smoothScroll(page, 700, 3);
    await pause(page, 2.5);
  },
};

const NEEDS_STATE = { 'owner-request': 'owner', 'owner-chat': 'owner', 'staff-employee': 'staff', 'staff-requests': 'staff' };

// Пишем кадры через CDP-screencast в 2x разрешении (видео Playwright — 1x и мыльное),
// затем склеиваем их ffmpeg с реальными паузами между кадрами.
async function record(browser, name) {
  const stateFile = NEEDS_STATE[name] && path.join(STATE, `${NEEDS_STATE[name]}.json`);
  if (stateFile && !fs.existsSync(stateFile)) throw new Error(`Сначала запишите вход (${NEEDS_STATE[name]}-login)`);
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
  let readyAt = Date.now() / 1000;
  const mark = () => { readyAt = Date.now() / 1000; };
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
    const rel = Math.max(0, readyAt - (frames[0]?.t ?? readyAt));
    fs.writeFileSync(path.join(CLIPS, `${name}.json`), JSON.stringify({ readyAt: rel, recordedAt: new Date().toISOString() }, null, 2));
    console.log(`✓ ${name}.mp4 (${frames.length} кадров, страница готова на ${rel.toFixed(2)} c)`);
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
