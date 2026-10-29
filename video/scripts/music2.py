"""Фоновая музыка для ролика Vet.izi (без авторских прав — синтезируется кодом).

Ре минор, ~116 BPM, такт = 2.0625 с (16 тактов = 33.0 с — начало финала).
Интро (такты 0–1): пэд и электропиано. Такт 2: вступают бочка, бас и арпеджио. Такт 4: хлопки.
Такты 13–15: нарастание (хэты 16-ми, подъём шума). Такт 16: удар и большой аккорд, затем спокойный финал.
Использование: python3 music2.py <секунд> <out.wav>
"""
import sys
import wave

import numpy as np

SR = 48000
BAR = 2.0625
BEAT = BAR / 4
rng = np.random.default_rng(11)

CHORDS = [  # Dm9, Bbmaj7, Fadd9, C6 (MIDI)
    [50, 53, 57, 60, 64],
    [46, 50, 53, 57, 62],
    [41, 48, 53, 57, 67],
    [48, 52, 55, 57, 64],
]


def hz(m):
    return 440.0 * 2 ** ((m - 69) / 12)


def adsr(n, a, d, s, r):
    e = np.full(n, s, dtype=np.float64)
    a, d, r = int(a * SR), int(d * SR), int(r * SR)
    a = max(a, 1)
    e[:a] = np.linspace(0, 1, a)
    if d:
        e[a:a + d] = np.linspace(1, s, len(e[a:a + d]))
    if r:
        e[-r:] *= np.linspace(1, 0, r)
    return e


def ep(freq, n):  # электропиано (FM)
    t = np.arange(n) / SR
    idx = 2.2 * np.exp(-t * 6)
    s = np.sin(2 * np.pi * freq * t + idx * np.sin(2 * np.pi * freq * t))
    s += 0.25 * np.sin(2 * np.pi * freq * 2 * t) * np.exp(-t * 9)
    return s * np.exp(-t * 2.2)


def pad(freq, n):
    t = np.arange(n) / SR
    s = np.zeros(n)
    for det in (-0.004, 0.0, 0.0045):
        f = freq * (1 + det)
        for k in range(1, 7):
            s += np.sin(2 * np.pi * f * k * t + k) / k ** 1.3
    return s / 3 * adsr(n, 0.5, 0.0, 1.0, 0.6)


def pluck(freq, n):
    t = np.arange(n) / SR
    tri = 2 * np.abs(2 * ((freq * t) % 1) - 1) - 1
    return (0.6 * tri + 0.4 * np.sin(2 * np.pi * freq * t)) * np.exp(-t * 10)


def kick(n):
    t = np.arange(n) / SR
    f = 45 + 85 * np.exp(-t * 28)
    ph = 2 * np.pi * np.cumsum(f) / SR
    return np.sin(ph) * np.exp(-t * 6.5) + 0.15 * rng.standard_normal(n) * np.exp(-t * 300)


def hat(n, dec=70):
    t = np.arange(n) / SR
    x = np.diff(rng.standard_normal(n + 1))
    return x * np.exp(-t * dec)


def clap(n):
    t = np.arange(n) / SR
    x = np.diff(rng.standard_normal(n + 1))
    env = np.zeros(n)
    for o in (0.0, 0.011, 0.023):
        k = int(o * SR)
        env[k:] += np.exp(-(t[: n - k]) * 38)
    return x * env * 0.6


def bass(freq, n):
    t = np.arange(n) / SR
    s = np.sin(2 * np.pi * freq * t) + 0.3 * np.sin(4 * np.pi * freq * t)
    return np.tanh(1.6 * s) * adsr(n, 0.005, 0.12, 0.7, 0.05)


def place(buf, x, at, gain=1.0):
    s = int(at * SR)
    if s >= len(buf):
        return
    e = min(len(buf), s + len(x))
    buf[s:e] += gain * x[: e - s]


def reverb_ir(seconds=2.0):
    n = int(seconds * SR)
    t = np.arange(n) / SR
    ir = rng.standard_normal((n, 2)) * np.exp(-t * 3.2)[:, None]
    ir[: int(0.012 * SR)] = 0
    return ir / np.sqrt((ir ** 2).sum(axis=0))


def convolve(x, ir):
    n = len(x) + len(ir) - 1
    N = 1 << (n - 1).bit_length()
    X = np.fft.rfft(x, N)
    out = np.stack([np.fft.irfft(X * np.fft.rfft(ir[:, c], N), N)[: len(x)] for c in range(2)], axis=1)
    return out


def main(seconds, out):
    n = int(seconds * SR)
    music = np.zeros(n)       # сухая гармония (в реверб)
    drums = np.zeros(n)
    low = np.zeros(n)
    arp = np.zeros(n)
    sc = np.ones(n)           # огибающая «сайдчейна» от бочки
    bars = int(np.ceil(seconds / BAR))
    for b in range(bars):
        t0 = b * BAR
        ch = CHORDS[b % 4]
        final = b >= 16
        # пэд
        ln = int(BAR * SR * 1.1)
        for m in ch[1:]:
            place(music, pad(hz(m), ln), t0, 0.05 if not final else 0.07)
        # электропиано: аккорд на 1 и на «и» 2-й доли
        for at in ((0, 2.5) if b >= 2 else (0,)):
            for m in ch[1:]:
                place(music, ep(hz(m + 12), int(1.6 * SR)), t0 + at * BEAT, 0.07)
        # арпеджио 16-ми с такта 2 (в финале — восьмыми)
        if b >= 2:
            step = BEAT / 4 if not final else BEAT / 2
            notes = [ch[1] + 24, ch[2] + 24, ch[3] + 24, ch[4] + 12]
            k = 0
            t = t0
            while t < t0 + BAR - 1e-6:
                place(arp, pluck(hz(notes[k % 4]), int(0.4 * SR)), t, 0.05)
                k += 1
                t += step
        # бас восьмыми с такта 2
        if 2 <= b < 16 or final:
            root = ch[0] - 12 if ch[0] > 45 else ch[0]
            for i in range(8):
                if final and i % 2:
                    continue
                place(low, bass(hz(root), int(BEAT / 2 * SR * 0.95)), t0 + i * BEAT / 2, 0.16)
        # ударные
        build = 13 <= b < 16
        if 2 <= b < 16 or final:
            for i in range(4):
                if b == 15 and i >= 2:
                    break  # пауза перед ударом
                kt = t0 + i * BEAT
                place(drums, kick(int(0.45 * SR)), kt, 0.5 if not final else 0.38)
                s = int(kt * SR)
                e = min(n, s + int(0.22 * SR))
                if e > s:
                    sc[s:e] = np.minimum(sc[s:e], 0.55 + 0.45 * np.linspace(0, 1, e - s) ** 0.6)
            for i in range(16 if build else 8):
                sub = BEAT / 4 if build else BEAT / 2
                place(drums, hat(int(0.06 * SR)), t0 + i * sub + (0 if build else BEAT / 2 * 0), 0.05 if i % 2 else 0.08)
            if b >= 4 and not (b == 15):
                for i in (1, 3):
                    place(drums, clap(int(0.25 * SR)), t0 + i * BEAT, 0.16)
    # подъём перед финалом (такт 15) и удар на 33.0
    rs, re_ = 15 * BAR, 16 * BAR
    m = int((re_ - rs) * SR)
    t = np.arange(m) / SR
    noise = np.diff(rng.standard_normal(m + 1)) * (t / t[-1]) ** 2
    sweep = np.sin(2 * np.pi * np.cumsum(200 + 1400 * (t / t[-1]) ** 2) / SR) * (t / t[-1]) ** 3
    place(drums, 0.10 * noise + 0.05 * sweep, rs)
    it = np.arange(int(1.8 * SR)) / SR
    boom = np.sin(2 * np.pi * np.cumsum(38 + 60 * np.exp(-it * 8)) / SR) * np.exp(-it * 2.2)
    place(drums, 0.7 * boom + 0.15 * hat(len(it), 9), 16 * BAR)
    for mm in CHORDS[0][1:]:
        place(music, ep(hz(mm + 12), int(3 * SR)), 16 * BAR, 0.1)

    # шина: реверб на гармонии и арпеджио, сайдчейн, стерео
    ir = reverb_ir(2.2)
    wet = convolve(music * 0.9 + arp * 0.7, ir) * 0.35
    dry = np.stack([music + arp * 0.8, music + np.roll(arp, int(0.009 * SR)) * 0.8], axis=1)
    mix = (dry + wet) * sc[:, None]
    mix += (low * sc)[:, None] * 0.9
    mix += drums[:, None]
    # мягкое начало и конец
    fi, fo = int(0.6 * SR), int(2.5 * SR)
    mix[:fi] *= np.linspace(0, 1, fi)[:, None]
    mix[-fo:] *= np.linspace(1, 0, fo)[:, None]
    mix = np.tanh(mix * 1.2) / np.tanh(1.2)
    mix = mix / np.max(np.abs(mix)) * 0.89
    with wave.open(out, 'wb') as w:
        w.setnchannels(2)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes((mix * 32767).astype('<i2').tobytes())


if __name__ == '__main__':
    main(float(sys.argv[1]), sys.argv[2])
