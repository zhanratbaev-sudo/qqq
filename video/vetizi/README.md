# Vet.izi — промо-ролик для ветклиник (YouTube 16:9)

Готовое видео: `video/vetizi-promo.mp4`. Формат 1920×1080, 30 fps, ~35 с, с озвучкой.

Собрано на [HyperFrames](https://hyperframes.heygen.com): это HTML, GSAP и Three.js, которые рендерятся в MP4.
План ролика по секундам лежит в `SPEC.md`.

| Сцена | Файл | Что происходит |
|---|---|---|
| 0–7.9 с | `compositions/stage3d.html` | 3D MacBook и iPhone, заголовок, слово-слот «Сайт → Заявки → CRM → Чаты → Приложение», «Vet.izi» |
| 7.9–12.5 | `compositions/build.html` | Код печатается, сайт собирается по блокам, затем проявляется сайт PetGo («Пример проекта») |
| 12.5–16.9 | `compositions/request.html` | Курсор заполняет форму заявки, карточка заявки вылетает из формы |
| 16.9–21.3 | `compositions/crm.html` | Карточка садится в CRM, затем быстрые кадры: заказы, календарь, сотрудники и роли, чаты |
| 21.3–27.9 | `compositions/phone3d.html` | 3D iPhone с PWA-приложением, падают пуш-уведомления |
| 27.9–35.3 | `compositions/endcard.html` | 3D знак Vet.izi, кнопка WhatsApp +7 747 817 57 12 |

## Поменять тексты

Тексты на экране записаны прямо в HTML сцен (ищите русский текст в `compositions/*.html`).
Номер WhatsApp находится в `compositions/endcard.html`.

Озвучка задаётся в `vo-script.json`. После правки текста перегенерируйте голос
(нужен `pip install piper-tts` и голос `ru_RU-dmitri-medium`):

```bash
cd video/vetizi
for id in v1 v2 v3 v4 v5 v6 v7; do
  python3 -c "import json;print([l['text'] for l in json.load(open('vo-script.json')) if l['id']=='$id'][0])" \
  | python3 -m piper -m /путь/к/ru_RU-dmitri-medium.onnx -f assets/vo/$id.wav --length-scale 0.88 --sentence-silence 0.15
done
```

Если фраза стала длиннее или короче, поправьте `data-start` и `data-duration` у `<audio id="voN">` в `index.html`.

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
