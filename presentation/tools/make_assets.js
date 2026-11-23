/**
 * Generates the cover illustration: alpenglow dawn over a Tien Shan lake (Kolsai), cut into an arch.
 * Programmatic artwork — no stock photo is used. Swap assets/kolsai-arch.png for a real photo if you have one
 * (keep the arch shape: 744 × 920 px at 1x, rounded top).
 *
 *   node tools/make_assets.js
 */
const sharp = require("sharp");
const path = require("path");

const OUT = path.join(__dirname, "..", "assets");
const S = 2; // render at 2x
const W = 744 * S, H = 920 * S;

function rng(seed) { return () => ((seed = (seed * 16807) % 2147483647) / 2147483647); }
const r = rng(42);

function ridge(base, amp, step, freq, phase, jitter) {
  const pts = [];
  for (let x = -60; x <= W + 60; x += step) {
    const y = base - amp * (0.5 + 0.5 * Math.sin(x * freq + phase)) * (0.65 + 0.35 * Math.sin(x * freq * 2.7 + phase * 1.3)) - r() * jitter;
    pts.push([Math.round(x), Math.round(y)]);
  }
  return pts;
}
const area = (pts, bottom) => `M${pts.map((p) => p.join(",")).join(" L")} L${W + 60},${bottom} L-60,${bottom} Z`;

const horizon = Math.round(H * 0.64);
const far = ridge(horizon - 250, 330, 22, 0.0042, 0.6, 26);
const mid = ridge(horizon - 70, 200, 18, 0.0061, 2.1, 18);
const near = ridge(horizon - 4, 90, 14, 0.009, 4.0, 10);

// snow on the highest peaks of the far ridge
let snow = "";
far.forEach(([x, y], i) => {
  if (y < horizon - 470 && i % 2 === 0) {
    const s = 38 + r() * 30;
    snow += `<path d="M${x - s},${y + s * 1.25} L${x},${y} L${x + s},${y + s * 1.25} L${x + s * 0.35},${y + s * 0.9} L${x},${y + s * 1.3} L${x - s * 0.4},${y + s * 0.95} Z" fill="#FFF4F5" opacity="0.92"/>`;
  }
});

// spruce forest on the shore
let trees = "";
for (let x = -10; x < W + 10; x += 9 + r() * 12) {
  const h = 60 + r() * 120, y = horizon + 6 + r() * 10, w = h * 0.26;
  const tone = r() > 0.5 ? "#5C4656" : "#4E3B4A";
  trees += `<path d="M${x},${y - h} L${x + w * 0.55},${y - h * 0.55} L${x + w * 0.35},${y - h * 0.55} L${x + w},${y} L${x - w},${y} L${x - w * 0.35},${y - h * 0.55} L${x - w * 0.55},${y - h * 0.55} Z" fill="${tone}"/>`;
}

// shimmering light on the lake
let shimmer = "";
for (let i = 0; i < 70; i++) {
  const y = horizon + 30 + Math.pow(r(), 1.4) * (H - horizon - 40);
  const w = 30 + r() * 260;
  shimmer += `<rect x="${Math.round(r() * W - w / 2)}" y="${Math.round(y)}" width="${Math.round(w)}" height="${2 + Math.round(r() * 3)}" rx="2" fill="#FFF1F3" opacity="${(0.12 + r() * 0.28).toFixed(2)}"/>`;
}

const rTop = W / 2, rb = 36 * S;
const archPath = `M0,${rTop} A${rTop},${rTop} 0 0 1 ${W},${rTop} L${W},${H - rb} Q${W},${H} ${W - rb},${H} L${rb},${H} Q0,${H} 0,${H - rb} Z`;

const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">
<defs>
  <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
    <stop offset="0" stop-color="#E9B9C8"/>
    <stop offset="0.45" stop-color="#F6D6DC"/>
    <stop offset="0.72" stop-color="#FCE9E3"/>
  </linearGradient>
  <radialGradient id="sun" cx="0.5" cy="0.5" r="0.5">
    <stop offset="0" stop-color="#FFF6EE" stop-opacity="1"/>
    <stop offset="0.35" stop-color="#FFE7DE" stop-opacity="0.75"/>
    <stop offset="1" stop-color="#FFE7DE" stop-opacity="0"/>
  </radialGradient>
  <linearGradient id="farG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#E7B4C3"/><stop offset="1" stop-color="#D79AAE"/></linearGradient>
  <linearGradient id="midG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#C98199"/><stop offset="1" stop-color="#A9677F"/></linearGradient>
  <linearGradient id="nearG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8E5470"/><stop offset="1" stop-color="#6E4058"/></linearGradient>
  <linearGradient id="lake" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#D8A2B4"/><stop offset="0.5" stop-color="#B07A93"/><stop offset="1" stop-color="#7C4F67"/></linearGradient>
  <linearGradient id="mist" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFF3F4" stop-opacity="0"/><stop offset="1" stop-color="#FFF3F4" stop-opacity="0.55"/></linearGradient>
  <clipPath id="arch"><path d="${archPath}"/></clipPath>
</defs>
<g clip-path="url(#arch)">
  <rect width="${W}" height="${H}" fill="url(#sky)"/>
  <circle cx="${W * 0.66}" cy="${horizon - 330}" r="${300 * S / 2}" fill="url(#sun)"/>
  <circle cx="${W * 0.66}" cy="${horizon - 330}" r="${46 * S / 2}" fill="#FFF8F2"/>
  <path d="${area(far, horizon + 10)}" fill="url(#farG)"/>${snow}
  <rect y="${horizon - 260}" width="${W}" height="200" fill="url(#mist)"/>
  <path d="${area(mid, horizon + 10)}" fill="url(#midG)"/>
  <rect y="${horizon - 90}" width="${W}" height="90" fill="url(#mist)" opacity="0.7"/>
  <path d="${area(near, horizon + 12)}" fill="url(#nearG)"/>
  ${trees}
  <rect y="${horizon + 10}" width="${W}" height="${H - horizon}" fill="url(#lake)"/>
  <g transform="translate(0,${2 * horizon + 20}) scale(1,-1)" opacity="0.28"><path d="${area(mid, horizon + 10)}" fill="#F3CFD9"/></g>
  <g transform="translate(0,${2 * horizon + 20}) scale(1,-1)" opacity="0.35"><path d="${area(near, horizon + 12)}" fill="#6E4058"/></g>
  ${shimmer}
</g>
</svg>`;

(async () => {
  await sharp(Buffer.from(svg)).png({ compressionLevel: 9 }).toFile(path.join(OUT, "kolsai-arch.png"));
  console.log("assets/kolsai-arch.png");
})();
