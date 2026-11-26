# Листовка PetGo A4

- `petgo-listovka-a4.pdf` — для печати (A4, вектор, шрифты внутри).
- `petgo-listovka-a4.png` — та же листовка, 300 dpi (для типографии, WhatsApp, Instagram).

QR-коды: сайт `https://petgo.kz/?utm_source=flyer&utm_medium=print&utm_campaign=a4` (заявки с листовки видно в статистике и в заявке),
WhatsApp `https://wa.me/77478175712`, Instagram `https://instagram.com/petgo.kz`.

## Обновить цены или тексты
Тексты и цены — в `src/template.html`. Пересобрать (нужны Python 3 с `qrcode`, Node.js с Playwright и шрифт Inter):
```
cd src
python3 qr.py .                      # QR-коды
python3 build.py . 26.11.2026        # дата цен внизу листовки
node render.js "$PWD" "$PWD/../petgo-listovka-a4" 3.125 pdf
node qrcheck.js ../petgo-listovka-a4.png   # проверка, что QR читаются
```
