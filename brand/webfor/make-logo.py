"""Логотип WebFor: знак (плитка с W) и знак + надпись. Надпись переведена в кривые, шрифт не нужен.

Использование: python3 make-logo.py   (нужен fonttools и шрифт Inter Display Black)
"""
from pathlib import Path

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

OUT = Path(__file__).parent
FONT = TTFont('/usr/share/fonts/opentype/inter/InterDisplay-Black.otf')
GS = FONT.getGlyphSet()
CMAP = FONT.getBestCmap()
UPM = FONT['head'].unitsPerEm
CAP = FONT['OS/2'].sCapHeight

TEAL_A, TEAL_B, CREAM, MINT = '#19b39c', '#0b6e63', '#f4f1e4', '#3fe0a0'

MARK = (
    '<defs><linearGradient id="wf-g" x1="0" y1="0" x2="1" y2="1">'
    f'<stop offset="0" stop-color="{TEAL_A}"/><stop offset="1" stop-color="{TEAL_B}"/></linearGradient></defs>'
    '<rect x="4" y="4" width="112" height="112" rx="30" fill="url(#wf-g)"/>'
    f'<path d="M26 40 L42 84 L60 52 L78 84 L94 40" fill="none" stroke="{CREAM}" stroke-width="12" '
    'stroke-linecap="round" stroke-linejoin="round"/>'
)


def word(text, size, x0, baseline, tracking=-0.03):
    """Путь для строки текста; возвращает (d, ширина)."""
    s = size / UPM
    pen = SVGPathPen(GS)
    x = 0.0
    for ch in text:
        g = CMAP[ord(ch)]
        GS[g].draw(TransformPen(pen, (s, 0, 0, -s, x0 + x, baseline)))
        x += GS[g].width * s + tracking * size
    return pen.getCommands(), x - tracking * size


def lockup(name, web_color, for_color, bg=None):
    size = 100
    cap = CAP * size / UPM
    gap = 22
    baseline = 60 + cap / 2
    d_web, w_web = word('Web', size, 120 + gap, baseline)
    d_for, w_for = word('For', size, 120 + gap + w_web - 0.03 * size, baseline)
    width = round(120 + gap + w_web - 0.03 * size + w_for + 4)
    pad = 40 if bg else 0
    body = (f'<g transform="translate({pad} {pad})">{MARK}'
            f'<path d="{d_web}" fill="{web_color}"/><path d="{d_for}" fill="{for_color}"/></g>')
    W, H = width + 2 * pad, 120 + 2 * pad
    back = f'<rect width="{W}" height="{H}" fill="{bg}"/>' if bg else ''
    (OUT / f'{name}.svg').write_text(
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W * 4}" height="{H * 4}">{back}{body}</svg>\n')


(OUT / 'webfor-mark.svg').write_text(
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="1024" height="1024">{MARK}</svg>\n')
lockup('webfor-logo-on-dark', '#f2f6f4', MINT)          # для тёмного фона (как в ролике)
lockup('webfor-logo-on-light', '#062019', TEAL_B)       # для светлого фона
lockup('webfor-logo-dark-bg', '#f2f6f4', MINT, '#050908')  # с тёмной подложкой
print('готово')
