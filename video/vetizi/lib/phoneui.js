// Экран телефона (canvas 1170 x 2484): главный экран, установка Vet.izi, открытие приложения, кадры записи.
// Все функции рисуют состояние по числам 0..1 — без часов и случайностей.
(function () {
  var W = 1170, H = 2484;
  function rr(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
  function clamp(k) { return Math.max(0, Math.min(1, k)); }
  function easeOut(k) { return 1 - Math.pow(1 - clamp(k), 3); }

  // обычные иконки без брендов: цвет + простой знак
  var ICONS = [
    ['Камера', '#3a3d42', 'cam'], ['Фото', '#ffffff', 'photo'], ['Почта', '#2f8cff', 'mail'], ['Часы', '#111214', 'clock'],
    ['Карты', '#34c759', 'map'], ['Погода', '#3aa0ff', 'sun'], ['Заметки', '#ffd23f', 'notes'], ['Календарь', '#ffffff', 'cal'],
    ['Музыка', '#ff375f', 'music'], ['Файлы', '#2f8cff', 'files'], ['Здоровье', '#ffffff', 'heart'], ['Настройки', '#8e8e93', 'gear'],
    ['Книги', '#ff9500', 'book'], null, ['Калькулятор', '#1c1c1e', 'calc'], ['Подкасты', '#a24cff', 'pod'],
  ];
  var DOCK = [['', '#34c759', 'phone'], ['', '#2f8cff', 'globe'], ['', '#34c759', 'msg'], ['', '#ff375f', 'music']];
  var SLOT = 13; // пустое место — сюда устанавливается Vet.izi
  var S = 176, COLX = [166, 445, 724, 1003], ROW0 = 300, ROWH = 300;
  function slotXY(i) { return [COLX[i % 4], ROW0 + Math.floor(i / 4) * ROWH]; }

  function glyph(c, type, x, y, s) {
    c.save(); c.translate(x, y);
    var k = s / 176;
    c.scale(k, k);
    c.lineCap = 'round'; c.lineJoin = 'round';
    var wht = '#fff';
    switch (type) {
      case 'cam': c.fillStyle = '#d7d9dd'; rr(c, -56, -36, 112, 76, 18); c.fill(); c.fillStyle = '#3a3d42'; c.beginPath(); c.arc(0, 2, 24, 0, 7); c.fill(); break;
      case 'photo': ['#ff9f0a', '#ffd60a', '#34c759', '#0a84ff', '#bf5af2', '#ff375f'].forEach(function (col, i) { c.fillStyle = col; c.globalAlpha = .85; c.beginPath(); c.ellipse(Math.cos(i) * 26, Math.sin(i) * 26, 30, 18, i, 0, 7); c.fill(); }); c.globalAlpha = 1; break;
      case 'mail': c.fillStyle = wht; rr(c, -56, -38, 112, 76, 12); c.fill(); c.strokeStyle = '#2f8cff'; c.lineWidth = 8; c.beginPath(); c.moveTo(-50, -30); c.lineTo(0, 8); c.lineTo(50, -30); c.stroke(); break;
      case 'clock': c.strokeStyle = wht; c.lineWidth = 8; c.beginPath(); c.arc(0, 0, 58, 0, 7); c.stroke(); c.beginPath(); c.moveTo(0, 0); c.lineTo(0, -38); c.moveTo(0, 0); c.lineTo(28, 14); c.stroke(); break;
      case 'map': c.strokeStyle = wht; c.lineWidth = 14; c.beginPath(); c.moveTo(-50, 50); c.lineTo(-10, -10); c.lineTo(20, 20); c.lineTo(55, -50); c.stroke(); break;
      case 'sun': c.fillStyle = '#ffd60a'; c.beginPath(); c.arc(-14, -14, 30, 0, 7); c.fill(); c.fillStyle = wht; c.beginPath(); c.ellipse(16, 20, 44, 24, 0, 0, 7); c.fill(); break;
      case 'notes': c.fillStyle = '#fff'; rr(c, -60, -20, 120, 76, 8); c.fill(); c.strokeStyle = '#d8d0b0'; c.lineWidth = 5; [0, 18, 36].forEach(function (yy) { c.beginPath(); c.moveTo(-48, yy); c.lineTo(48, yy); c.stroke(); }); break;
      case 'cal': c.fillStyle = '#ff3b30'; c.font = '600 30px sans-serif'; c.textAlign = 'center'; c.fillText('ЧТ', 0, -36); c.fillStyle = '#111'; c.font = '300 84px sans-serif'; c.fillText('30', 0, 46); break;
      case 'music': c.fillStyle = wht; c.beginPath(); c.arc(-24, 34, 18, 0, 7); c.arc(30, 22, 18, 0, 7); c.fill(); c.fillRect(-10, -46, 10, 80); c.fillRect(44, -58, 10, 80); c.fillRect(-10, -58, 64, 14); break;
      case 'files': c.fillStyle = wht; rr(c, -58, -40, 116, 84, 14); c.fill(); c.fillStyle = '#b8d6ff'; rr(c, -58, -40, 56, 24, 8); c.fill(); break;
      case 'heart': c.fillStyle = '#ff375f'; c.beginPath(); c.moveTo(0, 46); c.bezierCurveTo(-80, -10, -36, -70, 0, -30); c.bezierCurveTo(36, -70, 80, -10, 0, 46); c.fill(); break;
      case 'gear': c.strokeStyle = '#e5e5ea'; c.lineWidth = 16; c.beginPath(); c.arc(0, 0, 36, 0, 7); c.stroke(); for (var i = 0; i < 8; i++) { c.save(); c.rotate(i * Math.PI / 4); c.fillStyle = '#e5e5ea'; c.fillRect(-8, -64, 16, 22); c.restore(); } break;
      case 'book': c.fillStyle = wht; c.beginPath(); c.moveTo(0, -36); c.quadraticCurveTo(-30, -52, -60, -40); c.lineTo(-60, 42); c.quadraticCurveTo(-30, 30, 0, 46); c.quadraticCurveTo(30, 30, 60, 42); c.lineTo(60, -40); c.quadraticCurveTo(30, -52, 0, -36); c.fill(); break;
      case 'calc': c.fillStyle = '#ff9f0a'; [[-34, -34], [0, -34], [34, -34], [-34, 0], [0, 0], [34, 0], [-34, 34], [0, 34], [34, 34]].forEach(function (p, i) { c.fillStyle = i % 3 === 2 ? '#ff9f0a' : '#5c5c60'; c.beginPath(); c.arc(p[0], p[1], 13, 0, 7); c.fill(); }); break;
      case 'pod': c.strokeStyle = wht; c.lineWidth = 9; c.beginPath(); c.arc(0, -6, 40, Math.PI * 0.8, Math.PI * 2.2); c.stroke(); c.fillStyle = wht; c.beginPath(); c.arc(0, -8, 14, 0, 7); c.fill(); c.fillRect(-7, 6, 14, 44); break;
      case 'phone': c.fillStyle = wht; c.beginPath(); c.moveTo(-40, -46); c.quadraticCurveTo(-58, 10, 10, 54); c.lineTo(34, 30); c.lineTo(10, 10); c.lineTo(-2, 22); c.quadraticCurveTo(-24, 6, -24, -16); c.lineTo(-12, -28); c.lineTo(-30, -56); c.closePath(); c.fill(); break;
      case 'globe': c.strokeStyle = wht; c.lineWidth = 8; c.beginPath(); c.arc(0, 0, 52, 0, 7); c.stroke(); c.beginPath(); c.ellipse(0, 0, 22, 52, 0, 0, 7); c.stroke(); c.beginPath(); c.moveTo(-52, 0); c.lineTo(52, 0); c.stroke(); break;
      case 'msg': c.fillStyle = wht; c.beginPath(); c.ellipse(0, -6, 58, 44, 0, 0, 7); c.fill(); c.beginPath(); c.moveTo(-30, 28); c.lineTo(-44, 54); c.lineTo(-6, 34); c.fill(); break;
      case 'paw': c.fillStyle = '#0e7c6b'; c.beginPath(); c.ellipse(0, 22, 32, 26, 0, 0, 7); c.fill();
        [[-38, -8, -0.35], [38, -8, 0.35], [-16, -38, -0.1], [16, -38, 0.1]].forEach(function (t) { c.beginPath(); c.ellipse(t[0], t[1], 12, 17, t[2], 0, 7); c.fill(); }); break;
    }
    c.restore();
  }
  function icon(c, x, y, s, bg, type, label) {
    c.save();
    c.shadowColor = 'rgba(0,0,0,.18)'; c.shadowBlur = 16; c.shadowOffsetY = 6;
    rr(c, x - s / 2, y - s / 2, s, s, s * 0.235);
    if (bg === 'vet') { var g = c.createLinearGradient(x - s / 2, y - s / 2, x + s / 2, y + s / 2); g.addColorStop(0, '#f4f1e4'); g.addColorStop(1, '#e2dcc4'); c.fillStyle = g; }
    else c.fillStyle = bg;
    c.fill(); c.restore();
    glyph(c, type, x, y, s);
    if (label) {
      c.fillStyle = '#fff'; c.font = '500 34px "Inter Medium", "Inter", sans-serif'; c.textAlign = 'center';
      c.shadowColor = 'rgba(0,0,0,.35)'; c.shadowBlur = 6; c.fillText(label, x, y + s / 2 + 50); c.shadowBlur = 0;
    }
  }
  function wallpaper(c) {
    var g = c.createLinearGradient(0, 0, W, H); g.addColorStop(0, '#0b4f6e'); g.addColorStop(0.55, '#0e7c6b'); g.addColorStop(1, '#1d2b6b');
    c.fillStyle = g; c.fillRect(0, 0, W, H);
    [[200, 600, 700, 'rgba(63,224,160,.35)'], [1000, 1500, 800, 'rgba(47,123,255,.35)'], [500, 2200, 600, 'rgba(63,224,160,.25)']].forEach(function (b) {
      var r = c.createRadialGradient(b[0], b[1], 0, b[0], b[1], b[2]); r.addColorStop(0, b[3]); r.addColorStop(1, 'rgba(0,0,0,0)'); c.fillStyle = r; c.fillRect(0, 0, W, H);
    });
  }
  function status(c, dark) {
    c.fillStyle = dark ? '#000' : '#fff'; c.font = '600 46px "Inter SemiBold", "Inter", sans-serif'; c.textAlign = 'center'; c.fillText('9:41', 190, 118);
    c.fillRect(890, 92, 10, 26); c.fillRect(908, 84, 10, 34); c.fillRect(926, 76, 10, 42);
    rr(c, 975, 82, 80, 38, 11); c.lineWidth = 4; c.strokeStyle = dark ? '#000' : '#fff'; c.stroke(); rr(c, 981, 88, 60, 26, 7); c.fill();
  }

  // Главный экран. st: { install: 0..1 (установка Vet.izi), tap: 0..1 (нажатие) }
  function home(c, st) {
    st = st || {};
    wallpaper(c); status(c);
    ICONS.forEach(function (ic, i) { if (!ic) return; var p = slotXY(i); icon(c, p[0], p[1], S, ic[1], ic[2], ic[0]); });
    // док
    c.save(); rr(c, 40, 2150, W - 80, 250, 80); c.fillStyle = 'rgba(255,255,255,.22)'; c.fill(); c.restore();
    DOCK.forEach(function (ic, i) { icon(c, COLX[i], 2275, S, ic[1], ic[2], ''); });
    var k = st.install || 0;
    if (k > 0) {
      var p = slotXY(SLOT);
      var pop = easeOut(Math.min(1, k * 4));
      var s = S * (0.4 + 0.6 * pop) * (1 - 0.08 * (st.tap || 0));
      c.save(); c.globalAlpha = pop;
      icon(c, p[0], p[1], s, 'vet', 'paw', k >= 1 ? 'Vet.izi' : 'Загрузка…');
      if (k < 1) { // затемнение и кольцо прогресса, как при загрузке приложения
        c.fillStyle = 'rgba(0,0,0,.45)'; rr(c, p[0] - s / 2, p[1] - s / 2, s, s, s * 0.235); c.fill();
        c.strokeStyle = 'rgba(255,255,255,.35)'; c.lineWidth = 10; c.beginPath(); c.arc(p[0], p[1], 46, 0, 7); c.stroke();
        c.strokeStyle = '#fff'; c.beginPath(); c.arc(p[0], p[1], 46, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * clamp((k - 0.15) / 0.85)); c.stroke();
      }
      c.restore();
      if (st.tap > 0 && st.tap < 1) { // касание пальцем
        c.save(); c.globalAlpha = 0.5 * (1 - st.tap); c.fillStyle = '#fff'; c.beginPath(); c.arc(p[0], p[1], 60 + 50 * st.tap, 0, 7); c.fill(); c.restore();
      }
    }
  }
  function slot() { return slotXY(SLOT); }

  // Раскрытие иконки на весь экран (как в референсе): круг из иконки, затем заставка с лапкой
  function flood(c, k, splash) {
    var p = slotXY(SLOT), e = easeOut(k);
    var r = 90 + e * 2900;
    c.save(); c.beginPath(); c.arc(p[0] + (W / 2 - p[0]) * e, p[1] + (H / 2 - p[1]) * e, r, 0, 7); c.clip();
    var g = c.createLinearGradient(0, 0, W, H); g.addColorStop(0, '#14a38d'); g.addColorStop(1, '#0b6e63'); c.fillStyle = g; c.fillRect(0, 0, W, H);
    if (splash > 0) {
      c.globalAlpha = clamp(splash);
      icon(c, W / 2, H / 2 - 80, 300 + 40 * splash, 'vet', 'paw', '');
      c.fillStyle = '#f4f1e4'; c.font = '900 120px "Inter Display Black", "Inter", sans-serif'; c.textAlign = 'center'; c.fillText('Vet.izi', W / 2, H / 2 + 230);
    }
    c.restore();
  }

  // Кадр записи (k 0..1 по последовательности), вписанный в экран с полосой статуса сверху
  function seq(c, imgs, k, alpha) {
    if (!imgs || !imgs.length) return;
    var i = Math.min(imgs.length - 1, Math.max(0, Math.floor(clamp(k) * (imgs.length - 1) + 0.0001)));
    var im = imgs[i]; if (!im) return;
    var s = W / im.width;
    c.save(); c.globalAlpha = alpha == null ? 1 : alpha;
    c.fillStyle = '#f2f2f7'; c.fillRect(0, 0, W, H);
    c.drawImage(im, 0, 0, im.width, im.height, 0, 150, W, im.height * s);
    c.fillStyle = '#f2f2f7'; c.fillRect(0, 0, W, 150);
    status(c, true);
    c.restore();
  }
  function loadSeq(dir, n) {
    var arr = [];
    var ps = [];
    for (var i = 1; i <= n; i++) {
      (function (j) {
        ps.push(new Promise(function (res) { var im = new Image(); im.onload = function () { arr[j - 1] = im; res(); }; im.onerror = res; im.src = dir + '/' + String(j).padStart(3, '0') + '.jpg'; }));
      })(i);
    }
    return Promise.all(ps).then(function () { return arr; });
  }
  function banner(c, k, y, title, text, iconBg) {
    if (k <= 0) return;
    var e = easeOut(k), yy = y - (1 - e) * 260;
    c.save(); c.globalAlpha = clamp(k * 2.5);
    c.shadowColor = 'rgba(0,0,0,0.28)'; c.shadowBlur = 40; c.shadowOffsetY = 12;
    rr(c, 36, yy, 1098, 230, 64); c.fillStyle = 'rgba(248,248,250,0.97)'; c.fill(); c.shadowColor = 'transparent';
    icon(c, 132, yy + 106, 120, iconBg || 'vet', 'paw', '');
    c.fillStyle = '#111'; c.font = '700 46px "Inter SemiBold", "Inter", sans-serif'; c.textAlign = 'left'; c.fillText(title, 224, yy + 92);
    c.fillStyle = '#8a8a90'; c.font = '500 38px "Inter Medium", "Inter", sans-serif'; c.fillText('сейчас', 940, yy + 92);
    c.fillStyle = '#222'; c.font = '500 44px "Inter Medium", "Inter", sans-serif'; c.fillText(text, 224, yy + 160);
    c.restore();
  }
  window.PUI = { W: W, H: H, home: home, flood: flood, seq: seq, loadSeq: loadSeq, banner: banner, slot: slot };
})();
