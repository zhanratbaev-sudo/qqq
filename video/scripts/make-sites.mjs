// Концепт-сайты разных видов бизнеса для сцены «помогаем многим бизнесам» → video/vetizi/assets/shots/site-*.png
// Это условные макеты без названий брендов (не реальные клиенты), 1440 x 900.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const { chromium } = createRequire(import.meta.url)('playwright');
const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'vetizi', 'assets', 'shots');

const sites = [
  { id: 'vet', bg: '#f4f8ff', ink: '#0d2a5c', acc: '#2f6bff', soft: '#dfe9ff', logo: 'Ветклиника', h: 'Здоровье питомца<br>в надёжных руках', p: 'Приём, анализы, вакцинация и стационар', btn: 'Записаться на приём', cards: ['Терапия', 'Хирургия', 'Вакцинация'], shape: 'circle' },
  { id: 'groom', bg: '#fff4ef', ink: '#4a1f1a', acc: '#ff7a59', soft: '#ffe1d6', logo: 'Груминг', h: 'Стрижка и уход<br>для собак и кошек', p: 'Мастера с опытом, бережная косметика', btn: 'Выбрать время', cards: ['Стрижка', 'Мытьё', 'Когти'], shape: 'blob' },
  { id: 'dent', bg: '#effaf8', ink: '#0b3b37', acc: '#14b8a6', soft: '#d3f2ed', logo: 'Стоматология', h: 'Лечение зубов<br>без страха', p: 'Консультация, гигиена, имплантация', btn: 'Онлайн-запись', cards: ['Гигиена', 'Лечение', 'Имплантация'], shape: 'pill' },
  { id: 'beauty', bg: '#161214', ink: '#f6ece4', acc: '#d6a86b', soft: '#2a2226', logo: 'Салон красоты', h: 'Красота, которая<br>вам идёт', p: 'Волосы, ногти, брови — в одном месте', btn: 'Записаться', cards: ['Волосы', 'Маникюр', 'Брови'], shape: 'arch' },
  { id: 'auto', bg: '#121518', ink: '#f2f4f6', acc: '#ff8a1f', soft: '#1f2429', logo: 'Автосервис', h: 'Ремонт и обслуживание<br>за один день', p: 'Диагностика, ТО, шиномонтаж', btn: 'Оставить заявку', cards: ['Диагностика', 'ТО', 'Шины'], shape: 'square' },
  { id: 'cafe', bg: '#f7efe4', ink: '#3b2516', acc: '#9c5b2e', soft: '#ead9c4', logo: 'Кофейня', h: 'Кофе и завтраки<br>каждый день', p: 'Предзаказ на сайте — заберите без очереди', btn: 'Сделать заказ', cards: ['Кофе', 'Завтраки', 'Десерты'], shape: 'circle' },
];

const art = (s) => {
  const radius = { circle: '50%', blob: '46% 54% 40% 60% / 55% 45% 55% 45%', pill: '200px', arch: '260px 260px 24px 24px', square: '36px' }[s.shape];
  return `<div style="position:absolute;right:120px;top:150px;width:470px;height:470px;border-radius:${radius};background:${s.soft}"></div>
    <div style="position:absolute;right:250px;top:270px;width:210px;height:210px;border-radius:${radius};background:${s.acc};opacity:.9"></div>
    <div style="position:absolute;right:150px;top:470px;width:140px;height:140px;border-radius:50%;background:${s.ink};opacity:.12"></div>`;
};

const html = (s) => `<!doctype html><meta charset="utf-8"><style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{width:1440px;height:900px;overflow:hidden;background:${s.bg};color:${s.ink};font-family:'Inter Display','Inter',sans-serif;position:relative}
  nav{position:absolute;left:120px;right:120px;top:36px;height:64px;display:flex;align-items:center;gap:40px;font-weight:600;font-size:19px}
  nav b{font-size:26px;font-weight:900;margin-right:auto;letter-spacing:-.02em}
  nav .cta{height:48px;padding:0 24px;border-radius:24px;background:${s.acc};color:#fff;display:flex;align-items:center}
  h1{position:absolute;left:120px;top:210px;font-size:72px;line-height:1.05;font-weight:900;letter-spacing:-.035em}
  p{position:absolute;left:124px;top:400px;font-size:24px;opacity:.75}
  .btn{position:absolute;left:120px;top:470px;height:64px;padding:0 34px;border-radius:32px;background:${s.acc};color:#fff;font-weight:800;font-size:22px;display:flex;align-items:center}
  .cards{position:absolute;left:120px;right:120px;top:660px;display:flex;gap:28px}
  .cards div{flex:1;height:170px;border-radius:24px;background:${s.soft};padding:28px;font-weight:800;font-size:26px}
</style>
<nav><b>${s.logo}</b><span>Услуги</span><span>Цены</span><span>Контакты</span><span class="cta">${s.btn}</span></nav>
${art(s)}
<h1>${s.h}</h1><p>${s.p}</p><div class="btn">${s.btn}</div>
<div class="cards">${s.cards.map((c) => `<div>${c}</div>`).join('')}</div>`;

fs.mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
for (const s of sites) {
  await page.setContent(html(s), { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: path.join(OUT, `site-${s.id}.png`) });
  console.log('✓ site-' + s.id);
}
await browser.close();
