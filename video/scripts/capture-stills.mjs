// Полностраничные скриншоты PetGo для экранов 3D-устройств в ролике Vet.izi → video/vetizi/assets/shots/
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'vetizi', 'assets', 'shots');
const BASE = (process.env.PETGO_BASE_URL || 'http://127.0.0.1:8080').replace(/\/$/, '');
const STATE = path.join(ROOT, '.auth', 'desktop.json');
const MOBILE = { ...devices['iPhone 14'], viewport: { width: 390, height: 844 } };
delete MOBILE.defaultBrowserType;

fs.mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch();
const shot = async (opts, url, file, full = true) => {
  const ctx = await browser.newContext({ locale: 'ru-RU', timezoneId: 'Asia/Almaty', reducedMotion: 'reduce', ...opts });
  const page = await ctx.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(OUT, file), fullPage: full });
  await ctx.close();
  console.log('✓', file);
};
const desk = { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1.5 };
await shot(desk, BASE + '/', 'landing-desktop.png');
await shot({ ...desk, storageState: STATE }, BASE + '/кабинет/?pg=home', 'crm-home.png', false);
await shot({ ...desk, storageState: STATE }, BASE + '/кабинет/?pg=requests', 'crm-requests.png', false);
await shot({ ...MOBILE, deviceScaleFactor: 3, storageState: STATE }, BASE + '/кабинет/?pg=home', 'app-home.png', false);
await shot({ ...MOBILE, deviceScaleFactor: 3, storageState: STATE }, BASE + '/кабинет/?pg=orders', 'app-orders.png', false);
await browser.close();
