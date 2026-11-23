/**
 * Tax Burden Analysis and Pricing–Tax Proposals for a Small Guesthouse in Saty Village, Kazakhstan
 * Student: Nguyen Xuan Mai · Tourism Business / Taxation · 2026
 *
 * Generates  ./Saty_Guesthouse_Tax_Presentation.pptx  (16:9, 9 slides, native editable charts)
 *
 *   npm install && npm run build
 *
 * Design system (see DESIGN.md):
 *   forest green / cream / warm beige, muted-gold accent · Georgia headings · Arial body
 *   motif: gold numbered badges + soft white cards on cream; dark "sandwich" title & closing slides
 */
const pptxgen = require("pptxgenjs");
const sharp = require("sharp");
const React = require("react");
const ReactDOMServer = require("react-dom/server");
const fa = require("react-icons/fa");
const { applyTheme } = require("./lib/apply_theme.js");

const OUT = "Saty_Guesthouse_Tax_Presentation.pptx";
const STUDENT = "Nguyen Xuan Mai";

// ───────────────────────────── Design tokens ─────────────────────────────
const THEME = {
  name: "Saty Forest",
  headFontFace: "Georgia",
  bodyFontFace: "Arial",
  colors: {
    dk1: "1E2A24", // ink (near-black green)
    lt1: "FFFFFF", // card white
    dk2: "1F4D3A", // forest green (dominant)
    lt2: "F6F1E7", // cream (content background)
    accent1: "1F4D3A", // forest
    accent2: "B8963E", // muted gold
    accent3: "E4D6B8", // warm beige
    accent4: "6E8B74", // sage
    accent5: "9A4A36", // brick (negative effects)
    accent6: "4F5B54", // slate
    hlink: "1F4D3A",
    folHlink: "6E8B74",
  },
};
const HEX = THEME.colors;
const MUTED = "5E6A63"; // captions on cream (AA contrast)
const GOLD_TINT = "F2E8CF"; // callout tint
const ROW_ALT = "F3ECDD"; // table zebra
const HEAD = "+mj-lt"; // theme heading font (Georgia)
const BODY = "+mn-lt"; // theme body font (Arial)

const W = 13.333, H = 7.5, MX = 0.6, CW = W - 2 * MX;

// ───────────────────────────── Assets ─────────────────────────────
async function icon(Comp, color, size = 256) {
  const svg = ReactDOMServer.renderToStaticMarkup(React.createElement(Comp, { color: "#" + color, size: String(size) }));
  const buf = await sharp(Buffer.from(svg)).png().toBuffer();
  return "image/png;base64," + buf.toString("base64");
}

// Deterministic pseudo-random for the generated landscape.
function rng(seed) { return () => ((seed = (seed * 9301 + 49297) % 233280) / 233280); }

/** Stylised Tien Shan landscape (mountains, spruce forest, alpine lake) used as the title-slide photo stand-in. */
async function landscape(file) {
  const Wp = 1920, Hp = 1080, r = rng(7);
  const ridge = (base, amp, step, jitter) => {
    const pts = [];
    for (let x = -40; x <= Wp + 40; x += step) pts.push([x, base - amp * (0.45 + 0.55 * Math.abs(Math.sin(x / 260 + base))) - r() * jitter]);
    return pts;
  };
  const poly = (pts, bottom) => `M${pts.map((p) => p.join(",")).join(" L")} L${Wp + 40},${bottom} L-40,${bottom} Z`;
  const far = ridge(560, 260, 60, 60);
  const mid = ridge(650, 170, 45, 40);
  const near = ridge(740, 80, 30, 25);
  const snow = far.filter((p) => p[1] < 360).map(([x, y]) => `<path d="M${x - 34},${y + 46} L${x},${y} L${x + 34},${y + 46} L${x + 12},${y + 36} L${x},${y + 50} L${x - 14},${y + 38} Z" fill="#F4EEDF" opacity="0.85"/>`).join("");
  let trees = "";
  for (let x = -20; x < Wp + 20; x += 14 + r() * 10) {
    const h = 50 + r() * 70, y = 770 + r() * 18, w = h * 0.32;
    trees += `<path d="M${x},${y - h} L${x + w},${y} L${x - w},${y} Z" fill="#14302A"/>`;
  }
  let refl = "";
  for (let i = 0; i < 40; i++) refl += `<rect x="${r() * Wp}" y="${820 + r() * 240}" width="${60 + r() * 220}" height="2" fill="#E9DDBD" opacity="${0.08 + r() * 0.12}"/>`;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${Wp}" height="${Hp}" viewBox="0 0 ${Wp} ${Hp}">
  <defs>
    <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#173A31"/><stop offset="0.55" stop-color="#5E8273"/><stop offset="1" stop-color="#D8C79F"/></linearGradient>
    <linearGradient id="lake" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3C6E62"/><stop offset="1" stop-color="#0F2620"/></linearGradient>
    <radialGradient id="sun" cx="0.5" cy="0.5" r="0.5"><stop offset="0" stop-color="#F3E3B0" stop-opacity="0.95"/><stop offset="1" stop-color="#F3E3B0" stop-opacity="0"/></radialGradient>
  </defs>
  <rect width="${Wp}" height="${Hp}" fill="url(#sky)"/>
  <circle cx="1480" cy="430" r="220" fill="url(#sun)"/>
  <path d="${poly(far, 800)}" fill="#8EA597"/>${snow}
  <path d="${poly(mid, 800)}" fill="#4D7262"/>
  <path d="${poly(near, 800)}" fill="#2A4C40"/>
  ${trees}
  <rect y="790" width="${Wp}" height="${Hp - 790}" fill="url(#lake)"/>
  <g transform="translate(0,1580) scale(1,-1)" opacity="0.22"><path d="${poly(mid, 800)}" fill="#8EA597"/></g>
  ${refl}
</svg>`;
  await sharp(Buffer.from(svg)).jpeg({ quality: 88 }).toFile(file);
  return file;
}

// ───────────────────────────── Build ─────────────────────────────
(async () => {
  const pres = new pptxgen();
  pres.layout = "LAYOUT_WIDE";
  pres.title = "Tax Burden Analysis and Pricing–Tax Proposals for a Small Guesthouse in Saty Village, Kazakhstan";
  pres.subject = "Tourism Business / Taxation";
  pres.author = STUDENT;
  pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
  const C = pres.SchemeColor;

  const I = {
    hotel: await icon(fa.FaHotel, HEX.dk2),
    target: await icon(fa.FaBullseye, HEX.dk2),
    scale: await icon(fa.FaBalanceScale, HEX.dk2),
    info: await icon(fa.FaInfoCircle, HEX.dk2),
    shield: await icon(fa.FaShieldAlt, HEX.lt2),
    leafG: await icon(fa.FaLeaf, HEX.dk2),
    calc: await icon(fa.FaCalculator, HEX.dk2),
    mountain: await icon(fa.FaMountain, HEX.lt2),
    plane: await icon(fa.FaPlaneDeparture, HEX.lt2),
    city: await icon(fa.FaLandmark, HEX.lt2),
    coins: await icon(fa.FaCoins, HEX.dk2),
    users: await icon(fa.FaUsers, HEX.dk2),
    eye: await icon(fa.FaEye, HEX.dk2),
    chart: await icon(fa.FaChartLine, HEX.dk2),
    house: await icon(fa.FaHome, HEX.lt2),
    flag: await icon(fa.FaFlag, HEX.lt2),
    tag: await icon(fa.FaTag, HEX.dk2),
    road: await icon(fa.FaRoad, HEX.dk2),
    tree: await icon(fa.FaTree, HEX.dk2),
    quote: await icon(fa.FaQuoteLeft, HEX.accent2),
  };
  const bg = await landscape("assets/saty-landscape.jpg");

  // ── Layouts ──
  const footer = (color) => ({
    text: { text: `${STUDENT}  ·  Tax Burden Analysis — Small Guesthouse, Saty Village`, options: { x: MX, y: 7.0, w: 9, h: 0.3, fontSize: 10, color, margin: 0, valign: "middle" } },
  });
  pres.defineSlideMaster({
    title: "TITLE_DARK",
    background: { color: "13261F" },
    objects: [],
  });
  pres.defineSlideMaster({
    title: "CONTENT",
    background: { color: HEX.lt2 },
    objects: [
      { placeholder: { options: { name: "title", type: "title", x: MX, y: 0.38, w: CW, h: 0.7, fontSize: 26, bold: true, color: C.text2, align: "left", valign: "middle", margin: 0 }, text: "" } },
      { placeholder: { options: { name: "sub", type: "body", x: MX, y: 1.06, w: CW, h: 0.4, fontSize: 15, italic: true, color: C.accent6, valign: "middle", margin: 0 }, text: "" } },
      footer(MUTED),
    ],
    slideNumber: { x: W - MX - 0.6, y: 7.0, w: 0.6, h: 0.3, fontSize: 10, color: MUTED, align: "right" },
  });
  pres.defineSlideMaster({
    title: "CLOSING_DARK",
    background: { color: "13261F" },
    objects: [footer("C9BFA6")],
    slideNumber: { x: W - MX - 0.6, y: 7.0, w: 0.6, h: 0.3, fontSize: 10, color: "C9BFA6", align: "right" },
  });

  // ── Helpers ──
  const shadow = () => ({ type: "outer", color: "1E2A24", opacity: 0.12, blur: 6, offset: 2, angle: 90 });
  const card = (s, x, y, w, h, opts = {}) =>
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w, h, rectRadius: 0.08, fill: { color: opts.fill || C.background1 }, line: { color: opts.line || HEX.accent3, width: opts.lineW ?? 0.75 }, shadow: opts.noShadow ? undefined : shadow(), objectName: opts.name || "Card" });
  const badge = (s, x, y, n, d = 0.4, dark = false) => {
    s.addShape(pres.shapes.OVAL, { x, y, w: d, h: d, fill: { color: C.accent2 }, line: { type: "none" }, objectName: `Badge ${n}` });
    s.addText(String(n), { x, y, w: d, h: d, align: "center", valign: "middle", fontSize: d > 0.45 ? 16 : 13, bold: true, color: dark ? "13261F" : C.text2, fontFace: HEAD, margin: 0, isTextBox: true });
  };
  const iconDot = (s, x, y, data, d = 0.42, fill = HEX.accent3) => {
    s.addShape(pres.shapes.OVAL, { x, y, w: d, h: d, fill: { color: fill }, line: { type: "none" }, objectName: "Icon circle" });
    s.addImage({ data, x: x + d * 0.24, y: y + d * 0.24, w: d * 0.52, h: d * 0.52 });
  };
  const header = (s, x, y, w, text, ic) => {
    let tx = x;
    if (ic) { iconDot(s, x, y - 0.02, ic, 0.38); tx = x + 0.5; }
    s.addText(text, { x: tx, y, w: w - (tx - x), h: 0.34, fontSize: 16, bold: true, color: C.text2, margin: 0, valign: "middle", isTextBox: true });
  };
  const sources = (s, text, y = 6.62) => s.addText(text, { x: MX, y, w: CW, h: 0.28, fontSize: 10, italic: true, color: MUTED, margin: 0, valign: "middle", isTextBox: true });
  const tableStyle = (rows, colW, fontSize = 12, rowH = 0.36) => {
    const out = rows.map((r, i) =>
      r.map((cell, j) => ({
        text: cell,
        options: i === 0
          ? { bold: true, color: C.background2, fill: { color: C.text2 }, fontSize }
          : { color: C.text1, fill: { color: i % 2 ? HEX.lt1 : ROW_ALT }, fontSize, bold: j === 0 && false },
      })),
    );
    return { out, opts: { colW, rowH, border: { type: "solid", pt: 0.5, color: HEX.accent3 }, valign: "middle", margin: [0.03, 0.1, 0.03, 0.1] } };
  };
  const addTable = (s, rows, x, y, colW, fontSize, rowH, extra = {}) => {
    const t = tableStyle(rows, colW, fontSize, rowH);
    if (extra.boldRow) t.out[extra.boldRow].forEach((c) => (c.options.bold = true));
    s.addTable(t.out, { x, y, w: colW.reduce((a, b) => a + b, 0), ...t.opts, objectName: extra.name || "Table" });
  };
  const chartText = { catAxisLabelFontFace: BODY, valAxisLabelFontFace: BODY, dataLabelFontFace: BODY, titleFontFace: BODY, legendFontFace: BODY };

  // ════════════════════ Slide 1 · Title ════════════════════
  pres.addSection({ title: "Introduction" });
  {
    const s = pres.addSlide({ masterName: "TITLE_DARK", sectionTitle: "Introduction" });
    s.addImage({ path: bg, x: 0, y: 0, w: W, h: H, altText: "Stylised landscape of the Tien Shan mountains and an alpine lake near Saty village" });
    s.addShape(pres.shapes.RECTANGLE, { x: 0, y: 0, w: W, h: H, fill: { color: "0E2019", transparency: 30 }, line: { type: "none" }, objectName: "Dark overlay" });
    s.addShape(pres.shapes.RECTANGLE, { x: 0, y: 0, w: 9.7, h: H, fill: { color: "0E2019", transparency: 35 }, line: { type: "none" }, objectName: "Text panel overlay" });
    s.addText("ANALYTICAL REPORT  ·  TOURISM BUSINESS / TAXATION", { x: 0.8, y: 0.8, w: 8.2, h: 0.3, fontSize: 12, bold: true, color: HEX.accent2, charSpacing: 3, margin: 0, isTextBox: true });
    s.addText("Tax Burden Analysis and Pricing–Tax Proposals for a Small Guesthouse in Kazakhstan", { x: 0.8, y: 1.2, w: 8.7, h: 2.5, fontFace: HEAD, fontSize: 36, bold: true, color: HEX.lt2, valign: "top", margin: 0, isTextBox: true });
    s.addText("Comparison with International Tourism Taxes", { x: 0.8, y: 3.85, w: 8.7, h: 0.5, fontFace: HEAD, fontSize: 20, italic: true, color: HEX.accent2, margin: 0, isTextBox: true });
    const meta = [
      ["Selected destination", "Saty Village, Almaty Region"],
      ["Enterprise", "Small guesthouse (10 rooms)"],
      ["Course", "Tourism Business / Taxation"],
      ["Student", STUDENT],
      ["Academic year", "2026"],
    ];
    meta.forEach(([k, v], i) => {
      const col = i % 3, row = Math.floor(i / 3);
      const x = 0.8 + col * 2.85, y = 4.75 + row * 0.95;
      s.addText([
        { text: k.toUpperCase(), options: { fontSize: 10, bold: true, color: HEX.accent2, charSpacing: 2, breakLine: true } },
        { text: v, options: { fontSize: 15, color: HEX.lt2, bold: k === "Student" } },
      ], { x, y, w: 2.75, h: 0.8, valign: "top", margin: 0, paraSpaceAfter: 4, isTextBox: true });
    });
    s.addText("Illustration: Kolsai Lakes area, Northern Tien Shan", { x: 9.6, y: 7.0, w: 3.13, h: 0.3, fontSize: 10, italic: true, color: "E4D6B8", align: "right", margin: 0, isTextBox: true });
    s.addNotes("Introduce the topic and the main argument: a small rural guesthouse can benefit from a simplified tax regime, but the choice depends on its profit margin, eligibility and operating costs. Tourism taxation should generate revenue without making rural destinations less competitive. (≈30 seconds)");
  }

  // ════════════════════ Slide 2 · Enterprise profile ════════════════════
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Introduction" });
    s.addText("Enterprise Profile and Research Objectives", { placeholder: "title" });
    s.addText("Saty village is the main accommodation base for tourists visiting Kolsai Lakes and Kaindy Lake", { placeholder: "sub" });

    header(s, MX, 1.68, 5.7, "Business profile (assumptions)", I.hotel);
    addTable(s, [
      ["Indicator", "Assumption"],
      ["Business type", "Small guesthouse"],
      ["Location", "Saty village, Almaty Region"],
      ["Number of rooms", "10"],
      ["Average room price", "25,000 KZT / night"],
      ["Average occupancy", "45%"],
      ["Operating period", "365 days"],
      ["Target customers", "Domestic and international tourists"],
    ], MX, 2.15, [2.2, 3.5], 12, 0.3, { name: "Business profile table" });

    // Schematic location map (editable shapes)
    const my = 4.72, mh = 1.75;
    card(s, MX, my, 5.7, mh, { name: "Map card" });
    s.addText("Location — schematic, not to scale", { x: MX + 0.15, y: my + 0.08, w: 4, h: 0.26, fontSize: 10, italic: true, color: MUTED, margin: 0, isTextBox: true });
    [[2.55, 0.9], [3.1, 0.7], [3.75, 0.95], [4.35, 0.75]].forEach(([x, hgt], i) =>
      s.addShape(pres.shapes.ISOSCELES_TRIANGLE, { x: MX + x, y: my + mh - 0.12 - hgt, w: 0.9, h: hgt, fill: { color: HEX.accent3, transparency: 35 }, line: { type: "none" }, objectName: `Mountain ${i + 1}` }));
    const pt = (x, y) => ({ x: MX + x, y: my + y });
    const A = pt(0.55, 0.85), S = pt(3.55, 0.7), K1 = pt(3.1, 1.38), K2 = pt(4.2, 1.38);
    s.addShape(pres.shapes.LINE, { x: A.x + 0.09, y: S.y + 0.09, w: S.x - A.x, h: A.y - S.y, flipV: true, line: { color: HEX.dk2, width: 2, dashType: "dash" }, objectName: "Road Almaty–Saty" });
    s.addShape(pres.shapes.LINE, { x: K1.x + 0.07, y: S.y + 0.09, w: S.x - K1.x + 0.02, h: K1.y - S.y, flipH: true, line: { color: HEX.accent4, width: 1.25 }, objectName: "Road Saty–Kolsai" });
    s.addShape(pres.shapes.LINE, { x: S.x + 0.09, y: S.y + 0.09, w: K2.x - S.x, h: K2.y - S.y, line: { color: HEX.accent4, width: 1.25 }, objectName: "Road Saty–Kaindy" });
    const dot = (p, d, fill, label, lx, ly, lw, bold) => {
      s.addShape(pres.shapes.OVAL, { x: p.x, y: p.y, w: d, h: d, fill: { color: fill }, line: { color: HEX.lt1, width: 1.5 }, objectName: `Pin ${label}` });
      s.addText(label, { x: p.x + lx, y: p.y + ly, w: lw, h: 0.26, fontSize: 11, bold, color: C.text1, margin: 0, isTextBox: true });
    };
    dot(A, 0.18, HEX.accent6, "Almaty", -0.25, 0.22, 1.0, false);
    dot(S, 0.2, HEX.accent2, "Saty village", 0.28, -0.04, 1.4, true);
    dot(K1, 0.15, HEX.accent4, "Kolsai Lakes", -1.15, -0.04, 1.1, false);
    dot(K2, 0.15, HEX.accent4, "Kaindy Lake", 0.22, -0.04, 1.0, false);
    s.addText("≈ 300 km east of Almaty", { x: MX + 1.25, y: my + 0.42, w: 2.2, h: 0.26, fontSize: 10, italic: true, color: MUTED, margin: 0, isTextBox: true });

    // Objectives
    const rx = 6.85, rw = W - MX - rx;
    header(s, rx, 1.68, rw, "Research objectives", I.target);
    const objectives = [
      "Identify the applicable taxes under Kazakhstan’s 2026 Tax Code.",
      "Compare the general and simplified taxation regimes.",
      "Evaluate how VAT influences accommodation prices and tourist demand.",
      "Examine international tourism taxation practices.",
      "Develop recommendations for the enterprise and Kazakhstan’s tourism sector.",
    ];
    objectives.forEach((t, i) => {
      const y = 2.17 + i * 0.5;
      badge(s, rx, y + 0.02, i + 1, 0.36);
      s.addText(t, { x: rx + 0.5, y, w: rw - 0.5, h: 0.42, fontSize: 13, color: C.text1, valign: "middle", margin: 0, isTextBox: true });
    });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: rx, y: 4.72, w: rw, h: 1.75, rectRadius: 0.08, fill: { color: C.text2 }, line: { type: "none" }, shadow: shadow(), objectName: "Research question" });
    s.addText([
      { text: "KEY RESEARCH QUESTION", options: { fontSize: 11, bold: true, color: HEX.accent2, charSpacing: 2, breakLine: true } },
      { text: "How can a small rural guesthouse maintain competitive prices while fulfilling its tax obligations?", options: { fontFace: HEAD, fontSize: 18, italic: true, color: HEX.lt2 } },
    ], { x: rx + 0.3, y: 4.82, w: rw - 0.6, h: 1.55, valign: "middle", margin: 0, paraSpaceAfter: 6, isTextBox: true });
    s.addNotes("Present the enterprise: a 10-room guesthouse in Saty, the gateway to Kolsai and Kaindy lakes. All operating figures are assumptions for an illustrative model. Read the key research question aloud — the rest of the deck answers it. (≈40 seconds)");
  }

  // ════════════════════ Slide 3 · Legal framework ════════════════════
  pres.addSection({ title: "Analysis" });
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Analysis" });
    s.addText("Legal Framework: Tax Obligations in Kazakhstan (2026)", { placeholder: "title" });
    s.addText("Legal basis: Tax Code of the Republic of Kazakhstan No. 214-VIII of 18 July 2025, in force from 1 January 2026", { placeholder: "sub" });

    header(s, MX, 1.68, 6.7, "Main tax obligations", I.scale);
    addTable(s, [
      ["Tax / payment", "General rate or rule"],
      ["Corporate Income Tax (CIT)", "20% for legal entities"],
      ["Individual Income Tax (PIT)", "Generally 10%; higher 15% bracket"],
      ["Value Added Tax (VAT)", "16%"],
      ["Social tax", "6%"],
      ["Pension contributions", "Applicable statutory rates"],
      ["Social contributions", "Applicable statutory rates"],
      ["Property tax", "Depends on taxable property"],
      ["Land tax", "Depends on land characteristics"],
      ["Transport tax", "Depends on registered vehicles"],
    ], MX, 2.15, [3.0, 3.7], 12, 0.42, { name: "Tax obligations table" });

    const rx = 7.65, rw = W - MX - rx, gw = (rw - 0.25) / 2, gh = 1.5;
    header(s, rx, 1.68, rw, "Important regulatory changes", I.info);
    const changes = [
      ["12% → 16%", "Standard VAT rate increased"],
      ["10,000 MCI", "Mandatory VAT registration threshold (≈ 43.25 M KZT in 2026)"],
      ["4%", "Simplified declaration rate on revenue — subject to eligibility"],
      ["Exempt", "Simplified regime: no VAT and no social tax, under applicable conditions"],
    ];
    changes.forEach(([big, txt], i) => {
      const x = rx + (i % 2) * (gw + 0.25), y = 2.15 + Math.floor(i / 2) * (gh + 0.22);
      card(s, x, y, gw, gh, { name: `Change ${i + 1}` });
      s.addText(big, { x: x + 0.18, y: y + 0.14, w: gw - 0.36, h: 0.5, fontFace: HEAD, fontSize: 20, bold: true, color: C.text2, margin: 0, valign: "middle", isTextBox: true });
      s.addText(txt, { x: x + 0.18, y: y + 0.66, w: gw - 0.36, h: 0.74, fontSize: 12, color: C.text1, margin: 0, valign: "top", isTextBox: true });
    });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: rx, y: 5.6, w: rw, h: 0.9, rectRadius: 0.08, fill: { color: GOLD_TINT }, line: { type: "none" }, objectName: "Legal form callout" });
    s.addText([
      { text: "Legal form matters: ", options: { bold: true } },
      { text: "an individual entrepreneur (IE) pays PIT, a legal entity pays CIT. Our model assumes an IE, so the general-regime comparison uses PIT." },
    ], { x: rx + 0.2, y: 5.62, w: rw - 0.4, h: 0.86, fontSize: 12, color: C.text1, margin: 0, valign: "middle", isTextBox: true });
    sources(s, "Sources: Tax Code of the Republic of Kazakhstan (2025), Articles 503, 557 and 726; State Revenue Committee of Kazakhstan.");
    s.addNotes("Explain the legal basis — the new Tax Code effective 1 January 2026. Highlight the four changes on the right: VAT up to 16%, the lower 10,000 MCI registration threshold, the 4% simplified regime and its VAT/social-tax exemption. Stress that legal form decides PIT vs CIT; our guesthouse is modelled as an individual entrepreneur. (≈45 seconds)");
  }

  // ════════════════════ Slide 4 · Financial model ════════════════════
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Analysis" });
    s.addText("Financial Model and Tax Burden Calculation", { placeholder: "title" });
    s.addText("At the assumed 29.4% profit margin, the general regime produces the lower income tax", { placeholder: "sub" });

    const stats = [
      ["41,062,500", "KZT · estimated annual revenue"],
      ["29,000,000", "KZT · operating expenses"],
      ["12,062,500", "KZT · estimated profit"],
      ["29.4%", "profit margin (profit ÷ revenue)"],
    ];
    const sw = (CW - 3 * 0.25) / 4;
    stats.forEach(([n, l], i) => {
      const x = MX + i * (sw + 0.25);
      card(s, x, 1.68, sw, 1.05, { name: `Stat ${i + 1}`, fill: i === 3 ? C.text2 : undefined, line: i === 3 ? HEX.dk2 : undefined });
      s.addText(n, { x: x + 0.2, y: 1.76, w: sw - 0.4, h: 0.5, fontFace: HEAD, fontSize: 24, bold: true, color: i === 3 ? HEX.accent2 : C.text2, margin: 0, valign: "middle", isTextBox: true });
      s.addText(l, { x: x + 0.2, y: 2.26, w: sw - 0.4, h: 0.36, fontSize: 11, color: i === 3 ? HEX.lt2 : MUTED, margin: 0, valign: "middle", isTextBox: true });
    });
    iconDot(s, MX, 2.95, I.calc, 0.38);
    s.addText([
      { text: "Revenue  =  ", options: { bold: true, color: C.text2 } },
      { text: "10 rooms × 365 days × 45% occupancy × 25,000 KZT  =  " },
      { text: "41,062,500 KZT", options: { bold: true, color: C.text2 } },
    ], { x: MX + 0.5, y: 2.95, w: CW - 0.5, h: 0.38, fontSize: 14, color: C.text1, margin: 0, valign: "middle", isTextBox: true });

    addTable(s, [
      ["Indicator", "General regime", "Simplified regime"],
      ["Tax base", "Taxable profit", "Revenue"],
      ["Assumed tax rate", "10% PIT", "4%"],
      ["Income tax", "1,206,250 KZT", "1,642,500 KZT"],
      ["Tax / revenue", "2.94%", "4.00%"],
      ["Tax / profit", "10.00%", "13.62%"],
    ], MX, 3.62, [2.1, 2.05, 2.05], 13, 0.4, { name: "Regime comparison table", boldRow: 3 });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: MX, y: 6.12, w: 6.2, h: 0.42, rectRadius: 0.06, fill: { color: GOLD_TINT }, line: { type: "none" }, objectName: "Break-even note" });
    s.addText([
      { text: "Break-even margin = 4% ÷ 10% = 40%. ", options: { bold: true } },
      { text: "Simplified is cheaper only above it." },
    ], { x: MX + 0.15, y: 6.12, w: 5.9, h: 0.42, fontSize: 12, color: C.text1, margin: 0, valign: "middle", isTextBox: true });

    const cx = 7.15, cw = W - MX - cx;
    card(s, cx, 3.62, cw, 2.92, { name: "Chart card" });
    s.addChart(pres.charts.BAR, [{ name: "Income tax, KZT", labels: ["Simplified (4% of revenue): 1,642,500", "General (10% of profit): 1,206,250"], values: [1642500, 1206250] }], {
      x: cx + 0.15, y: 3.7, w: cw - 0.3, h: 1.85, barDir: "bar", barGapWidthPct: 55,
      chartColors: [HEX.accent2, HEX.dk2], showLegend: false,
      showTitle: true, title: "Annual income tax, KZT", titleFontSize: 12, titleColor: HEX.dk1,
      showValue: false, dataLabelFontSize: 11, dataLabelColor: HEX.dk1, dataLabelFontBold: true,
      catAxisLabelColor: HEX.dk1, catAxisLabelFontSize: 11, valAxisHidden: true, valAxisMaxVal: 1800000, valAxisMinVal: 0,
      valGridLine: { style: "none" }, catGridLine: { style: "none" }, catAxisLineShow: false, ...chartText,
      objectName: "Income tax chart",
    });
    s.addText([
      { text: "Initial finding: ", options: { bold: true, color: C.text2 } },
      { text: "the general regime saves 436,250 KZT a year, but the simplified regime may offer lower administrative costs and exemptions from certain payments." },
    ], { x: cx + 0.25, y: 5.58, w: cw - 0.5, h: 0.88, fontSize: 12, color: C.text1, margin: 0, valign: "middle", isTextBox: true });
    sources(s, "Note: illustrative income-tax calculations only — payroll taxes, social payments, other obligations and VAT are excluded. Sources: Tax Code of Kazakhstan (2025); State Revenue Committee (2026).", 6.66);
    s.addNotes("KEY SLIDE — spend time here. Walk through the revenue formula, then compare: general regime 10% PIT on profit = 1,206,250 KZT; simplified 4% on revenue = 1,642,500 KZT. Tax/profit 10% vs 13.62%. The break-even margin is 40% (4% ÷ 10%): below it the general regime is cheaper, above it the simplified regime is cheaper. Caveat: these are income taxes only; eligibility for the simplified regime (permitted activity list and other conditions) must be checked, and the full Excel model adds payroll and social payments. (≈1.5 minutes)");
  }

  // ════════════════════ Slide 5 · VAT ════════════════════
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Analysis" });
    s.addText("VAT and Its Impact on Accommodation Prices", { placeholder: "title" });
    s.addText("If the guesthouse is VAT-registered, the 12% → 16% increase raises the room price by 3.57%", { placeholder: "sub" });

    const lw = 5.75;
    card(s, MX, 1.68, lw, 3.55, { name: "VAT chart card" });
    s.addChart(pres.charts.BAR, [
      { name: "Base room price", labels: ["VAT 12% → 28,000 KZT", "VAT 16% → 29,000 KZT"], values: [25000, 25000] },
      { name: "VAT amount", labels: ["VAT 12% → 28,000 KZT", "VAT 16% → 29,000 KZT"], values: [3000, 4000] },
    ], {
      x: MX + 0.15, y: 1.76, w: lw - 0.3, h: 3.4, barDir: "col", barGrouping: "stacked", barGapWidthPct: 70,
      chartColors: [HEX.dk2, HEX.accent2],
      showTitle: true, title: "Final room price per night, KZT (same pre-tax price)", titleFontSize: 12, titleColor: HEX.dk1,
      showLegend: true, legendPos: "b", legendFontSize: 11, legendColor: HEX.dk1,
      showValue: true, dataLabelPosition: "ctr", dataLabelFormatCode: "#,##0", dataLabelFontSize: 12, dataLabelColor: HEX.lt1, dataLabelFontBold: true,
      catAxisLabelColor: HEX.dk1, catAxisLabelFontSize: 11, valAxisHidden: true, valAxisMinVal: 0, valAxisMaxVal: 30000,
      valGridLine: { style: "none" }, catGridLine: { style: "none" }, ...chartText,
      objectName: "VAT price chart",
    });

    const rx = MX + lw + 0.35, rw = W - MX - rx, sw = (rw - 2 * 0.22) / 3;
    [["+1,000", "KZT extra per night"], ["+3.57%", "price increase"], ["+2,000", "KZT extra for a 2-night stay"]].forEach(([n, l], i) => {
      const x = rx + i * (sw + 0.22);
      card(s, x, 1.68, sw, 1.05, { name: `VAT stat ${i + 1}` });
      s.addText(n, { x: x + 0.15, y: 1.75, w: sw - 0.3, h: 0.5, fontFace: HEAD, fontSize: 18, bold: true, color: C.text2, margin: 0, valign: "middle", isTextBox: true });
      s.addText(l, { x: x + 0.15, y: 2.25, w: sw - 0.3, h: 0.38, fontSize: 11, color: MUTED, margin: 0, valign: "middle", isTextBox: true });
    });
    card(s, rx, 2.95, rw, 1.3, { name: "Elasticity card" });
    s.addText("Estimated demand effect  ·  assumed price elasticity of demand = −1.2", { x: rx + 0.2, y: 3.02, w: rw - 0.4, h: 0.3, fontSize: 11, bold: true, color: MUTED, margin: 0, isTextBox: true });
    s.addText([
      { text: "−1.2 × 3.57% = ", options: { color: C.text1 } },
      { text: "−4.28%", options: { color: HEX.accent5, bold: true } },
    ], { x: rx + 0.2, y: 3.35, w: rw - 0.4, h: 0.5, fontFace: HEAD, fontSize: 22, margin: 0, valign: "middle", isTextBox: true });
    s.addText("A 3.57% rise in accommodation prices could lead to about a 4.28% fall in demand.", { x: rx + 0.2, y: 3.86, w: rw - 0.4, h: 0.32, fontSize: 12, color: C.text1, margin: 0, valign: "middle", isTextBox: true });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: rx, y: 4.45, w: rw, h: 0.78, rectRadius: 0.08, fill: { color: C.text2 }, line: { type: "none" }, shadow: shadow(), objectName: "VAT key finding" });
    s.addText([
      { text: "Key finding: ", options: { bold: true, color: HEX.accent2 } },
      { text: "higher VAT can reduce price competitiveness, particularly for small providers serving price-sensitive tourists.", options: { color: HEX.lt2 } },
    ], { x: rx + 0.22, y: 4.45, w: rw - 0.44, h: 0.78, fontSize: 13, margin: 0, valign: "middle", isTextBox: true });

    // Threshold qualification
    const qy = 5.45, qh = 1.1;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: MX, y: qy, w: CW, h: qh, rectRadius: 0.08, fill: { color: GOLD_TINT }, line: { type: "none" }, objectName: "Threshold qualification" });
    s.addText([
      { text: "Important qualification: ", options: { bold: true } },
      { text: "turnover ≈ 41.06 M KZT is below the 2026 mandatory VAT threshold (43.25 M KZT). The scenario applies only if the guesthouse registers or crosses the threshold; the simplified regime has its own VAT exemption." },
    ], { x: MX + 0.2, y: qy + 0.05, w: 6.3, h: qh - 0.1, fontSize: 12, color: C.text1, margin: 0, valign: "middle", isTextBox: true });
    const bx = 7.3, bw = W - MX - bx - 0.25, scale = bw / 46e6, by = qy + 0.48;
    s.addShape(pres.shapes.RECTANGLE, { x: bx, y: by, w: bw, h: 0.24, fill: { color: HEX.lt1 }, line: { color: HEX.accent3, width: 0.75 }, objectName: "Threshold track" });
    s.addShape(pres.shapes.RECTANGLE, { x: bx, y: by, w: 41.0625e6 * scale, h: 0.24, fill: { color: C.text2 }, line: { type: "none" }, objectName: "Revenue bar" });
    const tx = bx + 43.25e6 * scale;
    s.addShape(pres.shapes.LINE, { x: tx, y: by - 0.12, w: 0, h: 0.48, line: { color: HEX.accent5, width: 2 }, objectName: "Threshold marker" });
    s.addText("Revenue 41.06 M (95% of threshold)", { x: bx, y: qy + 0.12, w: 3.2, h: 0.26, fontSize: 11, bold: true, color: C.text2, margin: 0, isTextBox: true });
    s.addText("Threshold 43.25 M", { x: tx - 1.6, y: qy + 0.12, w: 1.6 + 0.25, h: 0.26, fontSize: 11, bold: true, color: HEX.accent5, align: "right", margin: 0, isTextBox: true });
    s.addText("Occupancy above ≈ 47.4% would trigger mandatory VAT registration", { x: bx, y: by + 0.3, w: bw + 0.25, h: 0.26, fontSize: 10, italic: true, color: MUTED, margin: 0, isTextBox: true });
    s.addNotes("KEY SLIDE. Assume the guesthouse is VAT-registered and keeps the same pre-tax price of 25,000 KZT: VAT rises from 3,000 to 4,000 KZT, the final price from 28,000 to 29,000 KZT (+3.57%). With an assumed elasticity of −1.2, demand could fall by ≈4.28%. Then the analytical caveat: our turnover of 41.06 M KZT is just below the 43.25 M KZT threshold (10,000 MCI × 4,325 KZT). Occupancy above ≈47.4% (43.25 M ÷ 91.25 M maximum revenue) would cross it — so the VAT question is close to becoming real for this business. (≈1.5 minutes)");
  }

  // ════════════════════ Slide 6 · International comparison ════════════════════
  pres.addSection({ title: "International experience" });
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "International experience" });
    s.addText("International Comparison of Tourism Taxes", { placeholder: "title" });
    s.addText("Bhutan focuses on sustainability, Japan on national tourism infrastructure, France on local destination financing", { placeholder: "sub" });
    const cols = [
      { c: "Bhutan", ic: I.mountain, tax: "Sustainable Development Fee", rate: "USD 100", unit: "per person per night", coll: "Collected during the stay", focus: "Sustainability",
        pts: ["Funds environmental conservation, infrastructure, healthcare and education", "Encourages a high-value, low-impact tourism model", "A two-night stay costs an extra USD 200 per adult"] },
      { c: "Japan", ic: I.plane, tax: "International Tourist Tax", rate: "JPY 3,000", unit: "per departure (from July 2026)", coll: "Collected via airlines and shipping lines", focus: "National infrastructure",
        pts: ["Raised from JPY 1,000 to JPY 3,000 from July 2026", "Paid when departing Japan", "Funds visitor infrastructure, crowd management and tourism development"] },
      { c: "France", ic: I.city, tax: "Taxe de séjour (Tourist Tax)", rate: "Variable", unit: "by accommodation category and municipality", coll: "Collected by accommodation providers", focus: "Local destination financing",
        pts: ["Charged to tourists staying in eligible accommodation", "Rates depend on accommodation type, classification and local decisions", "A local tourism revenue instrument for municipalities"] },
    ];
    const cw = (CW - 2 * 0.3) / 3, cy = 1.68, ch = 4.8;
    cols.forEach((k, i) => {
      const x = MX + i * (cw + 0.3);
      card(s, x, cy, cw, ch, { name: `${k.c} card` });
      iconDot(s, x + 0.25, cy + 0.25, k.ic, 0.55, HEX.dk2);
      s.addText([
        { text: k.c, options: { fontFace: HEAD, fontSize: 22, bold: true, color: C.text2, breakLine: true } },
        { text: k.tax, options: { fontSize: 12, color: MUTED } },
      ], { x: x + 0.95, y: cy + 0.2, w: cw - 1.15, h: 0.7, margin: 0, valign: "middle", isTextBox: true });
      s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.25, y: cy + 1.05, w: cw - 0.5, h: 0.95, rectRadius: 0.06, fill: { color: C.background2 }, line: { type: "none" }, objectName: `${k.c} rate` });
      s.addText([
        { text: k.rate, options: { fontFace: HEAD, fontSize: 24, bold: true, color: C.text2, breakLine: true } },
        { text: k.unit, options: { fontSize: 11, color: C.text1 } },
      ], { x: x + 0.4, y: cy + 1.08, w: cw - 0.8, h: 0.9, margin: 0, valign: "middle", isTextBox: true });
      s.addText(k.coll, { x: x + 0.25, y: cy + 2.1, w: cw - 0.5, h: 0.3, fontSize: 11, bold: true, color: HEX.accent6, margin: 0, isTextBox: true });
      s.addText(k.pts.map((t, j) => ({ text: t, options: { bullet: { indent: 14 }, breakLine: j < k.pts.length - 1 } })),
        { x: x + 0.25, y: cy + 2.45, w: cw - 0.5, h: 1.65, fontSize: 12, color: C.text1, margin: 0, valign: "top", paraSpaceAfter: 5, isTextBox: true });
      s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.25, y: cy + ch - 0.62, w: cw - 0.5, h: 0.4, rectRadius: 0.2, fill: { color: GOLD_TINT }, line: { type: "none" }, objectName: `${k.c} focus` });
      s.addText([{ text: "Focus: ", options: { bold: true } }, { text: k.focus }], { x: x + 0.25, y: cy + ch - 0.62, w: cw - 0.5, h: 0.4, fontSize: 12, color: C.text2, align: "center", margin: 0, valign: "middle", isTextBox: true });
    });
    sources(s, "Sources: Bhutan Department of Tourism; Japan National Tax Agency; Japan Tourism Agency; French Government (service-public.fr).");
    s.addNotes("Three models of tourism taxation. Bhutan: USD 100 per person per night — sustainability and high-value, low-impact tourism. Japan: departure tax raised to JPY 3,000 from July 2026 — national infrastructure and crowd management. France: municipal taxe de séjour collected by accommodation providers — local destination financing. (≈45 seconds)");
  }

  // ════════════════════ Slide 7 · Kazakhstan tourist contribution ════════════════════
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "International experience" });
    s.addText("Kazakhstan’s Tourist Contribution: Experience and Lessons", { placeholder: "title" });
    s.addText("Foreign-visitor contribution (2022–2023): guesthouses were exempt, so the Saty guesthouse would not charge it", { placeholder: "sub" });

    // Timeline
    const lw = 6.2;
    const nodes = [[MX + 0.35, "0%", "2022", "Contribution introduced at a 0% rate"], [MX + 3.45, "≤ 5%", "From 2023", "Up to 5% of accommodation cost, rate set locally"]];
    s.addShape(pres.shapes.LINE, { x: nodes[0][0] + 0.95, y: 2.2, w: nodes[1][0] - nodes[0][0] - 0.95, h: 0, line: { color: HEX.accent2, width: 2, dashType: "dash", endArrowType: "triangle" }, objectName: "Timeline" });
    nodes.forEach(([x, big, yr, txt], i) => {
      s.addShape(pres.shapes.OVAL, { x, y: 1.72, w: 0.95, h: 0.95, fill: { color: i ? C.text2 : C.background1 }, line: { color: HEX.dk2, width: 1.5 }, objectName: `Timeline node ${yr}` });
      s.addText(big, { x, y: 1.72, w: 0.95, h: 0.95, fontFace: HEAD, fontSize: 18, bold: true, color: i ? HEX.accent2 : C.text2, align: "center", valign: "middle", margin: 0, isTextBox: true });
      s.addText([{ text: yr, options: { bold: true, color: C.text2, breakLine: true } }, { text: txt }], { x: x - 0.35, y: 2.75, w: 2.75, h: 0.62, fontSize: 12, color: C.text1, margin: 0, valign: "top", isTextBox: true });
    });
    s.addText([
      "Applied to foreign visitors staying in accommodation facilities",
      "Rate determined locally; charged per day of stay",
      "Collected separately from the accommodation price",
    ].map((t, j, a) => ({ text: t, options: { bullet: { indent: 14 }, breakLine: j < a.length - 1 } })),
    { x: MX, y: 3.5, w: lw, h: 1.0, fontSize: 13, color: C.text1, margin: 0, valign: "top", paraSpaceAfter: 4, isTextBox: true });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: MX, y: 4.6, w: lw, h: 1.05, rectRadius: 0.08, fill: { color: C.text2 }, line: { type: "none" }, shadow: shadow(), objectName: "Exemption callout" });
    s.addImage({ data: I.shield, x: MX + 0.22, y: 4.88, w: 0.45, h: 0.45 });
    s.addText([
      { text: "Important exemption (2023 rules): ", options: { bold: true, color: HEX.accent2 } },
      { text: "hostels, guesthouses and rental accommodation were excluded — our Saty guesthouse would not be subject to the contribution.", options: { color: HEX.lt2 } },
    ], { x: MX + 0.85, y: 4.6, w: lw - 1.05, h: 1.05, fontSize: 12, margin: 0, valign: "middle", isTextBox: true });

    // Calculation card
    const rx = MX + lw + 0.35, rw = W - MX - rx;
    card(s, rx, 1.68, rw, 3.97, { name: "Contribution calculation card" });
    s.addText("Illustrative calculation — hotel stay, 2 nights", { x: rx + 0.25, y: 1.8, w: rw - 0.5, h: 0.32, fontSize: 14, bold: true, color: C.text2, margin: 0, isTextBox: true });
    const rowsCalc = [["Accommodation price", "25,000 KZT / night"], ["Stay duration", "2 nights"], ["Total accommodation", "50,000 KZT"], ["Contribution at 5%", "2,500 KZT"]];
    addTable(s, [["Indicator", "Amount"], ...rowsCalc], rx + 0.25, 2.22, [2.7, rw - 0.5 - 2.7], 12, 0.34, { name: "Contribution table", boldRow: 4 });
    const bx = rx + 0.25, bw = rw - 0.5, total = 52500, by = 4.35;
    s.addText("Guest pays 52,500 KZT in total", { x: bx, y: 4.0, w: bw, h: 0.28, fontSize: 12, bold: true, color: C.text1, margin: 0, isTextBox: true });
    s.addShape(pres.shapes.RECTANGLE, { x: bx, y: by, w: bw * 50000 / total, h: 0.5, fill: { color: C.text2 }, line: { type: "none" }, objectName: "Accommodation share" });
    s.addShape(pres.shapes.RECTANGLE, { x: bx + bw * 50000 / total, y: by, w: bw * 2500 / total, h: 0.5, fill: { color: C.accent2 }, line: { type: "none" }, objectName: "Contribution share" });
    s.addText("Accommodation 50,000 KZT (95.2%)", { x: bx + 0.12, y: by, w: bw - 0.6, h: 0.5, fontSize: 12, bold: true, color: HEX.lt2, margin: 0, valign: "middle", isTextBox: true });
    s.addText("+ 2,500 KZT contribution (4.8% of the bill) — collected separately", { x: bx, y: by + 0.58, w: bw, h: 0.3, fontSize: 11, italic: true, color: MUTED, margin: 0, isTextBox: true });

    // Lessons
    s.addText("LESSONS FOR KAZAKHSTAN", { x: MX, y: 5.82, w: 4, h: 0.26, fontSize: 11, bold: true, color: C.text2, charSpacing: 2, margin: 0, isTextBox: true });
    const lessons = [[I.coins, "Can generate extra destination revenue"], [I.users, "Exemptions protect small businesses"], [I.eye, "Revenue allocation must be transparent"], [I.chart, "Evaluate the effect on tourist demand"]];
    const lw2 = (CW - 3 * 0.2) / 4;
    lessons.forEach(([ic, t], i) => {
      const x = MX + i * (lw2 + 0.2);
      card(s, x, 6.12, lw2, 0.5, { name: `Lesson ${i + 1}`, noShadow: true });
      s.addImage({ data: ic, x: x + 0.14, y: 6.24, w: 0.26, h: 0.26 });
      s.addText(t, { x: x + 0.5, y: 6.12, w: lw2 - 0.6, h: 0.5, fontSize: 11, color: C.text1, margin: 0, valign: "middle", isTextBox: true });
    });
    sources(s, "Source: Government of Kazakhstan (2023), Rules for Payment of Tourist Contributions for Foreign Visitors.", 6.7);
    s.addNotes("Kazakhstan already tested a tourist contribution for foreign visitors: 0% in 2022, up to 5% of accommodation cost from 2023, set locally, charged per day and collected separately. Guesthouses were exempt — our Saty guesthouse would not charge it. For a two-night hotel stay at 25,000 KZT, a 5% contribution adds 2,500 KZT. Lessons: revenue potential, protection of small businesses, transparency, and monitoring demand effects. (≈45 seconds)");
  }

  // ════════════════════ Slide 8 · Recommendations ════════════════════
  pres.addSection({ title: "Recommendations and conclusion" });
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Recommendations and conclusion" });
    s.addText("Recommendations: Enterprise and Tourism Tax Policy", { placeholder: "title" });
    s.addText("Practical steps for the guesthouse and targeted policy options for Kazakhstan’s rural tourism", { placeholder: "sub" });
    const groups = [
      { h: "A · Recommendations for the guesthouse", ic: I.house, items: [
        ["Choose the appropriate tax regime", "Compare actual profitability, administrative costs and eligibility before choosing the general or simplified regime."],
        ["Introduce transparent pricing", "Clearly show the accommodation price, applicable VAT and any additional charges to tourists."],
        ["Improve financial planning", "Monitor occupancy, operating expenses and annual turnover to anticipate changes in tax obligations."],
      ] },
      { h: "B · Recommendations for Kazakhstan", ic: I.flag, items: [
        ["Support small rural tourism businesses", "Consider targeted tax incentives for eligible small accommodation providers in rural destinations."],
        ["Improve contribution transparency", "Publish annual reports on how tourism revenues fund infrastructure, waste management and conservation."],
        ["Introduce destination-based financing", "Carefully designed local visitor contributions in sensitive destinations, with exemptions and protection for small businesses."],
      ] },
    ];
    const gw = (CW - 0.3) / 2, gy = 1.68, gh = 4.08;
    groups.forEach((g, gi) => {
      const x = MX + gi * (gw + 0.3);
      card(s, x, gy, gw, gh, { name: `Group ${gi ? "B" : "A"}` });
      s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.2, y: gy + 0.2, w: gw - 0.4, h: 0.55, rectRadius: 0.06, fill: { color: C.text2 }, line: { type: "none" }, objectName: "Group header" });
      s.addImage({ data: g.ic, x: x + 0.38, y: gy + 0.33, w: 0.3, h: 0.3 });
      s.addText(g.h, { x: x + 0.82, y: gy + 0.2, w: gw - 1.1, h: 0.55, fontSize: 15, bold: true, color: HEX.lt2, margin: 0, valign: "middle", isTextBox: true });
      g.items.forEach(([t, d], i) => {
        const y = gy + 0.95 + i * 1.02;
        badge(s, x + 0.25, y + 0.03, i + 1, 0.4);
        s.addText([{ text: t, options: { bold: true, fontSize: 14, color: C.text2, breakLine: true } }, { text: d, options: { fontSize: 12, color: C.text1 } }],
          { x: x + 0.8, y, w: gw - 1.05, h: 0.95, margin: 0, valign: "top", paraSpaceAfter: 3, isTextBox: true });
      });
    });
    s.addText("EXPECTED EFFECTS", { x: MX, y: 5.9, w: 4, h: 0.26, fontSize: 11, bold: true, color: C.text2, charSpacing: 2, margin: 0, isTextBox: true });
    const effects = [[I.coins, "Financially sustainable SMEs"], [I.tag, "More transparent pricing"], [I.road, "Better destination infrastructure"], [I.tree, "Better environmental protection"], [I.chart, "More competitive rural tourism"]];
    const ew = (CW - 4 * 0.18) / 5;
    effects.forEach(([ic, t], i) => {
      const x = MX + i * (ew + 0.18);
      s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 6.2, w: ew, h: 0.55, rectRadius: 0.27, fill: { color: GOLD_TINT }, line: { type: "none" }, objectName: `Effect ${i + 1}` });
      s.addImage({ data: ic, x: x + 0.17, y: 6.34, w: 0.27, h: 0.27 });
      s.addText(t, { x: x + 0.52, y: 6.2, w: ew - 0.6, h: 0.55, fontSize: 11, bold: true, color: C.text2, margin: 0, valign: "middle", isTextBox: true });
    });
    s.addNotes("KEY SLIDE. For the guesthouse: (1) choose the regime from real margins — remember the 40% break-even margin and check eligibility; (2) show prices transparently, including VAT and any charges; (3) track occupancy and turnover — at ≈47.4% occupancy the business crosses the VAT threshold. For Kazakhstan: targeted incentives for rural SMEs, transparent reporting of tourism revenues, and carefully designed destination-based contributions with exemptions for small businesses. (≈1.5 minutes)");
  }

  // ════════════════════ Slide 9 · Conclusion & references ════════════════════
  {
    const s = pres.addSlide({ masterName: "CLOSING_DARK", sectionTitle: "Recommendations and conclusion" });
    const lw = 7.2;
    s.addText("Conclusion", { x: MX, y: 0.45, w: lw, h: 0.7, fontFace: HEAD, fontSize: 32, bold: true, color: HEX.lt2, margin: 0, valign: "middle", isTextBox: true });
    s.addText("Taxation directly influences the financial performance and competitiveness of small tourism enterprises.", { x: MX, y: 1.15, w: lw, h: 0.6, fontSize: 14, italic: true, color: "D9CFB6", margin: 0, valign: "top", isTextBox: true });
    const findings = [
      "The choice of tax regime should depend on profitability, eligibility and the total tax burden — not the nominal rate alone.",
      "Raising VAT from 12% to 16% can lift accommodation prices and potentially reduce tourist demand.",
      "International experience shows tourism contributions can support sustainable development when revenues are transparently allocated.",
    ];
    findings.forEach((t, i) => {
      const y = 2.0 + i * 0.95;
      badge(s, MX, y + 0.04, i + 1, 0.46, true);
      s.addText(t, { x: MX + 0.65, y, w: lw - 0.65, h: 0.85, fontSize: 14, color: HEX.lt2, margin: 0, valign: "top", isTextBox: true });
    });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: MX, y: 4.95, w: lw, h: 1.6, rectRadius: 0.08, fill: { color: "1F4D3A" }, line: { type: "none" }, objectName: "Final statement" });
    s.addImage({ data: I.quote, x: MX + 0.25, y: 5.12, w: 0.35, h: 0.35 });
    s.addText("Tourism taxation should not simply increase government revenue. It should balance business competitiveness, affordable travel and sustainable destination development.",
      { x: MX + 0.8, y: 5.0, w: lw - 1.05, h: 1.5, fontFace: HEAD, fontSize: 16, italic: true, color: HEX.accent2, margin: 0, valign: "middle", isTextBox: true });

    const rx = MX + lw + 0.4, rw = W - MX - rx;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: rx, y: 0.45, w: rw, h: 6.1, rectRadius: 0.08, fill: { color: HEX.lt2 }, line: { type: "none" }, objectName: "References panel" });
    s.addText("Selected references", { x: rx + 0.3, y: 0.6, w: rw - 0.6, h: 0.4, fontFace: HEAD, fontSize: 18, bold: true, color: C.text2, margin: 0, valign: "middle", isTextBox: true });
    const refs = [
      "Republic of Kazakhstan. (2025). Tax Code No. 214-VIII.",
      "OECD. (2014). Taxation and Tourism. OECD Tourism Trends and Policies.",
      "Gooroochurn, N., & Sinclair, M. T. (2005). Economics of tourism taxation: Evidence from Mauritius. Annals of Tourism Research, 32(2), 478–498.",
      "Stabler, M. J., Papatheodorou, A., & Sinclair, M. T. (2010). The Economics of Tourism. Routledge.",
      "State Revenue Committee of Kazakhstan. (2026).",
      "Bhutan Department of Tourism. (2026).",
      "Japan National Tax Agency. (2026).",
      "Japan Tourism Agency. (2026).",
      "French Government. (2026). Tourist Tax Regulations.",
      "Government of Kazakhstan. (2023). Rules for Payment of Tourist Contributions for Foreign Visitors.",
    ];
    s.addText(refs.map((t, j) => ({ text: t, options: { bullet: { type: "number", indent: 18 }, breakLine: j < refs.length - 1 } })),
      { x: rx + 0.3, y: 1.1, w: rw - 0.55, h: 5.3, fontSize: 12, color: C.text1, margin: 0, valign: "top", paraSpaceAfter: 6, isTextBox: true });
    s.addNotes("Summarise the three findings and finish with the final statement. Thank the audience and invite questions. Be ready to defend: (1) eligibility for the simplified regime must be checked; (2) turnover is just below the VAT threshold; (3) slide 4 compares income taxes only — the Excel model adds payroll and social payments. (≈45 seconds)");
  }

  await pres.writeFile({ fileName: OUT });
  await applyTheme(OUT, THEME);
  console.log("Wrote", OUT);
})().catch((e) => { console.error(e); process.exit(1); });
