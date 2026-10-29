# Промо-видео PetGo для Reels / TikTok / WhatsApp

Вертикальное видео 1080×1920 (9:16), MP4 (H.264 + AAC), ~49 сек → `video/petgo-promo-mobile.mp4`.

Как устроено:

1. `scripts/record.mjs` — Playwright открывает сайт в эмуляции iPhone 14 (390×844), проходит сценарии
   и пишет клипы `clips/<сценарий>.mp4` в 2× разрешении.
2. `scripts/overlays.mjs` — рисует фон, рамку телефона и надписи (HTML → PNG, шрифт Inter Display Black).
3. `scripts/music.py` — генерирует фоновую музыку кодом (авторских прав нет).
4. `scripts/build.mjs` — ffmpeg собирает сцены, анимирует надписи, делает переходы, сводит звук.

Надписи стоят над экраном телефона в зоне 175–447 px; интерфейс сайта не перекрывают.
Сверху и снизу остаётся не меньше 150 px свободного места под кнопки соцсетей.

## Требования

- Node.js 20.12+, Python 3 с `numpy`, `ffmpeg`
- `cd video && npm install && npx playwright install chromium`

## Поменять тексты и пересобрать (без новой записи)

1. Отредактируйте `video/scenes.json`:
   - `title` — надпись сцены. Слова в `*звёздочках*` выделяются жёлтым.
   - `duration` — длительность сцены в секундах (рекомендуем 3–5).
   - `start` — с какой секунды клипа брать кадры.
   - финальная сцена: `title`, `contacts` (список строк), `cta` (кнопка).
   - `transition` — переход в сцену: `fade`, `smoothleft`, `smoothup`, `slideleft`, `circleopen` и любые другие
     [переходы xfade](https://trac.ffmpeg.org/wiki/Xfade).
   - `brand` — цвета градиента и акцента.
2. Пересоберите видео:

   ```bash
   cd video
   npm run build          # → video/petgo-promo-mobile.mp4
   ```

   Сборка занимает ~2 минуты. Шрифт сам уменьшается, если надпись длинная (максимум 3 строки).
   Если общая длительность выйдет за 45–60 сек, скрипт выведет предупреждение.

Чтобы посмотреть тексты до записи клипов, запустите `npm run preview`: вместо экрана сайта будут серые заглушки.

## Перезаписать экран сайта

```bash
cd video
cp .env.example .env     # заполнить URL и тестовые логины
npm run record           # все сценарии
node scripts/record.mjs owner-chat staff-requests   # или только нужные
npm run build
```

Сценарии: `home`, `owner-login`, `owner-request`, `owner-chat`, `staff-login`, `staff-employee`, `staff-requests`.
Сценарии кабинета используют сессию, сохранённую при входе, поэтому сначала запишите `owner-login` / `staff-login`.

Если на сайте изменились кнопки или поля, поправьте шаги в `flows` внутри `scripts/record.mjs`.
`PETGO_SUBMIT=0` в `.env` заполняет формы, но не отправляет их, и записи на сайте не создаются.
