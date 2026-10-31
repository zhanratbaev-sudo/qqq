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
# Каждый звук привязан к действию, которое видно в кадре: нажатие, набор текста, движение объекта.
# Надписи и статичные кадры звука не дают.
# Набор текста (секунды ролика): печать кода, команды в терминале, поля формы на телефоне (по кадрам записи)
TYPING = [
    (17.25, 18.5, -17, 0.07),                                       # код сайта
    (18.55, 18.9, -15, 0.05), (19.5, 19.85, -15, 0.05),             # команды в терминале
    (24.13, 24.35, -14, 0.07), (24.47, 24.95, -14, 0.06),            # имя, телефон
    (25.36, 25.44, -14, 0.07), (25.56, 25.82, -14, 0.07),            # вес, порода
]
# Фиксированные звуки: (секунда ролика, звук, громкость дБ)
FIXED = [
    (0.2, 'whoosh-b', -17), (1.12, 'pop', -11),                  # пятна стягиваются → плитка WebFor
    (3.6, 'whoosh-a', -16), (4.03, 'pop', -15),                  # знак переворачивается, лапка отпечатывается
    (6.95, 'whoosh-a', -17), (7.55, 'whoosh-big', -13),          # иконка уходит вниз, телефон выезжает
    (11.2, 'success', -13),                                      # приложение установилось
    (11.5, 'click-a', -6), (11.8, 'whoosh-b', -14),              # касание иконки, раскрытие на весь экран
    (16.08, 'whoosh-a', -14),                                    # переход: экран телефона → редактор
    (18.97, 'tick', -12), (19.92, 'tick', -12),                  # Enter в терминале
    (20.1, 'whoosh-b', -15),                                     # открывается окно браузера
    (22.58, 'whoosh-a', -14),                                    # переход: браузер → телефон
    (24.07, 'click-b', -9), (24.4, 'click-b', -9), (25.07, 'click-a', -8), (25.2, 'click-a', -8),
    (25.33, 'click-b', -10), (25.53, 'click-b', -9), (25.93, 'click-a', -8), (26.27, 'click-a', -6),  # касания в форме
    (26.6, 'whoosh-big', -10),                                   # оборот телефона
    (27.8, 'success', -9),                                       # заявка отправлена
    (29.58, 'whoosh-a', -14),                                    # переход: телефон → CRM
    (34.6, 'whoosh-b', -22), (35.6, 'whoosh-b', -22), (36.6, 'whoosh-b', -22), (37.8, 'whoosh-b', -22),  # смена разделов
    (38.58, 'whoosh-a', -14),                                    # переход: CRM → сайты
    (39.45, 'whoosh-a', -16), (40.15, 'whoosh-b', -17), (40.85, 'whoosh-a', -16),  # карточки влетают, тасуются, в сетку
    (41.8, 'pop', -19), (41.92, 'pop', -20), (42.04, 'pop', -20), (42.16, 'pop', -21),  # модули «+»
    (43.58, 'whoosh-a', -14),                                    # переход: сайты → знак WebFor
    (44.3, 'pop', -11), (45.9, 'pop', -15),                      # знак WebFor, адрес сайта
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
