"""Дорожка звуков действий для ролика Vet.izi → video/vetizi/assets/sfx.wav.

Звуки — CC0 из пакета uisfx (лежат в vetizi/assets/sfx/). Клики и набор текста берутся из событий,
записанных в клипах (assets/clips/*.json: events), и переносятся во время ролика с учётом
окна клипа в сцене и скорости воспроизведения. Остальное (переходы, уведомления, тасовка) — по таймингу сцен.
Если поменяли тайминг в compositions/*.html — поправьте SCENES / TYPING / FIXED ниже.

Использование: python3 sfx.py <путь к video/vetizi>
"""
import json
import sys
import wave
from pathlib import Path

import numpy as np

SR = 48000
DUR = 50.0
ROOT = Path(sys.argv[1] if len(sys.argv) > 1 else '.')
rng = np.random.default_rng(5)

# Десктопные клипы в сценах: (клип, начало сцены в ролике, начало окна в сцене, длительность окна, media-start, скорость)
SCENES = [
    ('d-staff', 30.0, 6.58, 1.25, 12.9, 1.3),
    ('d-chat', 30.0, 7.78, 1.22, 7.9, 1.3),
]
# Набор текста (секунды ролика): печать кода, команды в терминале, форма заявки на телефоне
TYPING = [
    (16.9, 18.5, -17, 0.07),
    (18.62, 18.95, -16, 0.06), (19.28, 19.6, -16, 0.06),
    (23.95, 24.35, -14, 0.08), (24.55, 25.05, -14, 0.07), (25.55, 25.75, -14, 0.08), (25.85, 26.15, -14, 0.08),
]
# Фиксированные звуки: (секунда ролика, звук, громкость дБ)
FIXED = [
    (1.15, 'whoosh-b', -12), (1.3, 'pop', -10),                 # логотип WebFor
    (3.55, 'whoosh-a', -15), (4.15, 'pop', -9),                  # Vet.izi
    (6.95, 'whoosh-a', -14), (7.6, 'whoosh-big', -11),           # телефон выезжает
    (10.0, 'tick', -12), (11.2, 'success', -9),                  # установка приложения
    (11.5, 'click-a', -6), (11.8, 'whoosh-b', -12),              # касание и раскрытие
    (14.0, 'whoosh-b', -17), (15.2, 'whoosh-b', -17), (16.05, 'whoosh-a', -14),
    (16.6, 'whoosh-b', -15), (18.6, 'tick', -14), (18.85, 'tick', -16), (19.25, 'tick', -14), (19.65, 'tick', -16),
    (19.75, 'pop', -10),                                         # сайт открылся
    (22.7, 'whoosh-a', -14), (23.05, 'whoosh-big', -11),         # телефон с формой
    (24.45, 'click-b', -9), (25.15, 'click-a', -8), (25.4, 'click-a', -8), (26.25, 'click-b', -9), (26.45, 'click-a', -6),
    (26.6, 'whoosh-big', -9),                                    # оборот телефона
    (27.8, 'success', -8),                                       # заявка отправлена
    (29.45, 'whoosh-a', -13), (30.05, 'whoosh-b', -12),          # в CRM
    (31.0, 'tick', -16), (31.2, 'whoosh-b', -18),                # меню по наведению
    (34.6, 'whoosh-b', -17), (35.6, 'whoosh-b', -17), (36.6, 'whoosh-b', -17), (37.8, 'whoosh-b', -17),
    (38.7, 'whoosh-a', -13),
    (39.27, 'whoosh-a', -15), (39.4, 'whoosh-a', -17), (39.95, 'whoosh-a', -13), (40.1, 'whoosh-a', -15), (40.72, 'whoosh-a', -14),
    (41.65, 'pop', -14), (41.77, 'pop', -15), (41.89, 'pop', -15), (42.01, 'pop', -16),
    (43.65, 'whoosh-a', -14),
    (44.15, 'success', -8), (45.5, 'pop', -12),                  # финал
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

for t0, t1, db, gap in TYPING:
    typing(t0, t1, db=db, gap=gap)
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
