import sys, re
F = sys.argv[1]
r = lambda n: open(F + '/' + n).read()
def strip_anim(svg):
    return re.sub(r'\sclass="[^"]*"', '', svg)
paw = '<g fill="currentColor"><ellipse cx="24" cy="44" rx="9" ry="11" transform="rotate(-18 24 44)"/><ellipse cx="40" cy="27" rx="9.5" ry="12"/><ellipse cx="60" cy="27" rx="9.5" ry="12"/><ellipse cx="76" cy="44" rx="9" ry="11" transform="rotate(18 76 44)"/><path d="M50 50c10 0 17 9 22 17 4 6 3 14-4 16-6 2-11-1-18-1s-12 3-18 1c-7-2-8-10-4-16 5-8 12-17 22-17z"/></g>'
check = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>'
bag = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1 12.2a1 1 0 0 1-1 .8H7a1 1 0 0 1-1-.8L5 8Z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/></svg>'
h = r('template.html')
rep = {'LOGO': r('logo.svg'), 'DOG': strip_anim(r('dog-gold.svg')), 'DOG2': strip_anim(r('dog-cream.svg')),
       'CAT': strip_anim(r('cat-orange.svg')), 'CAT2': strip_anim(r('cat-gray.svg')), 'PAW': paw, 'CHECK': check, 'BAG': bag,
       'QR_SITE': r('qr-site.svg'), 'QR_WA': r('qr-wa.svg'), 'QR_IG': r('qr-ig.svg'), 'DATE': sys.argv[2]}
for k, v in rep.items():
    h = h.replace('{{%s}}' % k, v)
open(F + '/flyer.html', 'w').write(h)
print('ok', len(h))
