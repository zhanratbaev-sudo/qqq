"""Генерирует лёгкую фоновую музыку (без авторских прав — синтезируется кодом).

Мягкий пэд + арпеджио + тихий бит, 104 BPM, прогрессия Fmaj7–Am7–Dm7–C.
Использование: python3 music.py <секунд> <out.wav>
"""
import sys
import wave

import numpy as np

SR = 44100
BPM = 104
BEAT = 60 / BPM

CHORDS = [  # MIDI-ноты
    [53, 57, 60, 64],  # Fmaj7
    [57, 60, 64, 67],  # Am7
    [50, 53, 57, 60],  # Dm7
    [48, 52, 55, 60],  # C
]


def hz(m):
    return 440 * 2 ** ((m - 69) / 12)


def env(n, attack, release):
    e = np.ones(n)
    a, r = int(attack * SR), int(release * SR)
    e[:a] = np.linspace(0, 1, a)
    e[-r:] *= np.linspace(1, 0, r)
    return e


def pad(freq, n):
    t = np.arange(n) / SR
    s = sum(np.sin(2 * np.pi * freq * k * t + 0.3 * k) / k ** 1.6 for k in range(1, 5))
    s *= 1 + 0.1 * np.sin(2 * np.pi * 0.25 * t)  # лёгкое «дыхание»
    return s * env(n, 0.6, 0.8)


def pluck(freq, n):
    t = np.arange(n) / SR
    return (np.sin(2 * np.pi * freq * t) + 0.3 * np.sin(4 * np.pi * freq * t)) * np.exp(-t * 7)


def kick(n):
    t = np.arange(n) / SR
    return np.sin(2 * np.pi * (50 + 90 * np.exp(-t * 30)) * t) * np.exp(-t * 9)


def hat(n, rng):
    t = np.arange(n) / SR
    noise = np.diff(rng.standard_normal(n + 1))  # high-pass белого шума
    return noise * np.exp(-t * 60)


def main(seconds, out):
    rng = np.random.default_rng(7)
    n = int(seconds * SR)
    mix = np.zeros(n + SR * 4)
    bar = 4 * BEAT
    t = 0.0
    i = 0
    while t < seconds:
        chord = CHORDS[i % len(CHORDS)]
        s = int(t * SR)
        ln = int(bar * SR * 1.15)
        for m in chord:
            mix[s:s + ln] += 0.07 * pad(hz(m), ln)
        mix[s:s + ln] += 0.09 * pad(hz(chord[0] - 12), ln)  # бас
        for b in range(8):  # арпеджио восьмыми
            ps = s + int(b * BEAT / 2 * SR)
            pl = int(0.6 * SR)
            note = chord[[0, 1, 2, 3, 2, 1, 2, 3][b]] + 12
            mix[ps:ps + pl] += 0.06 * pluck(hz(note), pl)
        if t > 2 * bar:  # бит вступает со 3-го такта
            for b in range(4):
                ks = s + int(b * BEAT * SR)
                mix[ks:ks + int(0.35 * SR)] += 0.22 * kick(int(0.35 * SR))
                hs = ks + int(BEAT / 2 * SR)
                mix[hs:hs + int(0.08 * SR)] += 0.025 * hat(int(0.08 * SR), rng)
        t += bar
        i += 1
    mix = mix[:n]
    fade = int(2.5 * SR)
    mix[:int(1.0 * SR)] *= np.linspace(0, 1, int(1.0 * SR))
    mix[-fade:] *= np.linspace(1, 0, fade)
    mix = mix / np.max(np.abs(mix)) * 0.8
    stereo = np.stack([mix, np.roll(mix, int(0.012 * SR))], axis=1)  # лёгкая ширина
    with wave.open(out, 'wb') as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes((stereo * 32767).astype('<i2').tobytes())


if __name__ == '__main__':
    main(float(sys.argv[1]), sys.argv[2])
