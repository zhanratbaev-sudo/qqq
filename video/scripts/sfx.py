"""Дорожка звуков действий для ролика Vet.izi → video/vetizi/assets/sfx.wav.

Звуки — CC0 из пакета uisfx (лежат в vetizi/assets/sfx/). Клики и набор текста берутся из событий,
записанных в клипах (assets/clips/*.json: events), и переносятся во время ролика с учётом
окна клипа в сцене и скорости воспроизведения. Остальное (переходы, уведомления, тасовка) — по таймингу сцен.
Если поменяли тайминг в compositions/*.html — поправьте SCENES / FIXED ниже.

Использование: python3 sfx.py <путь к video/vetizi>
"""
import json
import sys
import wave
from pathlib import Path

import numpy as np

SR = 48000
DUR = 40.0
ROOT = Path(sys.argv[1] if len(sys.argv) > 1 else '.')
rng = np.random.default_rng(5)

# Клипы в сценах: (клип, начало сцены в ролике, начало окна в сцене, длительность окна, media-start, скорость)
SCENES = [
    ('d-request', 13.0, 0.0, 4.0, 4.39, 3.55),
    ('d-staff', 17.0, 2.68, 1.35, 12.9, 1.3),
    ('d-chat', 17.0, 3.98, 1.52, 8.0, 1.3),
]
# Печать кода в сцене сборки: окно в секундах ролика
CODE_TYPING = (8.95, 10.45)
# Фиксированные звуки: (секунда ролика, звук, громкость дБ)
FIXED = [
    (4.4, 'tick', -16), (5.0, 'tick', -16), (5.6, 'tick', -16), (6.2, 'tick', -16), (6.8, 'tick', -16),
    (8.1, 'whoosh-big', -10),                       # ныряем в экран ноутбука
    (10.5, 'whoosh-b', -14),                        # проявляется сайт
    (16.6, 'pop', -8),                              # карточка заявки вылетает
    (17.42, 'success', -12),                        # карточка в CRM
    (18.0, 'whoosh-b', -17), (18.9, 'whoosh-b', -17), (19.7, 'whoosh-b', -17), (21.0, 'whoosh-b', -17),
    (22.35, 'whoosh-big', -12),                     # к телефону
    (25.5, 'notify', -6), (26.2, 'notify', -8),     # уведомления на телефоне
    (27.85, 'whoosh-big', -12),                     # к сайтам
    (28.27, 'whoosh-a', -15), (28.4, 'whoosh-a', -17), (28.95, 'whoosh-a', -13), (29.1, 'whoosh-a', -15),
    (29.72, 'whoosh-a', -14),                       # раскладка в сетку
    (30.65, 'pop', -14), (30.77, 'pop', -15), (30.89, 'pop', -15), (31.01, 'pop', -16),
    (32.7, 'whoosh-a', -15),
]


def load(name):
    with wave.open(str(ROOT / 'assets' / 'sfx' / f'{name}.wav')) as w:
        a = np.frombuffer(w.readframes(w.getnframes()), '<i2').reshape(-1, 2) / 32768.0
    return a


S = {n: load(n) for n in ['click-a', 'click-b', 'key-a', 'key-b', 'key-c', 'notify', 'send', 'whoosh-big', 'whoosh-a', 'whoosh-b', 'pop', 'tick', 'success']}
out = np.zeros((int(DUR * SR), 2))


def put(at, name, db):
    x = S[name] * 10 ** (db / 20)
    s = int(at * SR)
    if s < 0 or s >= len(out):
        return
    e = min(len(out), s + len(x))
    out[s:e] += x[: e - s]


def typing(t0, t1, db=-15, gap=0.075):
    t = t0
    while t < t1:
        put(t + rng.uniform(-0.01, 0.01), ['key-a', 'key-b', 'key-c'][rng.integers(3)], db + rng.uniform(-3, 1))
        t += gap * rng.uniform(0.8, 1.25)


for clip, scene_at, win_at, win_len, media, rate in SCENES:
    events = json.load(open(ROOT / 'assets' / 'clips' / f'{clip}.json')).get('events', [])
    for ev in events:
        local = win_at + (ev['t'] - media) / rate
        if not (win_at <= local <= win_at + win_len):
            continue
        at = scene_at + local
        if ev['type'] == 'click':
            put(at, 'click-a' if rng.random() < 0.6 else 'click-b', -9)
        elif ev['type'] == 'send':
            put(at, 'send', -10)
        elif ev['type'] == 'type':
            end = min(scene_at + win_at + win_len, at + ev['dur'] / rate)
            typing(at, max(end, at + 0.12))

typing(*CODE_TYPING, db=-17, gap=0.07)
for at, name, db in FIXED:
    put(at, name, db)

peak = np.abs(out).max()
if peak > 0.9:
    out *= 0.9 / peak
with wave.open(str(ROOT / 'assets' / 'sfx.wav'), 'wb') as w:
    w.setnchannels(2)
    w.setsampwidth(2)
    w.setframerate(SR)
    w.writeframes((out * 32767).astype('<i2').tobytes())
print('sfx.wav готов, пик', round(20 * np.log10(np.abs(out).max() + 1e-9), 1), 'dBFS')
