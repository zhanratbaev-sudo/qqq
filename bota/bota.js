/*!
 * Bota — анимированный ИИ-маскот (веб-компонент <bota-assistant>)
 * Один файл, без зависимостей. Подключение:
 *   <script src="bota.js"></script>
 *   <bota-assistant variant="bota" floating></bota-assistant>
 *
 * Атрибуты:
 *   variant  = bota | oscar | oscar-doctor | oscar-cap   (пресет: аксессуары + цвета)
 *   hat      = tubeteika | mirror | cap | none           (переопределяет пресет)
 *   glasses  = round | none                              (переопределяет пресет)
 *   state    = idle | thinking | talking
 *   floating                                             (плавает в правом нижнем углу)
 *   label    = текст для скринридера (по умолчанию имя персонажа)
 *
 * JS API:
 *   el.setState('thinking' | 'talking' | 'idle')
 *   el.burst()      — вспышка искорок (как при клике)
 *   el.blink()      — моргнуть
 *
 * События:
 *   'bota-click'    — по персонажу кликнули (откройте здесь чат)
 *
 * Цвета — CSS-переменные (см. README.md).
 */
(() => {
  if (customElements.get('bota-assistant')) return;

  // ---------- Геометрия (viewBox 0 0 200 230) ----------
  const C = { x: 100, y: 150 };            // центр звезды
  const TIP = {
    top: { x: 100, y: 92 },
    right: { x: 148, y: 150 },
    bottom: { x: 100, y: 216 },
    left: { x: 52, y: 150 },
  };
  const CONCAVE = 0.3;   // насколько вогнуты грани звезды
  const GAP = 7;         // одинаковый зазор между звездой и руками
  const FLICK = 9;       // хвостик линии руки за кончиком звезды

  const ctrl = (a, b) => {
    // контрольная точка грани: от центра к углу габаритного прямоугольника
    const corner = { x: Math.abs(a.x - C.x) > Math.abs(b.x - C.x) ? a.x : b.x,
                     y: Math.abs(a.y - C.y) > Math.abs(b.y - C.y) ? a.y : b.y };
    return { x: C.x + (corner.x - C.x) * CONCAVE, y: C.y + (corner.y - C.y) * CONCAVE };
  };
  const q = (p0, p1, p2, t) => ({
    x: (1 - t) * (1 - t) * p0.x + 2 * (1 - t) * t * p1.x + t * t * p2.x,
    y: (1 - t) * (1 - t) * p0.y + 2 * (1 - t) * t * p1.y + t * t * p2.y,
  });
  const qd = (p0, p1, p2, t) => ({
    x: 2 * (1 - t) * (p1.x - p0.x) + 2 * t * (p2.x - p1.x),
    y: 2 * (1 - t) * (p1.y - p0.y) + 2 * t * (p2.y - p1.y),
  });
  const norm = (v) => { const l = Math.hypot(v.x, v.y) || 1; return { x: v.x / l, y: v.y / l }; };
  const f = (n) => Math.round(n * 100) / 100;

  const order = [TIP.top, TIP.right, TIP.bottom, TIP.left];
  const starPath = (() => {
    let d = `M${TIP.top.x},${TIP.top.y}`;
    for (let i = 0; i < 4; i++) {
      const a = order[i], b = order[(i + 1) % 4], c = ctrl(a, b);
      d += ` Q${f(c.x)},${f(c.y)} ${b.x},${b.y}`;
    }
    return d + 'Z';
  })();

  // Внутренняя линия рук: точное смещение нижних граней звезды на GAP
  function offsetEdge(a, b, n = 28) {
    const c = ctrl(a, b), pts = [];
    for (let i = 0; i <= n; i++) {
      const t = i / n, p = q(a, c, b, t), d = norm(qd(a, c, b, t));
      let nx = d.y, ny = -d.x;
      if (nx * (p.x - C.x) + ny * (p.y - C.y) < 0) { nx = -nx; ny = -ny; }
      pts.push({ x: p.x + nx * GAP, y: p.y + ny * GAP, nx, ny, tx: d.x, ty: d.y });
    }
    return pts;
  }
  const L = offsetEdge(TIP.left, TIP.bottom);
  const R = offsetEdge(TIP.bottom, TIP.right);
  // скругление вокруг нижнего кончика
  const a0 = Math.atan2(L[L.length - 1].ny, L[L.length - 1].nx);
  let a1 = Math.atan2(R[0].ny, R[0].nx);
  while (a1 > a0) a1 -= Math.PI * 2;
  const arc = [];
  for (let i = 1; i < 10; i++) {
    const a = a0 + (a1 - a0) * (i / 10);
    arc.push({ x: TIP.bottom.x + Math.cos(a) * GAP, y: TIP.bottom.y + Math.sin(a) * GAP });
  }
  const flickL = { x: L[0].x - L[0].tx * FLICK, y: L[0].y - L[0].ty * FLICK };
  const lastR = R[R.length - 1];
  const flickR = { x: lastR.x + lastR.tx * FLICK, y: lastR.y + lastR.ty * FLICK };
  const ringPts = [flickL, ...L, ...arc, ...R, flickR];
  const ringPath = 'M' + ringPts.map((p) => `${f(p.x)},${f(p.y)}`).join(' L');
  const notchPath = ringPath + ` L${TIP.right.x},${TIP.right.y} L${C.x},${C.y - 10} L${TIP.left.x},${TIP.left.y} Z`;

  // Тело-капля: голова сверху, снизу две доли — это и есть руки
  const bodyPath =
    'M100,24 C130,24 147,44 151,72 C155,100 166,121 177,140 C188,159 191,180 182,196 ' +
    'C171,214 138,213 122,196 L100,172 L78,196 C62,213 29,214 18,196 ' +
    'C9,180 12,159 23,140 C34,121 45,100 49,72 C53,44 70,24 100,24 Z';

  const sparkle = (s) => {
    // маленькая четырёхконечная звёздочка радиуса s с центром в 0,0
    const k = s * 0.22;
    return `M0,${-s} Q${k},${-k} ${s},0 Q${k},${k} 0,${s} Q${-k},${k} ${-s},0 Q${-k},${-k} 0,${-s}Z`;
  };

  // глаза-радиоволны: высоты полосок
  const BARS = [5, 11, 18, 24, 17, 9];
  const eye = (cx, cy, side) => {
    const bars = BARS.map((h, i) => {
      const x = -6.25 + i * 2.5;
      return `<rect class="bar" style="--i:${i}" x="${f(x - 0.85)}" y="${f(-h / 2)}" width="1.7" height="${h}" rx="0.85"/>`;
    }).join('');
    return `
      <g class="eye eye-${side}" transform="translate(${cx} ${cy})">
        <g class="eye-lid">
          <ellipse class="eye-white" rx="10" ry="16"/>
          <g class="eye-wave">${bars}</g>
        </g>
        <path class="squint" d="${side === 'l' ? 'M-17,-10 l-6,-3 M-18,-3 l-7,0' : 'M17,-10 l6,-3 M18,-3 l7,0'}"/>
      </g>`;
  };

  const HATS = {
    tubeteika: `
      <g class="hat hat-tubeteika" transform="translate(70 38) rotate(-20)">
        <path class="hat-fill" d="M-25,4 C-26,-26 26,-26 25,4 Q0,9 -25,4Z"/>
        <path class="hat-band" d="M-25,4 Q0,9 25,4 L25.6,-1.5 Q0,3.6 -25.6,-1.5Z"/>
        <g class="ornament">
          <path d="M0,-4 C-2,-10 -9,-12 -11,-7 C-12.5,-3.5 -8,-2 -7.5,-5.5"/>
          <path d="M0,-4 C2,-10 9,-12 11,-7 C12.5,-3.5 8,-2 7.5,-5.5"/>
          <path d="M0,-4 L0,-14"/>
          <path d="M-18,-6 q-2,-6 2,-9 M18,-6 q2,-6 -2,-9"/>
        </g>
      </g>`,
    mirror: `
      <g class="hat hat-mirror">
        <path class="mirror-strap" d="M53,58 C70,40 130,40 147,58"/>
        <g transform="translate(100 40)">
          <circle class="mirror-rim" r="13"/>
          <circle class="mirror-glass" r="9"/>
          <circle class="mirror-hole" r="2.2"/>
          <path class="mirror-shine" d="M-5,-4 a7,7 0 0 1 5,-3.5"/>
        </g>
      </g>`,
    cap: `
      <g class="hat hat-cap">
        <path class="cap-fill" d="M70,38 C72,14 128,14 130,38 Q100,44 70,38Z"/>
        <path class="cap-band" d="M70,38 Q100,44 130,38 L131,31 Q100,37 69,31Z"/>
        <path class="cap-plus" d="M100,18 v12 M94,24 h12"/>
      </g>`,
    none: '',
  };

  const GLASSES = {
    round: `
      <g class="glasses">
        <circle class="lens" cx="76" cy="77" r="18"/>
        <circle class="lens" cx="124" cy="77" r="18"/>
        <path class="frame" d="M94,73 Q100,67 106,73 M58,74 L49,71 M142,74 L151,71"/>
        <path class="lens-shine" d="M65,70 l6,-6 M113,70 l6,-6"/>
      </g>`,
    none: '',
  };

  const PRESETS = {
    bota: { hat: 'tubeteika', glasses: 'none', name: 'Bota' },
    oscar: { hat: 'none', glasses: 'round', name: 'Oscar' },
    'oscar-doctor': { hat: 'mirror', glasses: 'round', name: 'Oscar' },
    'oscar-cap': { hat: 'cap', glasses: 'round', name: 'Oscar' },
  };

  // позиции звёздочек: 2 в покое, +2 (с малышами-спутниками) когда думает
  const SPARKS = [
    { x: 62, y: 122, s: 7, cls: 'base', d: 0 },
    { x: 150, y: 184, s: 7, cls: 'base', d: 0.9 },
    { x: 26, y: 102, s: 8, cls: 'extra', d: 0.3, kids: [[-8, -9, 2.4], [7, 8, 2]] },
    { x: 176, y: 98, s: 8, cls: 'extra', d: 1.2, kids: [[9, -7, 2.4], [-6, 10, 2]] },
  ];
  const CSS = `
  :host {
    --bota-size: 120px;
    --bota-accent: #2F6BFF;
    --bota-accent-think: #FF3B3B;
    --bota-line: #23227A;
    --bota-body: #FFFFFF;
    --bota-hat: #23227A;
    --bota-ornament: #F2C230;
    --bota-glasses: var(--bota-line);
    --bota-think-time: 1.6s;
    display: inline-block;
    width: var(--bota-size);
    aspect-ratio: 200 / 230;
    position: relative;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
  }
  :host([variant^="oscar"]) {
    --bota-accent: #C9A227;
    --bota-accent-think: #E5484D;
    --bota-line: #3A2E1F;
    --bota-hat: #3A2E1F;
    --bota-ornament: #C9A227;
    --bota-glasses: #B8892B;
  }
  :host([floating]) {
    position: fixed;
    right: max(20px, env(safe-area-inset-right));
    bottom: max(20px, env(safe-area-inset-bottom));
    z-index: 2147483000;
    --bota-size: 88px;
  }
  button {
    all: unset; display: block; width: 100%; height: 100%; cursor: pointer;
    border-radius: 40%;
  }
  button:focus-visible { outline: 3px solid var(--bota-accent); outline-offset: 4px; }
  svg { width: 100%; height: 100%; overflow: visible; display: block; }

  .float  { animation: bob 3.6s ease-in-out infinite; transform-box: view-box; transform-origin: 100px 230px; }
  .breath { animation: breath 3.6s ease-in-out infinite; transform-box: view-box; transform-origin: 100px 215px; }
  .squash { transform-box: view-box; transform-origin: 100px 215px; }
  @keyframes bob { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-7px) } }
  @keyframes breath { 0%,100% { transform: scale(1,1) } 50% { transform: scale(1.012,0.988) } }

  .body { fill: var(--bota-body); stroke: var(--bota-line); stroke-width: 3.2; stroke-linejoin: round; }
  .ring { fill: none; stroke: var(--bota-line); stroke-width: 3.2; stroke-linecap: round; stroke-linejoin: round; }

  .star-g { transform-box: view-box; transform-origin: ${C.x}px ${C.y}px; animation: starPulse 2.8s ease-in-out infinite; }
  .star { fill: var(--bota-accent); stroke: var(--bota-line); stroke-width: 3.2; stroke-linejoin: round;
          transition: fill var(--bota-think-time) ease; }
  .star-glow { fill: var(--bota-accent);  opacity: .35;
               transition: fill var(--bota-think-time) ease, opacity var(--bota-think-time) ease;
               transform-box: view-box; transform-origin: ${C.x}px ${C.y}px; }
  .star-core { fill: #fff; opacity: .28; transform-box: view-box; transform-origin: ${C.x}px ${C.y}px;
               transform: scale(.38); transition: opacity var(--bota-think-time) ease; animation: core 2.8s ease-in-out infinite; }
  @keyframes starPulse { 0%,100% { transform: scale(1) } 50% { transform: scale(1.025) } }
  @keyframes core { 0%,100% { opacity: .18 } 50% { opacity: .38 } }

  .spark-wrap { transition: opacity .5s ease; }
  .spark { fill: var(--bota-accent); transition: fill var(--bota-think-time) ease;
           animation: twinkle 2.4s ease-in-out infinite; animation-delay: var(--d); transform-box: fill-box; transform-origin: center; }
  .spark .kid { animation: twinkle 1.4s ease-in-out infinite; animation-delay: calc(var(--k) * .5s + .2s);
                transform-box: fill-box; transform-origin: center; }
  .extra { opacity: 0; }
  .pop { transform-box: fill-box; transform-origin: center; transform: scale(0);
         transition: transform .6s cubic-bezier(.3,1.6,.5,1); }
  @keyframes twinkle {
    0%,100% { transform: scale(.55) rotate(0deg); opacity: .55 }
    50%     { transform: scale(1.1) rotate(45deg); opacity: 1 }
  }

  .eye-white { fill: var(--bota-body); stroke: var(--bota-line); stroke-width: 2.6; }
  .bar { fill: var(--bota-line); transform-box: fill-box; transform-origin: center; }
  .eye-lid { transform-box: fill-box; transform-origin: center; transition: transform .45s cubic-bezier(.3,1.4,.5,1); }
  .eye-wave { transition: transform .25s ease-out; }
  .squint { fill: none; stroke: var(--bota-line); stroke-width: 2; stroke-linecap: round; opacity: 0; transition: opacity .4s ease; }
  .eyes.blink .eye-lid { transform: scaleY(.08) !important; transition: transform .07s ease-in; }

  .mouth { fill: none; stroke: var(--bota-line); stroke-width: 2.6; stroke-linecap: round; transition: opacity .3s ease; }
  .mouth-think { opacity: 0; }

  .hat-fill, .cap-fill { fill: var(--bota-hat); stroke: var(--bota-line); stroke-width: 3; stroke-linejoin: round; }
  .hat-band { fill: var(--bota-ornament); stroke: var(--bota-line); stroke-width: 2; stroke-linejoin: round; }
  .ornament path { fill: none; stroke: var(--bota-ornament); stroke-width: 1.7; stroke-linecap: round; }
  .hat-hop { transform-box: view-box; transform-origin: 100px 40px; }
  .cap-fill { fill: var(--bota-body); }
  .cap-band { fill: var(--bota-accent); stroke: var(--bota-line); stroke-width: 2; stroke-linejoin: round; transition: fill var(--bota-think-time) ease; }
  .cap-plus { fill: none; stroke: var(--bota-accent); stroke-width: 3.2; stroke-linecap: round; transition: stroke var(--bota-think-time) ease; }
  .mirror-strap { fill: none; stroke: var(--bota-line); stroke-width: 4; stroke-linecap: round; }
  .mirror-rim { fill: var(--bota-glasses); stroke: var(--bota-line); stroke-width: 2.4; }
  .mirror-glass { fill: #EEF2F6; stroke: var(--bota-line); stroke-width: 1.4; }
  .mirror-hole { fill: var(--bota-line); }
  .mirror-shine { fill: none; stroke: #fff; stroke-width: 1.8; stroke-linecap: round; }

  .lens { fill: var(--bota-accent); fill-opacity: .06; stroke: var(--bota-glasses); stroke-width: 3.2; }
  .frame { fill: none; stroke: var(--bota-glasses); stroke-width: 3.2; stroke-linecap: round; }
  .lens-shine { fill: none; stroke: #fff; stroke-width: 2.4; stroke-linecap: round; opacity: .9; }

  /* ---------- думает ---------- */
  :host([state="thinking"]) .star,
  :host([state="thinking"]) .star-glow,
  :host([state="thinking"]) .spark { fill: var(--bota-accent-think); }
  :host([state="thinking"]) .cap-band { fill: var(--bota-accent-think); }
  :host([state="thinking"]) .cap-plus { stroke: var(--bota-accent-think); }
  :host([state="thinking"]) .star-glow { opacity: .9; animation: glow 1.3s ease-in-out infinite; }
  :host([state="thinking"]) .star-core { opacity: .55; animation: core-hot 1.3s ease-in-out infinite; }
  :host([state="thinking"]) .star-g { animation-duration: 1.3s; }
  :host([state="thinking"]) .extra { opacity: 1; }
  :host([state="thinking"]) .pop { transform: scale(1); }
  :host([state="thinking"]) .eye-lid { transform: scaleY(.5); }
  :host([state="thinking"]) .squint { opacity: 1; }
  :host([state="thinking"]) .mouth-idle { opacity: 0; }
  :host([state="thinking"]) .mouth-think { opacity: 1; }
  :host([state="thinking"]) .eye-wave { transform: translate(0,0) !important; }
  @keyframes glow { 0%,100% { transform: scale(1); } 50% { transform: scale(1.12); } }
  @keyframes core-hot { 0%,100% { opacity: .4 } 50% { opacity: .8 } }

  /* ---------- говорит: глаза-эквалайзер ---------- */
  :host([state="talking"]) .bar { animation: eq .5s ease-in-out infinite alternate; animation-delay: calc(var(--i) * -.13s); }
  @keyframes eq { from { transform: scaleY(.35) } to { transform: scaleY(1.05) } }

  /* частицы при клике */
  .burst path { fill: var(--bota-accent); }
  :host([state="thinking"]) .burst path { fill: var(--bota-accent-think); }

  @media (prefers-reduced-motion: reduce) {
    .float, .breath, .star-g, .star-core, .spark, .spark .kid, .bar, .star-glow { animation: none !important; }
  }
  `;

  class BotaAssistant extends HTMLElement {
    static get observedAttributes() { return ['variant', 'hat', 'glasses', 'state', 'label']; }

    constructor() {
      super();
      this._root = this.attachShadow({ mode: 'open' });
      this._onMove = this._onMove.bind(this);
      this._uid = 'b' + Math.random().toString(36).slice(2, 8);
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
      if (name === 'state') {
        if (newV === 'thinking') this.burst(4);
        return; // состояние работает через CSS, перерисовка не нужна
      }
      this._render();
    }

    get state() { return this.getAttribute('state') || 'idle'; }
    set state(v) { this.setState(v); }
    setState(s) { this.setAttribute('state', s); }

    _cfg() {
      const p = PRESETS[this.getAttribute('variant')] || PRESETS.bota;
      return {
        hat: HATS[this.getAttribute('hat')] !== undefined ? this.getAttribute('hat') : p.hat,
        glasses: GLASSES[this.getAttribute('glasses')] !== undefined ? this.getAttribute('glasses') : p.glasses,
        name: this.getAttribute('label') || p.name,
      };
    }

    _render() {
      const cfg = this._cfg(), u = this._uid;
      this._root.innerHTML = `
        <style>${CSS}</style>
        <button type="button" part="button" aria-label="${cfg.name}">
          <svg viewBox="0 0 200 230" aria-hidden="true">
            <defs>
              <filter id="${u}-glow" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur stdDeviation="9"/>
              </filter>
              <mask id="${u}-notch" maskUnits="userSpaceOnUse" x="-20" y="-20" width="240" height="270">
                <rect x="-20" y="-20" width="240" height="270" fill="#fff"/>
                <path d="${notchPath}" fill="#000"/>
              </mask>
              <clipPath id="${u}-inside"><path d="${bodyPath}"/></clipPath>
            </defs>
            <g class="float"><g class="squash"><g class="breath">
              <g class="sparks-back"></g>
              <path class="body" mask="url(#${u}-notch)" d="${bodyPath}"/>
              <path class="ring" clip-path="url(#${u}-inside)" d="${ringPath}"/>
              <g class="hat-hop">${HATS[cfg.hat]}</g>
              <g class="eyes">${eye(76, 77, 'l')}${eye(124, 77, 'r')}</g>
              <path class="mouth mouth-idle" d="M95,83 Q100,84.5 105,83"/>
              <path class="mouth mouth-think" d="M94,84 q2,-2.5 4,0 t4,0 t4,0"/>
              ${GLASSES[cfg.glasses]}
              <g class="sparks-front">${SPARKS.filter((s) => s.cls === 'base').map((p) => `
                <g class="spark-wrap base" transform="translate(${p.x} ${p.y})">
                  <g class="spark" style="--d:${p.d}s"><path d="${sparkle(p.s)}"/></g>
                </g>`).join('')}</g>
              <g class="star-g">
                <path class="star-glow" filter="url(#${u}-glow)" d="${starPath}"/>
                <path class="star" d="${starPath}"/>
                <path class="star-core" d="${starPath}"/>
              </g>
              <g class="burst"></g>
            </g></g></g>
          </svg>
        </button>`;
      // в «задний» слой оставляем только дополнительные звёздочки
      const back = this._root.querySelector('.sparks-back');
      back.innerHTML = SPARKS.filter((s) => s.cls === 'extra').map((p) => `
        <g class="spark-wrap extra" transform="translate(${p.x} ${p.y})">
          <g class="pop"><g class="spark" style="--d:${p.d}s">
            <path d="${sparkle(p.s)}"/>
            ${p.kids.map(([x, y, s], j) => `<g transform="translate(${x} ${y})"><path class="kid" style="--k:${j}" d="${sparkle(s)}"/></g>`).join('')}
          </g></g>
        </g>`).join('');

      this._svg = this._root.querySelector('svg');
      this._eyes = this._root.querySelector('.eyes');
      this._waves = [...this._root.querySelectorAll('.eye-wave')];
      this._root.querySelector('button').addEventListener('click', () => this._click());
    }

    _reduced() { return matchMedia('(prefers-reduced-motion: reduce)').matches; }

    _click() {
      if (!this._reduced()) {
        const sq = this._root.querySelector('.squash');
        sq.animate([
          { transform: 'scale(1,1)' },
          { transform: 'scale(1.12,.86)', offset: .25 },
          { transform: 'scale(.94,1.08)', offset: .55 },
          { transform: 'scale(1.03,.98)', offset: .8 },
          { transform: 'scale(1,1)' },
        ], { duration: 650, easing: 'ease-out' });
        const hat = this._root.querySelector('.hat') && this._root.querySelector('.hat-hop');
        if (hat) hat.animate([
          { transform: 'translateY(0) rotate(0)' },
          { transform: 'translateY(-14px) rotate(-8deg)', offset: .4 },
          { transform: 'translateY(0) rotate(0)' },
        ], { duration: 600, easing: 'cubic-bezier(.3,1.5,.5,1)' });
        const star = this._root.querySelector('.star-g');
        star.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.18) rotate(8deg)' }, { transform: 'scale(1)' }],
          { duration: 500, easing: 'ease-out' });
        this.burst(10);
      }
      this.dispatchEvent(new CustomEvent('bota-click', { bubbles: true, composed: true }));
    }

    burst(n = 10) {
      if (!this._svg || this._reduced()) return;
      const g = this._root.querySelector('.burst');
      for (let i = 0; i < n; i++) {
        const a = (Math.PI * 2 * i) / n + Math.random() * 0.5;
        const dist = 50 + Math.random() * 40, s = 3 + Math.random() * 4;
        const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        p.setAttribute('d', sparkle(s));
        p.setAttribute('transform', `translate(${C.x} ${C.y})`);
        g.appendChild(p);
        const dx = Math.cos(a) * dist, dy = Math.sin(a) * dist;
        p.animate([
          { transform: `translate(${C.x}px, ${C.y}px) scale(.2) rotate(0deg)`, opacity: 1 },
          { transform: `translate(${C.x + dx}px, ${C.y + dy}px) scale(1) rotate(90deg)`, opacity: 0 },
        ], { duration: 700 + Math.random() * 300, easing: 'cubic-bezier(.2,.8,.3,1)' }).onfinish = () => p.remove();
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
          if (Math.random() < 0.25) setTimeout(() => this.blink(), 260); // иногда двойное моргание
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
      const tx = (dx / d) * 2.6 * k, ty = (dy / d) * 4 * k;
      this._waves.forEach((w) => { w.style.transform = `translate(${tx}px, ${ty}px)`; });
    }
  }

  customElements.define('bota-assistant', BotaAssistant);
})();
