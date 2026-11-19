#!/usr/bin/env python3
"""Сборка шрифтов для PDF договора: Inter (OFL) → TrueType, только нужные буквы (латиница, кириллица, казахский).
Запуск: python3 tools/build-pdf-fonts.py /usr/share/fonts/opentype/inter
Результат: petgo-client/includes/pdf-fonts/inter-*.ttf и petgo-client/includes/pdf-fonts/inter-*.php (ширины и номера букв)."""
import sys, os, json
from fontTools.ttLib import TTFont, newTable
from fontTools import subset
from fontTools.pens.cu2quPen import Cu2QuPen
from fontTools.pens.ttGlyphPen import TTGlyphPen

SRC = sys.argv[1] if len(sys.argv) > 1 else '/usr/share/fonts/opentype/inter'
ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'petgo-client')
KZ = 'ӘәҒғҚқҢңӨөҰұҮүҺһІі'
CHARS = set(range(0x20, 0x7F)) | set(range(0xA0, 0x100)) | set(range(0x400, 0x460)) | set(map(ord, KZ + '–—‘’‚“”„•…‰‹›€₸№™−√←→✓·«»§'))

def to_ttf(f):
    go = f.getGlyphOrder()
    gs = f.getGlyphSet()
    glyphs = {}
    for g in go:
        tp = TTGlyphPen(gs)
        gs[g].draw(Cu2QuPen(tp, 1.0, reverse_direction=True))
        glyphs[g] = tp.glyph()
    f['loca'] = newTable('loca')
    f['glyf'] = glyf = newTable('glyf')
    glyf.glyphOrder = go
    glyf.glyphs = glyphs
    del f['CFF ']
    for t in ('VORG',):
        if t in f:
            del f[t]
    glyf.compile(f)
    hm = f['hmtx']
    for g in go:
        gl = glyf[g]
        gl.recalcBounds(glyf)
        adv = hm[g][0]
        hm[g] = (adv, getattr(gl, 'xMin', 0) if gl.numberOfContours else 0)
    maxp = newTable('maxp')
    maxp.tableVersion = 0x00010000
    for a in ('maxZones', 'maxTwilightPoints', 'maxStorage', 'maxFunctionDefs', 'maxInstructionDefs', 'maxStackElements', 'maxSizeOfInstructions', 'maxComponentElements', 'maxComponentDepth'):
        setattr(maxp, a, 1 if a == 'maxZones' else 0)
    f['maxp'] = maxp
    post = f['post']
    post.formatType = 3.0
    f.sfntVersion = '\x00\x01\x00\x00'
    f['head'].indexToLocFormat = 0
    return f

for key, name in (('r', 'Inter-Regular.otf'), ('sb', 'Inter-SemiBold.otf')):
    f = TTFont(os.path.join(SRC, name))
    o = subset.Options()
    o.layout_features = []
    o.hinting = False
    o.notdef_outline = True
    o.name_IDs = [0, 1, 2, 3, 4, 5, 6]
    o.drop_tables += ['GSUB', 'GPOS', 'GDEF', 'kern', 'STAT', 'BASE', 'JSTF', 'MATH', 'DSIG']
    s = subset.Subsetter(o)
    s.populate(unicodes=CHARS)
    s.subset(f)
    f = to_ttf(f)
    out = os.path.join(ROOT, 'includes', 'pdf-fonts', 'inter-%s.ttf' % key)
    f.save(out)
    f = TTFont(out)
    upm = f['head'].unitsPerEm
    sc = 1000.0 / upm
    cmap = f.getBestCmap()
    go = f.getGlyphOrder()
    gid = {g: i for i, g in enumerate(go)}
    hm = f['hmtx']
    m = {}
    for u, g in sorted(cmap.items()):
        m[u] = [gid[g], int(round(hm[g][0] * sc))]
    os2, hh, hd = f['OS/2'], f['hhea'], f['head']
    meta = {
        'name': 'Inter-' + ('Regular' if key == 'r' else 'SemiBold'),
        'asc': int(round(hh.ascent * sc)), 'desc': int(round(hh.descent * sc)),
        'cap': int(round(getattr(os2, 'sCapHeight', 727) * sc)),
        'bbox': [int(round(hd.xMin * sc)), int(round(hd.yMin * sc)), int(round(hd.xMax * sc)), int(round(hd.yMax * sc))],
        'dw': int(round(hm['.notdef'][0] * sc)), 'n': len(go),
        'w': {g: int(round(hm[g][0] * sc)) for g in go},
    }
    php = '<?php\n/* ' + meta['name'] + ': номер буквы и ширина (1/1000 кегля). Собрано tools/build-pdf-fonts.py — не править руками. */\nreturn array(\n'
    php += "    'name' => '%s', 'asc' => %d, 'desc' => %d, 'cap' => %d, 'bbox' => array(%s), 'dw' => %d,\n" % (meta['name'], meta['asc'], meta['desc'], meta['cap'], ', '.join(map(str, meta['bbox'])), meta['dw'])
    php += "    'map' => array(" + ', '.join('%d => array(%d, %d)' % (u, v[0], v[1]) for u, v in m.items()) + '),\n'
    php += "    'gw' => array(" + ', '.join(str(int(round(hm[g][0] * sc))) for g in go) + '),\n);\n'
    open(os.path.join(ROOT, 'includes', 'pdf-fonts', 'inter-%s.php' % key), 'w').write(php)
    print(key, out, os.path.getsize(out), 'bytes,', len(m), 'chars,', len(go), 'glyphs, upm', upm)
