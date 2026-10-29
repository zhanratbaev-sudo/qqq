# Vet.izi — промо-ролик для ветклиник (YouTube 16:9)

Готовое видео: `video/vetizi-promo.mp4`. Формат 1920×1080, 30 fps, 42 с, женский голос.

Собрано на [HyperFrames](https://hyperframes.heygen.com): это HTML, GSAP и Three.js, которые рендерятся в MP4.

## Как устроен кадр

- **Слева — текст, справа — графика.** Текст стоит в колонке x 110–710 px, верх заголовка всегда на y = 380 px.
  Графика сцен (ноутбук, браузер, телефон, знак) находится только правее 760 px и никогда не заходит под текст.
- **Все надписи ролика в одном файле — `compositions/captions.html`.** Параметры записаны в начале файла:
  - заголовок: Inter Display Black, 80 px, белый, одно слово или фраза выделены мятным `#3FE0A0`;
  - описание: Inter Display Bold, 34 px, `#A9BDB6`;
  - кнопка — только в финале.
- **Каждая надпись задаётся одной группой:**
  `<div class="cap-g" data-in="секунда" data-out="секунда">` содержит заголовок `.cap-t` и описание `.cap-d p`.
  Атрибут `data-at` — когда показать описание, `data-until` — когда убрать его.
  Строки заголовка задаются явно через `<br>`, автопереноса нет.

| Время | Сцена (графика) | Заголовок |
|---|---|---|
| 0–10.6 с | `stage3d.html` — 3D MacBook и iPhone | Цифровые платформы для ветклиник → Сайт / Заявки / CRM / Чаты / Приложение → Vet.izi |
| 10.6–15.9 | `build.html` — код собирает сайт, затем сайт PetGo | Сайт под ваш дизайн |
| 15.9–20.9 | `request.html` — курсор заполняет форму | Заявки с сайта |
| 20.9–27.4 | `crm.html` — карточка садится в CRM, кадры кабинета | Заявки / Заказы / Календарь / Сотрудники и роли / Чаты с командой |
| 27.4–34.5 | `phone3d.html` — 3D iPhone, пуш-уведомления | Приложение для команды |
| 34.5–42 | `endcard.html` — 3D знак Vet.izi | Vet.izi + кнопка «Ответьте на это сообщение» |

## Озвучка

Текст озвучки лежит в `vo-script.json`. Голос женский, Piper `ru_RU-irina-medium`, звук обрабатывается
(тёплый тембр, присутствие, компрессия). Перегенерировать:

```bash
pip install piper-tts
VOICE=/путь/к/ru_RU-irina-medium.onnx ./make-vo.sh
```

Если фраза стала длиннее, поправьте `data-start` у `<audio id="voN">` в `index.html` и времена в `captions.html`.
Свою запись голоса (например, с телефона) можно положить вместо `assets/vo/v1.wav … v7.wav`.

## Пересобрать видео

```bash
cd video/vetizi
npx hyperframes check                    # проверка
npx hyperframes snapshot --at 1,10,25    # кадры для просмотра
npx hyperframes render -o ../vetizi-promo.mp4 -q delivery --video-frame-format png
```

## Перезаписать экраны продукта

Записи экрана и скриншоты снимаются с локальной копии сайта (`video/demo-site`, демо-данные):

```bash
cd video
./demo-site/setup.sh /путь/к/petgo-cabinet.zip   # поднять сайт на http://127.0.0.1:8080
php demo-site/seed.php demo-site/.wp             # сбросить демо-данные
node scripts/record-desktop.mjs                  # клипы с курсором → vetizi/assets/clips
node scripts/capture-stills.mjs                  # скриншоты для 3D-экранов → vetizi/assets/shots
```

После перезаписи метки времени в `assets/clips/*.json` могут сдвинуться.
Проверьте `data-media-start` у видео в `request.html` и `crm.html`.
