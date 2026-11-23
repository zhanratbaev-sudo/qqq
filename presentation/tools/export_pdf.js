// slides.html → output/*.pdf (vector text, one 1920×1080 page per slide). Usage: node tools/export_pdf.js
const { chromium } = require("playwright");
const path = require("path");
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1920, height: 1080 } });
  await p.goto("file://" + path.resolve(__dirname, "..", "slides.html"));
  await p.waitForTimeout(800);
  const out = path.resolve(__dirname, "..", "output", "Saty_Guesthouse_Tax_Presentation.pdf");
  await p.pdf({ path: out, width: "1920px", height: "1080px", printBackground: true, margin: { top: 0, right: 0, bottom: 0, left: 0 } });
  await b.close();
  console.log("PDF ->", out);
})();
