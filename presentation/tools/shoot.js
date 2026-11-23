// Screenshots every slide of slides.html at 1920×1080 (for review). Usage: node tools/shoot.js <outdir> [scale]
const { chromium } = require("playwright");
const path = require("path");
(async () => {
  const out = process.argv[2] || "shots", scale = Number(process.argv[3] || 1);
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: scale });
  await p.goto("file://" + path.resolve(__dirname, "..", "slides.html"));
  await p.waitForTimeout(800);
  const n = await p.locator(".slide").count();
  require("fs").mkdirSync(out, { recursive: true });
  for (let i = 0; i < n; i++) await p.locator(".slide").nth(i).screenshot({ path: `${out}/slide-${i + 1}.png` });
  await b.close();
  console.log(n, "slides ->", out);
})();
