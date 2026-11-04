"""Звуки ролика WebFor → assets/sfx-mix.wav. Только на видимые действия (CC0-звуки из assets/sfx).
Музыку владелец добавляет сам (в Instagram при публикации). Использование: python3 scripts/sfx.py"""
import wave
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parent.parent
SR, DUR = 48000, 52.5

# (секунда, файл, громкость) — у каждого звука есть причина в кадре
EVENTS = [
    (0.62, 'notify', .45), (1.42, 'notify', .40), (2.22, 'notify', .40), (3.02, 'notify', .40),  # сообщения ночью
    (5.27, 'whoosh-a', .55),                                                                      # сообщения → окна
    (6.17, 'send', .45), (7.82, 'pop', .55), (10.32, 'pop', .4),                                  # вопросы, ответы, менеджер
    (10.62, 'send', .4), (11.92, 'pop', .5), (14.92, 'whoosh-b', .5),                             # вторые вопросы, окна уходят
    (16.85, 'whoosh-a', .45), (18.53, 'click-a', .6), (18.95, 'whoosh-b', .4),                    # сайт собирается, клик «Спросить AI», камера к чату
    (19.62, 'send', .4), (20.57, 'pop', .5), (22.72, 'whoosh-a', .5),                             # вопрос, ответ, разговор улетает в телефон
    (23.52, 'whoosh-b', .45), (25.82, 'notify', .55), (27.07, 'tick', .5), (28.82, 'pop', .5),    # телефон, push, касание, заявка
    (30.67, 'whoosh-a', .4), (32.12, 'tick', .35), (33.22, 'tick', .35), (34.52, 'pop', .45),     # обучение, знания, проверка
    (35.02, 'success', .45), (37.77, 'whoosh-b', .45),                                            # 7 дней, телефон уходит
    (38.52, 'whoosh-a', .4), (39.72, 'whoosh-big', .45), (41.52, 'whoosh-b', .4), (42.92, 'whoosh-a', .4),  # продукты
    (45.62, 'whoosh-big', .5), (48.42, 'whoosh-a', .5), (48.97, 'success', .6),                   # отъезд, сборка в знак
]


def load(name):
    with wave.open(str(ROOT / 'assets' / 'sfx' / (name + '.wav'))) as w:
        x = np.frombuffer(w.readframes(w.getnframes()), dtype=np.int16).astype(np.float32) / 32768
        return x.reshape(-1, w.getnchannels())


mix = np.zeros((int(SR * DUR), 2), dtype=np.float32)
for t, name, vol in EVENTS:
    x = load(name) * vol
    i = int(t * SR)
    n = min(len(x), len(mix) - i)
    mix[i:i + n] += x[:n]
peak = np.max(np.abs(mix))
if peak > 0.89:  # не выше −1 dBFS
    mix *= 0.89 / peak
out = (mix * 32767).astype(np.int16)
with wave.open(str(ROOT / 'assets' / 'sfx-mix.wav'), 'wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes(out.tobytes())
print('assets/sfx-mix.wav', len(EVENTS), 'звуков, пик', round(float(peak), 2))
