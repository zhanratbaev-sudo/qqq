/*!
 * Bota — анимированный ИИ-маскот (веб-компонент <bota-assistant>)
 * Один файл, без зависимостей. Подключение:
 *   <script src="bota.js"></script>
 *   <bota-assistant variant="bota" floating></bota-assistant>
 *
 * Атрибуты:
 *   variant  = bota | oscar   (oscar — 3D-персонаж Oscar Clinic, слои в папке oscar/)
 *   hat      = tubeteika | mirror | cap | none           (переопределяет пресет)
 *   glasses  = round | none                              (переопределяет пресет)
 *   state    = idle | thinking | talking
 *   floating                                             (плавает в правом нижнем углу)
 *   label    = текст для скринридера (по умолчанию имя персонажа)
 *
 * JS API:  el.setState('thinking' | 'talking' | 'idle'), el.burst(), el.blink()
 * Событие: 'bota-click' — по персонажу кликнули (откройте здесь чат)
 * Цвета — CSS-переменные (см. README.md).
 */
(() => {
  if (customElements.get('bota-assistant')) return;

  // Плавный переход цвета звезды (синий → красный) через зарегистрированное свойство
  try {
    CSS.registerProperty({ name: '--bota-star', syntax: '<color>', inherits: true, initialValue: '#3B6CFF' });
  } catch (e) { /* уже зарегистрировано или не поддерживается — цвет сменится без плавности */ }

  const SVGNS = 'http://www.w3.org/2000/svg';
  const f = (n) => Math.round(n * 100) / 100;
  const nz = (v) => { const l = Math.hypot(v.x, v.y) || 1; return { x: v.x / l, y: v.y / l }; };

  // ---------- Звезда (viewBox 0 -12 200 252) ----------
  const C = { x: 100, y: 152 };
  const TIP = { top: { x: 100, y: 96 }, right: { x: 146, y: 152 }, bottom: { x: 100, y: 214 }, left: { x: 54, y: 152 } };
  const ORDER = [TIP.top, TIP.right, TIP.bottom, TIP.left];
  const CONCAVE = 0.3;
  const GAP = 8; // одинаковый зазор между звездой и руками

  const ctrl = (a, b) => {
    const cx = Math.abs(a.x - C.x) > Math.abs(b.x - C.x) ? a.x : b.x;
    const cy = Math.abs(a.y - C.y) > Math.abs(b.y - C.y) ? a.y : b.y;
    return { x: C.x + (cx - C.x) * CONCAVE, y: C.y + (cy - C.y) * CONCAVE };
  };
  const q = (a, c, b, t) => ({ x: (1 - t) ** 2 * a.x + 2 * (1 - t) * t * c.x + t * t * b.x,
                               y: (1 - t) ** 2 * a.y + 2 * (1 - t) * t * c.y + t * t * b.y });
  const qd = (a, c, b, t) => ({ x: 2 * (1 - t) * (c.x - a.x) + 2 * t * (b.x - c.x),
                                y: 2 * (1 - t) * (c.y - a.y) + 2 * t * (b.y - c.y) });

  const starPath = (() => {
    let d = `M${TIP.top.x},${TIP.top.y}`;
    for (let i = 0; i < 4; i++) {
      const a = ORDER[i], b = ORDER[(i + 1) % 4], c = ctrl(a, b);
      d += ` Q${f(c.x)},${f(c.y)} ${b.x},${b.y}`;
    }
    return d + 'Z';
  })();

  // 8 граней «кристалла»: каждый луч делится пополам на светлую и тёмную сторону
  const facets = (() => {
    const out = [];
    for (let i = 0; i < 4; i++) {
      const a = ORDER[i], b = ORDER[(i + 1) % 4], c = ctrl(a, b);
      const half1 = [], half2 = [];
      for (let k = 0; k <= 8; k++) half1.push(q(a, c, b, (k / 8) * 0.5));
      for (let k = 0; k <= 8; k++) half2.push(q(a, c, b, 0.5 + (k / 8) * 0.5));
      const poly = (pts) => `M${C.x},${C.y} L` + pts.map((p) => `${f(p.x)},${f(p.y)}`).join(' L') + 'Z';
      out.push(poly(half1), poly(half2));
    }
    // порядок: top→right(2), right→bottom(2), bottom→left(2), left→top(2)
    // свет сверху-слева
    const shade = ['f2', 'f3', 'f3', 'f4', 'f4', 'f3', 'f2', 'f1'];
    return out.map((d, i) => `<path class="facet ${shade[i]}" d="${d}"/>`).join('');
  })();

  // ---------- Сглаженный контур через Catmull-Rom ----------
  function smooth(pts, closed = true) {
    const n = pts.length, P = (i) => pts[closed ? (i + n) % n : Math.max(0, Math.min(n - 1, i))];
    let d = `M${f(pts[0].x)},${f(pts[0].y)}`;
    const last = closed ? n : n - 1;
    for (let i = 0; i < last; i++) {
      const p0 = P(i - 1), p1 = P(i), p2 = P(i + 1), p3 = P(i + 2);
      d += ` C${f(p1.x + (p2.x - p0.x) / 6)},${f(p1.y + (p2.y - p0.y) / 6)} ` +
           `${f(p2.x - (p3.x - p1.x) / 6)},${f(p2.y - (p3.y - p1.y) / 6)} ${f(p2.x)},${f(p2.y)}`;
    }
    return d + (closed ? 'Z' : '');
  }

  // ---------- Руки: внутренний край строго параллелен звезде, концы круглые ----------
  function armPoints() {
    const a = TIP.left, b = TIP.bottom, c = ctrl(a, b);
    const at = (t, g) => {
      const p = q(a, c, b, t), d = nz(qd(a, c, b, t));
      let n = { x: d.y, y: -d.x };
      if (n.x * (p.x - C.x) + n.y * (p.y - C.y) < 0) n = { x: -n.x, y: -n.y };
      return { x: p.x + n.x * g, y: p.y + n.y * g, n, d };
    };
    // внутренний край: ровно на GAP от нижней грани звезды
    const T0 = 0.06, T1 = 0.9, inner = [];
    for (let i = 0; i <= 10; i++) inner.push(at(T0 + (T1 - T0) * (i / 10), GAP));
    // круглый «кулачок» внизу
    const e = inner[inner.length - 1], R = 12;
    const cc = { x: e.x + e.n.x * R, y: e.y + e.n.y * R }, fist = [];
    for (let i = 1; i <= 4; i++) {
      const th = Math.PI * (i / 6);
      fist.push({ x: cc.x + R * (-e.n.x * Math.cos(th) + e.d.x * Math.sin(th)),
                  y: cc.y + R * (-e.n.y * Math.cos(th) + e.d.y * Math.sin(th)) });
    }
    // наружная долька руки и плечо
    const outer = [{ x: 54, y: 228 }, { x: 30, y: 223 }, { x: 15, y: 205 }, { x: 10, y: 181 }, { x: 16, y: 158 }];
    // круглая ладошка у кончика звезды
    const s = inner[0];
    const hand = [{ x: 29, y: 152 }, { x: s.x - s.d.x * 7 - s.n.x * 1, y: s.y - s.d.y * 7 - s.n.y * 1 }];
    return [...inner, ...fist, ...outer, ...hand];
  }
  const armL = armPoints();
  const armR = armL.map((p) => ({ x: 200 - p.x, y: p.y })).reverse();
  const armLPath = smooth(armL), armRPath = smooth(armR);

  // Тело-капля (как у пушистого робота): узкая голова, широкий низ
  const bodyPath = smooth([
    { x: 100, y: 22 }, { x: 132, y: 28 }, { x: 150, y: 56 }, { x: 156, y: 92 }, { x: 172, y: 124 },
    { x: 184, y: 160 }, { x: 178, y: 196 }, { x: 150, y: 214 }, { x: 120, y: 222 }, { x: 100, y: 224 },
    { x: 80, y: 222 }, { x: 50, y: 214 }, { x: 22, y: 196 }, { x: 16, y: 160 }, { x: 28, y: 124 },
    { x: 44, y: 92 }, { x: 50, y: 56 }, { x: 68, y: 28 },
  ]);

  const sparkle = (s) => {
    const k = s * 0.2;
    return `M0,${-s} Q${k},${-k} ${s},0 Q${k},${k} 0,${s} Q${-k},${k} ${-s},0 Q${-k},${-k} 0,${-s}Z`;
  };

  // ---------- Глаза: тёмный зрачок из очень тонких полосок-«радиоволн» ----------
  const EYE = { l: { x: 77, y: 70 }, r: { x: 123, y: 70 } };
  const eye = (side) => {
    const { x, y } = EYE[side];
    const N = 22, W = 11.6, bars = [];
    for (let i = 0; i < N; i++) {
      const bx = -W / 2 + (W * i) / (N - 1);
      const env = Math.sqrt(Math.max(0, 1 - (bx / (W / 2 + 0.4)) ** 2));
      const h = 21 * env * (0.9 + 0.1 * Math.sin(i * 2.3));
      const dur = (0.9 + ((i * 37) % 10) / 12).toFixed(2), del = (-((i * 53) % 17) / 10).toFixed(2);
      bars.push(`<rect class="bar" style="--dur:${dur}s;--del:${del}s;--i:${i}" x="${f(bx - 0.5)}" y="${f(-h / 2)}" width="1.3" height="${f(h)}" rx="0.65"/>`);
    }
    return `
      <g class="eye" transform="translate(${x} ${y})">
        <g class="lid">
          <ellipse class="sclera" rx="10.5" ry="15"/>
          <g class="look">
            <g class="pupil"><ellipse class="pcore" rx="4.9" ry="9.2"/>${bars.join('')}</g>
            <circle class="hl" cx="-2.4" cy="-5.2" r="2.7"/>
            <circle class="hl hl2" cx="2.4" cy="4.4" r="1.25"/>
          </g>
        </g>
      </g>`;
  };

  // ---------- Головные уборы ----------
  const diamonds = (() => {
    let s = '';
    for (let x = -28; x <= 28; x += 8) {
      const yy = -3.6 + 4.2 * (1 - (x / 37) ** 2);
      s += `<path class="tb-dot" d="M${x},${f(yy - 2.2)} l2,2.2 l-2,2.2 l-2,-2.2Z"/>`;
    }
    return s;
  })();
  const HATS = {
    tubeteika: `
      <g class="hat" transform="translate(88 36) rotate(-13)">
        <path class="tb-dome" d="M-34,-7 C-36,-27 -24,-41 0,-41 C24,-41 36,-27 34,-7 Q0,1 -34,-7Z"/>
        <path class="tb-seam" d="M0,-41 Q-15,-27 -19,-5 M0,-41 Q15,-27 19,-5"/>
        <g class="tb-orn">
          <path d="M0,-11 C-1,-19 -9,-24 -13.5,-19.5 C-16.5,-16 -11.5,-11.5 -9,-15"/>
          <path d="M0,-11 C1,-19 9,-24 13.5,-19.5 C16.5,-16 11.5,-11.5 9,-15"/>
          <path d="M0,-11 V-5.5"/>
          <path d="M-27,-12 c-2,-5 1,-9 4,-8 M27,-12 c2,-5 -1,-9 -4,-8"/>
        </g>
        <path class="tb-gem" d="M0,-32 l3,3.4 l-3,3.4 l-3,-3.4Z"/>
        <path class="tb-band" d="M-36,-8 Q0,0 36,-8 L37,0 Q0,9 -37,0Z"/>
        ${diamonds}
        <circle class="tb-bead" cx="0" cy="-41" r="2.8"/>
        <ellipse class="gloss" cx="-15" cy="-27" rx="8" ry="4" transform="rotate(-32 -15 -27)"/>
      </g>`,
    mirror: `
      <g class="hat">
        <path class="mr-strap" d="M48,56 C66,34 134,34 152,56"/>
        <path class="mr-strap-hl" d="M60,46 C76,34 124,34 140,46"/>
        <g transform="translate(100 34)">
          <circle class="mr-rim" r="13.5"/>
          <circle class="mr-glass" r="10"/>
          <circle class="mr-hole" r="2.2"/>
          <path class="mr-glint" d="M-6,-3.5 a7,7 0 0 1 4.5,-4.5"/>
        </g>
      </g>`,
    cap: `
      <g class="hat">
        <path class="cp-fill" d="M64,40 C60,10 140,10 136,40 Q100,48 64,40Z"/>
        <path class="cp-fold" d="M62,41 Q100,50 138,41 L137,32 Q100,41 63,32Z"/>
        <path class="cp-plus" d="M100,15 v12 M94,21 h12"/>
        <ellipse class="gloss" cx="80" cy="22" rx="8" ry="4" transform="rotate(-25 80 22)"/>
      </g>`,
    none: '',
  };

  const GLASSES = {
    round: `
      <g class="glasses">
        <circle class="lens" cx="77" cy="70" r="16.5"/>
        <circle class="lens" cx="123" cy="70" r="16.5"/>
        <path class="frame" d="M93.5,67 Q100,61 106.5,67 M60.5,67 L48,63 M139.5,67 L152,63"/>
        <path class="glare" d="M66,63 a12,12 0 0 1 8,-6 M112,63 a12,12 0 0 1 8,-6"/>
      </g>`,
    none: '',
  };

  const PRESETS = {
    bota: { hat: 'tubeteika', glasses: 'none', name: 'Bota' },
  };

  // =====================================================================
  //  OSCAR — 3D-персонаж (растровые слои + анимированные SVG-накладки)
  //  Координаты ниже — в пикселях исходного рендера (viewBox 40 190 816 860)
  // =====================================================================
  const OSCAR_EMBED = null;      // single-file build: { body: 'data:…', star: 'data:…' }
  const DEFAULT_VARIANT = 'bota';
  const SCRIPT_BASE = (document.currentScript && document.currentScript.src)
    ? new URL('.', document.currentScript.src).href : './';
  const VB = { x: 40, y: 190, w: 816, h: 860 };
  const OS = { x: 447, y: 706 };                       // центр звезды
  const STAR_BOX = { x: 330, y: 583, w: 237, h: 239 };  // слой звезды
  const pct = (v, o, s) => `${f(((v - o) / s) * 100)}%`;
  // золотые звёздочки вокруг звезды: [x, y, радиус, появляются только когда думает?]
  const SPARKS3D = [
    [525, 605, 20, 0], [595, 740, 18, 0], [355, 822, 16, 0], [382, 627, 12, 0], [334, 752, 13, 0],
    [552, 772, 12, 0], [599, 677, 11, 0], [300, 672, 13, 0], [425, 812, 9, 0], [290, 795, 12, 0],
    [485, 775, 13, 1], [331, 651, 9, 1], [455, 827, 10, 1], [272, 707, 9, 1], [317, 709, 8, 1],
    [565, 596, 9, 1], [590, 620, 7, 1], [615, 712, 7, 1], [489, 594, 6, 1], [277, 752, 7, 1],
  ];
  const puffy = (s) => {
    const k = s * 0.26;
    return `M0,${-s} Q${f(k)},${f(-k)} ${s},0 Q${f(k)},${f(k)} 0,${s} Q${f(-k)},${f(k)} ${-s},0 Q${f(-k)},${f(-k)} 0,${-s}Z`;
  };

  const CSS_IMG = `
  :host {
    --bota-size: 160px;
    --bota-accent: #FFC861;
    --bota-accent-think: #FF4D5E;
    --bota-think-hue: -42deg;
    --bota-think-time: 1.6s;
    --bota-star: var(--bota-accent);
    transition: --bota-star var(--bota-think-time) ease;
    display: inline-block; width: var(--bota-size); aspect-ratio: ${VB.w} / ${VB.h};
    position: relative; -webkit-tap-highlight-color: transparent; user-select: none;
  }
  :host([state="thinking"]) { --bota-star: var(--bota-accent-think); }
  :host([floating]) {
    position: fixed; right: max(20px, env(safe-area-inset-right)); bottom: max(20px, env(safe-area-inset-bottom));
    z-index: 2147483000; --bota-size: 104px;
  }
  button { all: unset; display: block; width: 100%; height: 100%; cursor: pointer; border-radius: 40%; }
  button:focus-visible { outline: 3px solid var(--bota-accent); outline-offset: 4px; }
  .float { width: 100%; height: 100%; animation: bob 4s ease-in-out infinite; }
  .squash { width: 100%; height: 100%; transform-origin: 50% 100%; }
  .stage { position: relative; width: 100%; height: 100%; transform-origin: 50% 100%;
           animation: breath 4s ease-in-out infinite; transition: rotate .4s ease; }
  button:hover .stage { rotate: -3deg; }
  @keyframes bob { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-4%) } }
  @keyframes breath { 0%,100% { transform: scale(1,1) } 50% { transform: scale(1.015,.985) } }
  .stage > * { position: absolute; inset: 0; width: 100%; height: 100%; }
  .body { object-fit: contain; pointer-events: none; }
  svg { overflow: visible; pointer-events: none; }

  .glow { fill: url(#OGLOW); opacity: .75; transform-box: fill-box; transform-origin: center;
          animation: oglow 3s ease-in-out infinite; transition: opacity var(--bota-think-time); }
  .g0 { stop-color: var(--bota-star); stop-opacity: .85; }
  .g1 { stop-color: var(--bota-star); stop-opacity: .28; }
  .g2 { stop-color: var(--bota-star); stop-opacity: 0; }
  @keyframes oglow { 0%,100% { transform: scale(.94) } 50% { transform: scale(1.06) } }

  .ring { fill: none; stroke: #fff; stroke-opacity: .5; stroke-width: 3.5; }
  .ring-soft { fill: none; stroke: #E9E4FF; stroke-opacity: .35; stroke-width: 10; filter: url(#OBLUR); }
  .comet { fill: none; stroke: #fff; stroke-width: 6; stroke-linecap: round; stroke-dasharray: 90 910;
           filter: url(#OSOFT); animation: comet 5s linear infinite; }
  .comet.c2 { animation-delay: -2.5s; stroke-width: 4; stroke-dasharray: 40 960; }
  @keyframes comet { to { stroke-dashoffset: -1000; } }

  .star-wrap { left: ${pct(STAR_BOX.x, VB.x, VB.w)}; top: ${pct(STAR_BOX.y, VB.y, VB.h)};
               width: ${pct(STAR_BOX.x + STAR_BOX.w, STAR_BOX.x, VB.w)}; height: ${pct(STAR_BOX.y + STAR_BOX.h, STAR_BOX.y, VB.h)};
               right: auto; bottom: auto; perspective: 600px; }
  .star-move { width: 100%; height: 100%; animation: sfloat 3.2s ease-in-out infinite; }
  .star { width: 100%; height: 100%; display: block; transform-style: preserve-3d;
          animation: ssway 4.6s ease-in-out infinite;
          filter: hue-rotate(0deg) saturate(1) brightness(1) drop-shadow(0 0 0 transparent);
          transition: filter var(--bota-think-time) ease; }
  @keyframes sfloat { 0%,100% { transform: translateY(2%) } 50% { transform: translateY(-5%) } }
  @keyframes ssway { 0%,100% { transform: rotateY(-18deg) rotateZ(-5deg) scale(1) }
                     50% { transform: rotateY(18deg) rotateZ(5deg) scale(1.05) } }

  .spark { fill: url(#OSPARK); filter: url(#OSOFT2); transform-box: fill-box; transform-origin: center;
           animation: otw var(--t) ease-in-out var(--d) infinite; }
  .spark-wrap.extra { opacity: 0; transition: opacity .5s ease; }
  .sp0 { stop-color: #FFFBEF; } .sp1 { stop-color: color-mix(in srgb, var(--bota-star), #fff 35%); }
  .sp2 { stop-color: color-mix(in srgb, var(--bota-star), #b8782a 30%); }
  @keyframes otw { 0%,100% { transform: scale(.7) rotate(0deg); opacity: .75 } 50% { transform: scale(1.12) rotate(20deg); opacity: 1 } }



  /* думает: звезда краснеет, вращается, свет ярче, звёздочек больше */
  :host([state="thinking"]) .star { filter: hue-rotate(var(--bota-think-hue)) saturate(1.7) brightness(1.18)
                                     drop-shadow(0 0 10px color-mix(in srgb, var(--bota-accent-think) 70%, transparent)); }
  :host([state="thinking"]) .star { animation: sspin 2.4s cubic-bezier(.45,0,.55,1) infinite; }
  :host([state="thinking"]) .star-move { animation-duration: 1.6s; }
  :host([state="thinking"]) .glow { opacity: 1; animation-duration: 1.2s; }
  :host([state="thinking"]) .comet { animation-duration: 1.8s; }
  :host([state="thinking"]) .spark-wrap.extra { opacity: 1; }
  :host([state="thinking"]) .spark { animation-duration: 1.1s !important; }
  @keyframes sspin { 0% { transform: rotateY(0deg) scale(1.08) } 100% { transform: rotateY(360deg) scale(1.08) } }

  /* отвечает: звезда пульсирует в такт «голосу» */
  :host([state="talking"]) .star-move { animation: stalk .36s ease-in-out infinite alternate; }
  @keyframes stalk { from { transform: translateY(0) scale(.97) } to { transform: translateY(-2%) scale(1.06) } }

  .burst path { fill: url(#OSPARK); }

  @media (prefers-reduced-motion: reduce) {
    .float, .stage, .star, .star-move, .glow, .comet, .spark { animation: none !important; }
  }`;

  function oscarMarkup(base, name) {
    const ring = (cls, extra = '') => `<ellipse class="${cls}" ${extra} cx="${OS.x + 3}" cy="${OS.y}" rx="170" ry="50" pathLength="1000"/>`;
    const ringSet = () => ring('ring-soft') + ring('ring') + ring('comet') + ring('comet c2');
    const rot = `rotate(-8 ${OS.x} ${OS.y})`;
    const sparks = (extra) => SPARKS3D.filter((s) => !!s[3] === extra).map(([x, y, r], i) => `
      <g class="spark-wrap${extra ? ' extra' : ''}" transform="translate(${x} ${y})">
        <path class="spark" style="--t:${(2 + (i % 5) * 0.35).toFixed(2)}s;--d:${(-(i * 0.47) % 3).toFixed(2)}s" d="${puffy(r)}"/>
      </g>`).join('');
    const vb = `${VB.x} ${VB.y} ${VB.w} ${VB.h}`;
    return `
      <style>${CSS_IMG}</style>
      <button type="button" part="button" aria-label="${name}">
        <div class="float"><div class="squash"><div class="stage">
          <img class="body" src="${base.body}" alt="" draggable="false">
          <svg class="back" viewBox="${vb}" aria-hidden="true">
            <defs>
              <radialGradient id="OGLOW"><stop offset="0" class="g0"/><stop offset=".45" class="g1"/><stop offset="1" class="g2"/></radialGradient>
              <filter id="OBLUR" x="-20%" y="-50%" width="140%" height="200%"><feGaussianBlur stdDeviation="5"/></filter>
              <filter id="OSOFT" x="-20%" y="-50%" width="140%" height="200%"><feGaussianBlur stdDeviation="2.5"/></filter>
              <clipPath id="OBACK"><rect x="${OS.x - 260}" y="${OS.y - 120}" width="520" height="120"/></clipPath>
              <clipPath id="OFRONT"><rect x="${OS.x - 260}" y="${OS.y}" width="520" height="120"/></clipPath>
            </defs>
            <circle class="glow" cx="${OS.x}" cy="${OS.y}" r="185"/>
            <g transform="${rot}"><g clip-path="url(#OBACK)">${ringSet()}</g></g>
          </svg>
          <div class="star-wrap"><div class="star-move"><img class="star" src="${base.star}" alt="" draggable="false"></div></div>
          <svg class="front" viewBox="${vb}" aria-hidden="true">
            <defs>
              <radialGradient id="OSPARK" cx=".4" cy=".35" r=".7"><stop offset="0" class="sp0"/><stop offset=".5" class="sp1"/><stop offset="1" class="sp2"/></radialGradient>
              <filter id="OSOFT2" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="1.2"/></filter>
            </defs>
            <g transform="${rot}"><g clip-path="url(#OFRONT)">${ringSet()}</g></g>
            ${sparks(false)}${sparks(true)}
            <g class="burst"></g>
          </svg>
        </div></div></div>
      </button>`;
  }

  // 2 звёздочки в покое, +2 со спутниками, когда думает
  const SPARKS = [
    { x: 60, y: 120, s: 6.5, extra: false, d: 0 },
    { x: 152, y: 190, s: 6.5, extra: false, d: 1.1 },
    { x: 22, y: 102, s: 8, extra: true, d: 0.3, kids: [[-8, -10, 2.6], [8, 8, 2]] },
    { x: 178, y: 96, s: 8, extra: true, d: 1.3, kids: [[9, -8, 2.6], [-7, 10, 2]] },
  ];
  const sparkSvg = (p) => `
    <g class="spark-wrap${p.extra ? ' extra' : ''}" transform="translate(${p.x} ${p.y})">
      <g class="pop"><g class="spark" style="--d:${p.d}s">
        <path d="${sparkle(p.s)}"/>
        ${(p.kids || []).map(([x, y, s], j) => `<g transform="translate(${x} ${y})"><path class="kid" style="--k:${j}" d="${sparkle(s)}"/></g>`).join('')}
      </g></g>
    </g>`;

  const CSS = `
  :host {
    --bota-size: 120px;
    --bota-accent: #3B6CFF;
    --bota-accent-think: #FF3B4E;
    --bota-line: #1F2350;
    --bota-body: #FFFFFF;
    --bota-shade: #A9B6D3;
    --bota-hat: #24307A;
    --bota-ornament: #E8B931;
    --bota-gold: #D4A63A;
    --bota-blush: #FF9DB0;
    --bota-think-time: 1.6s;
    --bota-star: var(--bota-accent);
    transition: --bota-star var(--bota-think-time) ease;
    display: inline-block;
    width: var(--bota-size);
    aspect-ratio: 200 / 252;
    position: relative;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
  }
  :host([variant^="oscar"]) {
    --bota-accent: #D9A93A;
    --bota-accent-think: #E5484D;
    --bota-line: #2B2118;
    --bota-shade: #CDBB95;
    --bota-gold: #C99A2E;
  }
  :host([state="thinking"]) { --bota-star: var(--bota-accent-think); }
  :host([floating]) {
    position: fixed;
    right: max(20px, env(safe-area-inset-right));
    bottom: max(20px, env(safe-area-inset-bottom));
    z-index: 2147483000;
    --bota-size: 88px;
  }
  button { all: unset; display: block; width: 100%; height: 100%; cursor: pointer; border-radius: 40%; }
  button:focus-visible { outline: 3px solid var(--bota-accent); outline-offset: 4px; }
  svg { width: 100%; height: 100%; overflow: visible; display: block; }

  .float  { animation: bob 3.8s ease-in-out infinite; }
  .breath { animation: breath 3.8s ease-in-out infinite; transform-box: view-box; transform-origin: 100px 228px; }
  .squash { transform-box: view-box; transform-origin: 100px 228px; }
  @keyframes bob { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-7px) } }
  @keyframes breath { 0%,100% { transform: scale(1,1) } 50% { transform: scale(1.014,.986) } }

  /* объём тела */
  .sil path { fill: var(--edge); stroke: var(--edge); stroke-width: 4; stroke-linejoin: round; }
  .sil { --edge: color-mix(in srgb, var(--bota-shade) 75%, var(--bota-line)); }
  .g-body0 { stop-color: color-mix(in srgb, var(--bota-body) 40%, #fff); }
  .g-body1 { stop-color: var(--bota-body); }
  .g-body2 { stop-color: color-mix(in srgb, var(--bota-body), var(--bota-shade) 55%); }
  .g-arm0 { stop-color: color-mix(in srgb, var(--bota-body) 30%, #fff); }
  .g-arm1 { stop-color: var(--bota-body); }
  .g-arm2 { stop-color: color-mix(in srgb, var(--bota-body), var(--bota-shade) 62%); }
  .crease { fill: none; stroke: color-mix(in srgb, var(--bota-shade) 70%, var(--bota-line)); stroke-width: 1.5; opacity: .55; }
  .occl { fill: none; stroke: var(--bota-shade); stroke-width: ${GAP * 2}; stroke-linejoin: round; opacity: .35; }
  .spec { fill: #fff; opacity: .8; }

  /* звезда-кристалл */
  .star-g { transform-box: view-box; transform-origin: ${C.x}px ${C.y}px; animation: starPulse 2.8s ease-in-out infinite; }
  .facet { stroke: none; }
  .f1 { fill: color-mix(in srgb, var(--bota-star), #fff 48%); }
  .f2 { fill: color-mix(in srgb, var(--bota-star), #fff 20%); }
  .f3 { fill: color-mix(in srgb, var(--bota-star), #000 8%); }
  .f4 { fill: color-mix(in srgb, var(--bota-star), #000 26%); }
  .star-edge { fill: none; stroke: color-mix(in srgb, var(--bota-star), #000 38%); stroke-width: 1.6; stroke-linejoin: round; }
  .star-glow { fill: var(--bota-star); opacity: .32; transition: opacity var(--bota-think-time) ease;
               transform-box: view-box; transform-origin: ${C.x}px ${C.y}px; }
  .glint { fill: #fff; transform-box: fill-box; transform-origin: center; animation: glint 2.8s ease-in-out infinite; }
  @keyframes starPulse { 0%,100% { transform: scale(1) } 50% { transform: scale(1.025) } }
  @keyframes glint { 0%,100% { opacity: .55; transform: scale(.7) } 50% { opacity: 1; transform: scale(1.1) } }

  /* звёздочки */
  .spark { fill: color-mix(in srgb, var(--bota-star), #fff 18%); animation: twinkle 2.4s ease-in-out infinite;
           animation-delay: var(--d); transform-box: fill-box; transform-origin: center; }
  .spark .kid { animation: twinkle 1.4s ease-in-out infinite; animation-delay: calc(var(--k) * .5s + .2s);
                transform-box: fill-box; transform-origin: center; }
  .pop { transform-box: fill-box; transform-origin: center; transition: transform .6s cubic-bezier(.3,1.6,.5,1); }
  .extra .pop { transform: scale(0); }
  @keyframes twinkle { 0%,100% { transform: scale(.55) rotate(0deg); opacity: .6 } 50% { transform: scale(1.1) rotate(45deg); opacity: 1 } }

  /* глаза */
  .sclera { fill: url(#EYEG); stroke: color-mix(in srgb, var(--bota-line) 45%, var(--bota-shade)); stroke-width: 1.3; }
  .g-eye0 { stop-color: #fff; } .g-eye1 { stop-color: color-mix(in srgb, #fff, var(--bota-shade) 30%); }
  .bar { fill: var(--bota-line); transform-box: fill-box; transform-origin: center;
         animation: shimmer var(--dur) ease-in-out var(--del) infinite alternate; }
  @keyframes shimmer { from { transform: scaleY(.95) } to { transform: scaleY(1.03) } }
  .pcore { fill: var(--bota-line); }
  .hl { fill: #fff; } .hl2 { opacity: .85; }
  .lid { transform-box: fill-box; transform-origin: center; transition: transform .45s cubic-bezier(.3,1.4,.5,1); }
  .look { transition: transform .25s ease-out; }
  .eyes.blink .lid { transform: scaleY(.07) !important; transition: transform .07s ease-in; }

  .blush { fill: var(--bota-blush); opacity: .38; filter: url(#SOFT); transition: opacity .6s ease; }
  .mouth { fill: none; stroke: var(--bota-line); stroke-width: 2.2; stroke-linecap: round; transition: opacity .3s ease; }
  .m-think, .m-talk { opacity: 0; }
  .m-talk { fill: var(--bota-line); stroke: none; transform-box: fill-box; transform-origin: center; }

  /* головные уборы */
  .tb-dome { fill: url(#HATG); stroke: color-mix(in srgb, var(--bota-hat), #000 45%); stroke-width: 1.4; }
  .g-hat0 { stop-color: color-mix(in srgb, var(--bota-hat), #fff 22%); }
  .g-hat1 { stop-color: var(--bota-hat); }
  .g-hat2 { stop-color: color-mix(in srgb, var(--bota-hat), #000 40%); }
  .tb-seam { fill: none; stroke: color-mix(in srgb, var(--bota-hat), #000 35%); stroke-width: 1.2; opacity: .7; }
  .tb-orn path { fill: none; stroke: url(#GOLDG); stroke-width: 1.8; stroke-linecap: round; }
  .tb-gem, .tb-bead { fill: url(#GOLDG); stroke: color-mix(in srgb, var(--bota-ornament), #000 35%); stroke-width: .8; }
  .tb-band { fill: url(#GOLDG); stroke: color-mix(in srgb, var(--bota-ornament), #000 35%); stroke-width: 1.1; }
  .tb-dot { fill: var(--bota-hat); }
  .g-gold0 { stop-color: color-mix(in srgb, var(--bota-ornament), #fff 45%); }
  .g-gold1 { stop-color: var(--bota-ornament); }
  .g-gold2 { stop-color: color-mix(in srgb, var(--bota-ornament), #000 25%); }
  .gloss { fill: #fff; opacity: .22; }
  .hat-hop { transform-box: view-box; transform-origin: 100px 40px; }

  .mr-strap { fill: none; stroke: color-mix(in srgb, var(--bota-line) 70%, var(--bota-shade)); stroke-width: 5; stroke-linecap: round; }
  .mr-strap-hl { fill: none; stroke: #fff; stroke-width: 1.2; opacity: .35; stroke-linecap: round; }
  .mr-rim { fill: url(#METALG); stroke: color-mix(in srgb, var(--bota-gold), #000 40%); stroke-width: 1.2; }
  .mr-glass { fill: url(#MIRRG); stroke: color-mix(in srgb, var(--bota-gold), #000 30%); stroke-width: 1; }
  .mr-hole { fill: var(--bota-line); }
  .mr-glint { fill: none; stroke: #fff; stroke-width: 1.8; stroke-linecap: round; }
  .g-met0 { stop-color: color-mix(in srgb, var(--bota-gold), #fff 50%); }
  .g-met1 { stop-color: var(--bota-gold); }
  .g-met2 { stop-color: color-mix(in srgb, var(--bota-gold), #000 30%); }

  .cp-fill { fill: url(#CAPG); stroke: color-mix(in srgb, var(--bota-shade) 75%, var(--bota-line)); stroke-width: 1.6; }
  .cp-fold { fill: color-mix(in srgb, #fff, var(--bota-shade) 35%); stroke: color-mix(in srgb, var(--bota-shade) 75%, var(--bota-line)); stroke-width: 1.4; stroke-linejoin: round; }
  .cp-plus { fill: none; stroke: var(--bota-gold); stroke-width: 3.6; stroke-linecap: round; }
  .g-cap0 { stop-color: #fff; } .g-cap1 { stop-color: color-mix(in srgb, #fff, var(--bota-shade) 30%); }

  .lens { fill: #fff; fill-opacity: .14; stroke: url(#METALG); stroke-width: 2.8; }
  .frame { fill: none; stroke: var(--bota-gold); stroke-width: 2.6; stroke-linecap: round; }
  .glare { fill: none; stroke: #fff; stroke-width: 2.2; stroke-linecap: round; opacity: .85; }

  /* ---------- думает ---------- */
  :host([state="thinking"]) .star-glow { opacity: .95; animation: glow 1.3s ease-in-out infinite; }
  :host([state="thinking"]) .star-g { animation-duration: 1.3s; }
  :host([state="thinking"]) .glint { animation-duration: 1.3s; }
  :host([state="thinking"]) .extra .pop { transform: scale(1); }
  :host([state="thinking"]) .lid { transform: scaleY(.52); }
  :host([state="thinking"]) .look { transform: translate(0, 1px) !important; }
  :host([state="thinking"]) .blush { opacity: .7; }
  :host([state="thinking"]) .m-idle { opacity: 0; }
  :host([state="thinking"]) .m-think { opacity: 1; }
  @keyframes glow { 0%,100% { transform: scale(1) } 50% { transform: scale(1.14) } }

  /* ---------- говорит: зрачки-волны двигаются ---------- */
  :host([state="talking"]) .bar { animation: talk .42s ease-in-out infinite alternate; animation-delay: calc(var(--i) * -.07s); }
  :host([state="talking"]) .m-idle { opacity: 0; }
  :host([state="talking"]) .m-talk { opacity: 1; animation: mtalk .32s ease-in-out infinite alternate; }
  @keyframes talk { from { transform: scaleY(.62) } to { transform: scaleY(1.04) } }
  @keyframes mtalk { from { transform: scale(1, .5) } to { transform: scale(1, 1.1) } }

  .burst path { fill: color-mix(in srgb, var(--bota-star), #fff 15%); }

  @media (prefers-reduced-motion: reduce) {
    .float, .breath, .star-g, .spark, .spark .kid, .bar, .star-glow, .glint, .m-talk { animation: none !important; }
  }
  `;

  const grad = (id, cls, kind, attrs, stops) =>
    `<${kind}Gradient id="${id}" ${attrs}>${stops.map((o, i) => `<stop offset="${o}" class="${cls}${i}"/>`).join('')}</${kind}Gradient>`;

  class BotaAssistant extends HTMLElement {
    static get observedAttributes() { return ['variant', 'hat', 'glasses', 'state', 'label', 'assets']; }

    constructor() {
      super();
      this._root = this.attachShadow({ mode: 'open' });
      this._onMove = this._onMove.bind(this);
    }

    connectedCallback() {
      if (!this.hasAttribute('state')) this.setAttribute('state', 'idle');
      this._render();
      window.addEventListener('pointermove', this._onMove, { passive: true });
      this._scheduleBlink();
    }

    disconnectedCallback() {
      window.removeEventListener('pointermove', this._onMove);
      clearTimeout(this._blinkT);
    }

    attributeChangedCallback(name, oldV, newV) {
      if (!this.isConnected || oldV === newV) return;
      if (name === 'state') { if (newV === 'thinking') this.burst(5); return; }
      this._render();
    }

    get state() { return this.getAttribute('state') || 'idle'; }
    set state(v) { this.setState(v); }
    setState(s) { this.setAttribute('state', s); }

    _cfg() {
      const p = PRESETS[this.getAttribute('variant')] || PRESETS.bota;
      const hat = this.getAttribute('hat'), gl = this.getAttribute('glasses');
      return {
        hat: hat in HATS ? hat : p.hat,
        glasses: gl in GLASSES ? gl : p.glasses,
        name: this.getAttribute('label') || p.name,
      };
    }

    _isOscar() { return /^oscar/.test(this.getAttribute('variant') || DEFAULT_VARIANT); }

    _render() {
      if (this._isOscar()) {
        const dir = this.getAttribute('assets');
        const base = (!dir && OSCAR_EMBED) ? OSCAR_EMBED : (() => {
          const d = (dir || SCRIPT_BASE + 'oscar/').replace(/"/g, '');
          return { body: d + 'body.webp', star: d + 'star.webp' };
        })();
        const name = (this.getAttribute('label') || 'Oscar').replace(/[<>&"]/g, '');
        this._root.innerHTML = oscarMarkup(base, name);
        this._svg = this._root.querySelector('svg.front');
        this._eyes = null;
        this._looks = [];
        this._root.querySelector('button').addEventListener('click', () => this._click());
        return;
      }
      const cfg = this._cfg();
      const name = cfg.name.replace(/[<>&"]/g, '');
      this._root.innerHTML = `
        <style>${CSS}</style>
        <button type="button" part="button" aria-label="${name}">
          <svg viewBox="0 -12 200 252" aria-hidden="true">
            <defs>
              ${grad('BODYG', 'g-body', 'radial', 'cx="0.38" cy="0.26" r="0.82"', [0, 0.5, 1])}
              ${grad('ARMG', 'g-arm', 'linear', 'x1="0.3" y1="0" x2="0.6" y2="1"', [0, 0.45, 1])}
              ${grad('EYEG', 'g-eye', 'linear', 'x1="0" y1="0" x2="0" y2="1"', [0.3, 1])}
              ${grad('HATG', 'g-hat', 'linear', 'x1="0.15" y1="0" x2="0.8" y2="1"', [0, 0.45, 1])}
              ${grad('GOLDG', 'g-gold', 'linear', 'x1="0" y1="0" x2="0.4" y2="1"', [0, 0.5, 1])}
              ${grad('METALG', 'g-met', 'linear', 'x1="0" y1="0" x2="1" y2="1"', [0, 0.5, 1])}
              ${grad('CAPG', 'g-cap', 'linear', 'x1="0" y1="0" x2="0.3" y2="1"', [0.2, 1])}
              <radialGradient id="MIRRG" cx="0.35" cy="0.3" r="0.8"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#BFC9D6"/></radialGradient>
              <filter id="GLOW" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="10"/></filter>
              <filter id="SOFT" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="1.6"/></filter>
              <filter id="BLUR4" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="4"/></filter>
              <clipPath id="BODYCLIP"><path d="${bodyPath}"/></clipPath>
            </defs>
            <g class="float"><g class="squash"><g class="breath">
              <g class="sparks-back">${SPARKS.filter((s) => s.extra).map(sparkSvg).join('')}</g>
              <g class="sil"><path d="${bodyPath}"/><path d="${armLPath}"/><path d="${armRPath}"/></g>
              <path d="${bodyPath}" fill="url(#BODYG)"/>
              <path class="occl" filter="url(#BLUR4)" clip-path="url(#BODYCLIP)" d="${starPath}"/>
              <path d="${armLPath}" fill="url(#ARMG)"/>
              <path d="${armRPath}" fill="url(#ARMG)"/>
              <g clip-path="url(#BODYCLIP)">
                <path class="crease" d="${armLPath}"/><path class="crease" d="${armRPath}"/>
              </g>
              <ellipse class="spec" filter="url(#BLUR4)" cx="78" cy="42" rx="14" ry="8" transform="rotate(-28 78 42)"/>
              <g class="hat-hop">${HATS[cfg.hat]}</g>
              <ellipse class="blush" cx="60" cy="90" rx="7.5" ry="4"/>
              <ellipse class="blush" cx="140" cy="90" rx="7.5" ry="4"/>
              <g class="eyes">${eye('l')}${eye('r')}</g>
              <path class="mouth m-idle" d="M96.5,78 Q100,81 103.5,78"/>
              <path class="mouth m-think" d="M95,80 q1.7,-2 3.3,0 t3.4,0 t3.3,0"/>
              <ellipse class="mouth m-talk" cx="100" cy="80" rx="2.6" ry="2.4"/>
              ${GLASSES[cfg.glasses]}
              <g class="sparks-front">${SPARKS.filter((s) => !s.extra).map(sparkSvg).join('')}</g>
              <g class="star-g">
                <path class="star-glow" filter="url(#GLOW)" d="${starPath}"/>
                ${facets}
                <path class="star-edge" d="${starPath}"/>
                <path class="glint" transform="translate(88 132)" d="${sparkle(5)}"/>
              </g>
              <g class="burst"></g>
            </g></g></g>
          </svg>
        </button>`;
      this._svg = this._root.querySelector('svg');
      this._eyes = this._root.querySelector('.eyes');
      this._looks = [...this._root.querySelectorAll('.look')];
      this._root.querySelector('button').addEventListener('click', () => this._click());
    }

    _reduced() { return matchMedia('(prefers-reduced-motion: reduce)').matches; }

    _click() {
      if (!this._reduced()) {
        this._root.querySelector('.squash').animate([
          { transform: 'scale(1,1)' },
          { transform: 'scale(1.12,.86)', offset: .25 },
          { transform: 'scale(.94,1.08)', offset: .55 },
          { transform: 'scale(1.03,.98)', offset: .8 },
          { transform: 'scale(1,1)' },
        ], { duration: 650, easing: 'ease-out' });
        if (this._isOscar()) {
          this._root.querySelector('.star-wrap').animate(
            [{ transform: 'scale(1) rotate(0deg)' }, { transform: 'scale(1.25) rotate(25deg)' }, { transform: 'scale(1) rotate(0deg)' }],
            { duration: 700, easing: 'cubic-bezier(.3,1.4,.5,1)' });
          this.burst(14);
          this.dispatchEvent(new CustomEvent('bota-click', { bubbles: true, composed: true }));
          return;
        }
        const hat = this._root.querySelector('.hat') && this._root.querySelector('.hat-hop');
        if (hat) hat.animate([
          { transform: 'translateY(0) rotate(0)' },
          { transform: 'translateY(-14px) rotate(-8deg)', offset: .4 },
          { transform: 'translateY(0) rotate(0)' },
        ], { duration: 600, easing: 'cubic-bezier(.3,1.5,.5,1)' });
        this._root.querySelector('.star-g').animate(
          [{ transform: 'scale(1)' }, { transform: 'scale(1.16) rotate(8deg)' }, { transform: 'scale(1)' }],
          { duration: 500, easing: 'ease-out' });
        this._root.querySelectorAll('.blush').forEach((b) => b.animate(
          [{ opacity: .38 }, { opacity: .9 }, { opacity: .38 }], { duration: 900 }));
        this.burst(10);
      }
      this.dispatchEvent(new CustomEvent('bota-click', { bubbles: true, composed: true }));
    }

    burst(n = 10) {
      if (!this._svg || this._reduced()) return;
      const g = this._root.querySelector('.burst');
      const big = this._isOscar(), K = big ? 3.6 : 1, O = big ? OS : C;
      for (let i = 0; i < n; i++) {
        const a = (Math.PI * 2 * i) / n + Math.random() * 0.5;
        const dist = (50 + Math.random() * 40) * K, s = (3 + Math.random() * 4) * K;
        const p = document.createElementNS(SVGNS, 'path');
        p.setAttribute('d', big ? puffy(s) : sparkle(s));
        g.appendChild(p);
        const dx = Math.cos(a) * dist, dy = Math.sin(a) * dist;
        p.animate([
          { transform: `translate(${O.x}px, ${O.y}px) scale(.2) rotate(0deg)`, opacity: 1 },
          { transform: `translate(${O.x + dx}px, ${O.y + dy}px) scale(1) rotate(90deg)`, opacity: 0 },
        ], { duration: 700 + Math.random() * 300, easing: 'cubic-bezier(.2,.8,.3,1)', fill: 'forwards' })
          .onfinish = () => p.remove();
      }
    }

    blink() {
      if (!this._eyes) return;
      this._eyes.classList.add('blink');
      setTimeout(() => this._eyes && this._eyes.classList.remove('blink'), 140);
    }

    _scheduleBlink() {
      clearTimeout(this._blinkT);
      this._blinkT = setTimeout(() => {
        if (this.state !== 'thinking') {
          this.blink();
          if (Math.random() < 0.25) setTimeout(() => this.blink(), 260);
        }
        this._scheduleBlink();
      }, 2600 + Math.random() * 3600);
    }

    _onMove(e) {
      if (!this._svg || this._reduced()) return;
      const r = this._svg.getBoundingClientRect();
      if (!r.width) return;
      const cx = r.left + r.width / 2, cy = r.top + r.height * 0.33;
      const dx = e.clientX - cx, dy = e.clientY - cy, d = Math.hypot(dx, dy) || 1;
      const k = Math.min(1, d / 300);
      const A = this._isOscar() ? 4 : 1;
      const tx = (dx / d) * 2.2 * k * A, ty = (dy / d) * 3.4 * k * A;
      this._looks.forEach((w) => { w.style.transform = `translate(${tx}px, ${ty}px)`; });
    }
  }

  customElements.define('bota-assistant', BotaAssistant);
})();
