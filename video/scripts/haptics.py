"""Фоновые звуки для ролика Vet.izi → video/vetizi/assets/sfx.wav (синтез, без чужих сэмплов).

Только мягкая «вибрация» (короткий гул телефона + низкий толчок) на глобальных сменах кадра
и тихий подъём перед финальной заливкой. Никаких кликов и клавиатуры: основная музыка
(SEVER — Vision) добавляется отдельно — в Instagram при публикации или файлом в assets/track.*.

Использование: python3 haptics.py <путь к video/vetizi>
"""
import sys
import wave
from pathlib import Path

import numpy as np

SR = 48000
DUR = 50.0
ROOT = Path(sys.argv[1] if len(sys.argv) > 1 else '.')

# (секунда ролика, сила 0..1) — глобальные смены: знак собрался, переворот, телефон, смены сцен, финал
HITS = [(1.1, 0.8), (3.6, 0.6), (7.6, 0.9), (16.4, 0.7), (22.95, 0.7), (26.6, 0.6),
        (29.95, 0.7), (38.9, 0.7), (44.0, 0.8), (45.55, 1.0)]
SWELLS = [(43.2, 44.8)]  # тихий подъём перед заливкой экрана цветом логотипа


def hit(strength):
    n = int(0.55 * SR)
    t = np.arange(n) / SR
    thump = np.sin(2 * np.pi * np.cumsum(42 + 30 * np.exp(-t * 18)) / SR) * np.exp(-t * 7)
    buzz_env = ((t < 0.07) | ((t > 0.11) & (t < 0.17))).astype(float) * np.exp(-t * 4)
    buzz = np.tanh(3 * np.sin(2 * np.pi * 165 * t)) * buzz_env * 0.18
    x = (0.9 * thump + buzz) * strength
    x[:int(0.004 * SR)] *= np.linspace(0, 1, int(0.004 * SR))
    return x


def swell(seconds):
    n = int(seconds * SR)
    t = np.arange(n) / SR
    rng = np.random.default_rng(3)
    noise = np.convolve(rng.standard_normal(n), np.ones(60) / 60, mode='same')  # мягкий шум (низкочастотный)
    env = (t / t[-1]) ** 2 * (1 - np.clip((t - t[-1] + 0.15) / 0.15, 0, 1))
    return noise * env * 1.2


out = np.zeros(int(DUR * SR))
for at, k in HITS:
    x = hit(k); s = int(at * SR); e = min(len(out), s + len(x)); out[s:e] += x[: e - s]
for a, b in SWELLS:
    x = swell(b - a); s = int(a * SR); out[s:s + len(x)] += x
out = out / np.abs(out).max() * 0.35
st = np.stack([out, out], axis=1)
with wave.open(str(ROOT / 'assets' / 'sfx.wav'), 'wb') as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes((st * 32767).astype('<i2').tobytes())
print('sfx.wav (вибрации) готов')
