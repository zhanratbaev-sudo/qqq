import sys, qrcode
out = sys.argv[1]
codes = {
    'site': 'https://petgo.kz/?utm_source=flyer&utm_medium=print&utm_campaign=a4',
    'wa': 'https://wa.me/77478175712',
    'ig': 'https://instagram.com/petgo.kz',
}
for name, data in codes.items():
    q = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_H, border=0, box_size=1)
    q.add_data(data); q.make(fit=True)
    m = q.get_matrix(); n = len(m)
    # центр под знак PetGo: квадрат ~22% стороны (уровень H выдерживает до 30%)
    hole = int(n * 0.22) | 1
    a = (n - hole) // 2
    d = []
    for y, row in enumerate(m):
        for x, v in enumerate(row):
            if v and not (a - 1 <= x <= a + hole and a - 1 <= y <= a + hole):
                d.append('M%d %dh1v1h-1z' % (x, y))
    c = n / 2; r = hole / 2 + 0.6
    paw = ('<g transform="translate(%.2f %.2f) scale(%.4f)">' % (c - r * 0.82, c - r * 0.82, r * 1.64 / 100)
           + '<circle cx="50" cy="50" r="50" fill="#024C46"/><g fill="#FBF9E8"><ellipse cx="27" cy="45" rx="8.5" ry="11" transform="rotate(-18 27 45)"/><ellipse cx="41" cy="29" rx="9" ry="12"/><ellipse cx="59" cy="29" rx="9" ry="12"/><ellipse cx="73" cy="45" rx="8.5" ry="11" transform="rotate(18 73 45)"/><path d="M50 50c10 0 17 9 21 16 4 6 2 13-5 15-6 2-10-1-16-1s-10 3-16 1c-7-2-9-9-5-15 4-7 11-16 21-16z"/></g></g>')
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="-4 -4 %d %d" shape-rendering="crispEdges"><rect x="-4" y="-4" width="%d" height="%d" fill="#fff"/>' % (n + 8, n + 8, n + 8, n + 8)
           + '<path fill="#0F2A26" d="%s"/>' % ''.join(d)
           + '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="#fff" shape-rendering="geometricPrecision"/>' % (c - r, c - r, 2 * r, 2 * r, r * 0.35)
           + paw.replace('<g transform', '<g shape-rendering="geometricPrecision" transform') + '</svg>')
    open('%s/qr-%s.svg' % (out, name), 'w').write(svg)
    print(name, 'version', q.version, 'modules', n, 'len', len(data))
