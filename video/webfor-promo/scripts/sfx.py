"""Звуки ролика WebFor → assets/sfx-mix.wav. Только на видимые действия (CC0-звуки из assets/sfx).
Музыку владелец добавляет сам (в Instagram при публикации). Использование: python3 scripts/sfx.py"""
import wave
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parent.parent
SR, DUR = 48000, 51.0

# (секунда, файл, громкость) — у каждого звука есть причина в кадре
EVENTS = [
    (0.62, 'notify', .45), (1.42, 'notify', .40), (2.22, 'notify', .40), (3.02, 'notify', .40),  # сообщения ночью
    (5.27, 'whoosh-a', .55),                                                                      # сообщения → окна
    (6.17, 'send', .45), (7.82, 'pop', .55), (10.32, 'pop', .4),                                  # вопросы, ответы, менеджер
    (10.62, 'send', .4), (11.92, 'pop', .5), (14.92, 'whoosh-b', .5),                             # вторые вопросы, окна уходят
    (16.95, 'whoosh-a', .45), (18.42, 'whoosh-b', .45), (21.07, 'whoosh-a', .5),                  # сайт собирается, чат в угол, в телефон
    (22.02, 'whoosh-b', .45), (24.32, 'notify', .55), (25.57, 'tick', .5), (27.32, 'pop', .5),    # телефон, push, касание, заявка
    (29.17, 'whoosh-a', .4), (30.62, 'tick', .35), (31.72, 'tick', .35), (33.02, 'pop', .45),     # обучение, знания, проверка
    (33.52, 'success', .45), (36.27, 'whoosh-b', .45),                                            # 7 дней, телефон уходит
    (37.02, 'whoosh-a', .4), (38.22, 'whoosh-big', .45), (40.02, 'whoosh-b', .4), (41.42, 'whoosh-a', .4),  # продукты
    (44.12, 'whoosh-big', .5), (46.92, 'whoosh-a', .5), (47.47, 'success', .6),                   # отъезд, сборка в знак
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
