const { chromium } = require('playwright');
(async () => {
  const F = process.argv[2], out = process.argv[3];
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 794, height: 1123 }, deviceScaleFactor: +(process.argv[4] || 1) })).newPage();
  await p.goto('file://' + F + '/flyer.html'); await p.waitForTimeout(400);
  const ov = await p.evaluate(() => { const pg = document.querySelector('.page'); return { scrollH: pg.scrollHeight, clientH: pg.clientHeight }; });
  console.log('page fits:', JSON.stringify(ov));
  await p.screenshot({ path: out + '.png' });
  if (process.argv[5] === 'pdf') await p.pdf({ path: out + '.pdf', format: 'A4', printBackground: true, preferCSSPageSize: true });
  await br.close();
})();
