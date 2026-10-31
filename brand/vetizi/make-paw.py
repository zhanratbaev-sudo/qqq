"""Лапка Vet.izi без надписей: обложки для «Актуального» в Instagram (1080×1080, лапка в центре — круг не обрежет)
и прозрачная лапка. Использование: python3 make-paw.py
"""
from pathlib import Path

OUT = Path(__file__).parent


def paw(color):
    return (f'<g fill="{color}"><ellipse cx="60" cy="76" rx="20" ry="16"/>'
            '<ellipse cx="34" cy="56" rx="8" ry="11" transform="rotate(-20 34 56)"/>'
            '<ellipse cx="86" cy="56" rx="8" ry="11" transform="rotate(20 86 56)"/>'
            '<ellipse cx="48" cy="38" rx="8" ry="11" transform="rotate(-8 48 38)"/>'
            '<ellipse cx="72" cy="38" rx="8" ry="11" transform="rotate(8 72 38)"/></g>')


def grad(a, b):
    return (f'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{a}"/>'
            f'<stop offset="1" stop-color="{b}"/></linearGradient></defs><rect width="120" height="120" fill="url(#g)"/>')


def cover(name, back, color, scale=0.5):
    # лапка занимает ~половину кадра и центрирована по своему контуру (x 24–96, y 26–92)
    s = scale * 120 / 72
    tx, ty = 60 - 60 * s, 60 - 59 * s
    (OUT / f'{name}.svg').write_text(
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="1080" height="1080">'
        f'{back}<g transform="translate({tx:.2f} {ty:.2f}) scale({s:.3f})">{paw(color)}</g></svg>\n')


cover('vetizi-highlight-cream', grad('#f4f1e4', '#e2dcc4'), '#0e7c6b')   # как иконка Vet.izi
cover('vetizi-highlight-teal', grad('#19b39c', '#0b6e63'), '#f4f1e4')    # цвет WebFor
cover('vetizi-highlight-dark', '<rect width="120" height="120" fill="#062019"/>', '#3fe0a0')
cover('vetizi-highlight-white', '<rect width="120" height="120" fill="#ffffff"/>', '#0e7c6b')
cover('vetizi-paw', '', '#0e7c6b', scale=0.9)                              # прозрачный фон
print('готово')
