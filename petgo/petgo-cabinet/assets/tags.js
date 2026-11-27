/* PetGo · QR-адресники: наклейки и бирки на листе и PDF для печати.
   QR-код в PDF — векторный (чёткий при любом увеличении), код под QR — шрифтом PDF,
   фон (зелёный, лапки, надписи, логотип) — одна картинка 600 dpi на весь лист.
   Бирка 3 × 4 см — двусторонняя: лист A4 с QR (6 × 7 = 42 бирки вплотную, метки для резки по краям),
   за ним лист оборотов с логотипом PetGo — зеркально, чтобы при двусторонней печати
   (переворот по длинному краю) логотип попал точно на обратную сторону каждой бирки.
   Работает вместе с badge.js (общий сборщик PDF и выдача файла). */
(function () {
	"use strict";
	if (window.PGT) return;
	var D = document, N = navigator;
	/* k: v — вертикальная бирка, r — круг, b — прямоугольник; gap — промежуток на листе (0 — вплотную) */
	var F = {
		v34: { k: "v", w: 30, h: 40, gap: 0, two: 1 },
		r30: { k: "r", w: 30, h: 30, gap: 4 }, r25: { k: "r", w: 25, h: 25, gap: 4 }, r35: { k: "r", w: 35, h: 35, gap: 4 },
		b40: { k: "b", w: 40, h: 25, gap: 4 }
	};
	/* где белое поле с QR (доли ширины) */
	var PANEL = { v: [0.10, 0.19, 0.80], r: [0.17, 0.17, 0.66], b: [0.05, 0.05, 0.525] };
	var MARK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 4 64 64" width="256" height="256"><circle cx="32" cy="36" r="32" fill="#fff"/><g transform="translate(0 4)" fill="#024C46"><path d="M32 36c-8 0-14 6-14 11 0 5 4 7 8 6 3-1 4-2 6-2s3 1 6 2c4 1 8-1 8-6 0-5-6-11-14-11z"/><ellipse cx="19" cy="30" rx="4.5" ry="6" transform="rotate(-20 19 30)"/><ellipse cx="27" cy="22" rx="4.5" ry="6.5" transform="rotate(-6 27 22)"/><ellipse cx="37" cy="22" rx="4.5" ry="6.5" transform="rotate(6 37 22)"/><ellipse cx="45" cy="30" rx="4.5" ry="6" transform="rotate(20 45 30)"/></g></svg>';
	var WORD = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="78 6 172 63" width="688" height="252"><g transform="translate(76 4) scale(.62)" fill="none" stroke="#fff" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 30V92"/><circle cx="34" cy="50" r="20"/><path d="M72 50H112A20 20 0 1 0 106.14 64.14"/><path d="M142 12V54Q142 70 158 70"/><path d="M130 32H156"/><circle cx="196" cy="50" r="20"/><path d="M216 30V80Q216 96 196 96Q182 96 176 88"/><circle cx="254" cy="50" r="20"/></g></svg>';
	/* ширина знаков Helvetica-Bold (на 1000 единиц) — чтобы ровно центрировать код */
	var HW = { "-": 333, ".": 278, A: 722, B: 722, C: 722, D: 722, E: 667, F: 611, G: 778, H: 722, J: 556, K: 722, M: 833, N: 722, P: 667, Q: 778, R: 722, S: 667, T: 611, U: 722, V: 667, W: 944, X: 667, Y: 667, Z: 611, a: 556, e: 556, g: 611, k: 556, o: 611, p: 611, t: 333, z: 500 };
	function hw(t, size) { var w = 0; for (var i = 0; i < t.length; i++) w += HW[t[i]] || 556; return w * size / 1000; }

	function qr(text) { var q = window.qrcode(0, "Q"); q.addData(text); q.make(); return q; }
	var QZ = 3; /* белое поле вокруг QR — 3 клетки: тёмный фон рядом не мешает камере */
	/* QR на экране и для печати из браузера: SVG с белым полем */
	function svg(text) {
		var q = qr(text), n = q.getModuleCount(), d = "";
		for (var r = 0; r < n; r++) {
			for (var c = 0; c < n; c++) {
				if (!q.isDark(r, c)) continue;
				var s = c; while (c + 1 < n && q.isDark(r, c + 1)) c++;
				d += "M" + s + " " + r + "h" + (c - s + 1) + "v1h-" + (c - s + 1) + "z";
			}
		}
		return '<svg viewBox="' + (-QZ) + " " + (-QZ) + " " + (n + 2 * QZ) + " " + (n + 2 * QZ) + '" shape-rendering="crispEdges" aria-hidden="true"><path fill="#000" d="' + d + '"/></svg>';
	}
	function paint(root) {
		Array.prototype.forEach.call((root || D).querySelectorAll(".tg-q[data-q]"), function (e) {
			if (e._q) return; e._q = 1; e.innerHTML = svg(e.getAttribute("data-q"));
		});
	}

	function canvas(f) {
		var dpi = 600 / 25.4, W = Math.round(f.w * dpi), H = Math.round(f.h * dpi), c = D.createElement("canvas");
		c.width = W; c.height = H;
		return { c: c, g: c.getContext("2d"), W: W, H: H, u: W / 100 };
	}
	function green(L, g, W, H, ang, paw, u) {
		var gl = g.createLinearGradient(0, 0, W * ang, H); gl.addColorStop(0, "#0B6B5C"); gl.addColorStop(0.7, "#024C46"); gl.addColorStop(1, "#024C46");
		g.fillStyle = gl; g.fillRect(0, 0, W, H);
		if (paw) { g.fillStyle = g.createPattern(L.tile(paw, 60 * u), "repeat"); g.fillRect(0, 0, W, H); }
	}

	/* лицевая сторона (без QR и кода — они векторные) */
	function bg(fmt) {
		var L = window.PGB.lib, f = F[fmt];
		return Promise.all([L.load(L.svgUrl(L.pawSvg("rgba(255,255,255,.07)"))), f.k === "b" ? L.load(L.svgUrl(MARK)) : Promise.resolve(null)]).then(function (a) {
			var x = canvas(f), g = x.g, W = x.W, H = x.H, u = x.u, P = PANEL[f.k];
			g.fillStyle = "#fff"; g.fillRect(0, 0, W, H);
			g.save();
			if (f.k === "r") { g.beginPath(); g.arc(W / 2, H / 2, W / 2, 0, Math.PI * 2); g.clip(); var gr = g.createRadialGradient(W / 2, H * 0.3, 0, W / 2, H * 0.3, W * 0.7); gr.addColorStop(0, "#0B6B5C"); gr.addColorStop(0.7, "#024C46"); gr.addColorStop(1, "#024C46"); g.fillStyle = gr; g.fillRect(0, 0, W, H); if (a[0]) { g.fillStyle = g.createPattern(L.tile(a[0], 60 * u), "repeat"); g.fillRect(0, 0, W, H); } }
			else if (f.k === "b") { L.rr(g, 0, 0, W, H, 7.5 * u); g.clip(); green(L, g, W, H, 0.34, a[0], u); }
			/* бирка — зелёная до самого края (вплотную на листе: неточный рез не виден) */
			else green(L, g, W, H, 0.45, a[0], u);
			g.fillStyle = "#fff";
			L.rr(g, P[0] * W, P[1] * W, P[2] * W, P[2] * W, (f.k === "b" ? 5 : f.k === "v" ? 6 : 7) * u); g.fill();
			g.textAlign = "center"; g.textBaseline = "middle"; g.fillStyle = "#fff";
			if (f.k === "r") { g.font = "800 " + (6.2 * u) + "px " + L.FONT; g.fillText("НАШЛИ МЕНЯ?", W / 2, 11.7 * u, 56 * u); }
			else if (f.k === "v") { g.font = "800 " + (7.4 * u) + "px " + L.FONT; g.fillText("НАШЛИ МЕНЯ?", W / 2, 10.5 * u, 86 * u); }
			else {
				if (a[1]) g.drawImage(a[1], 71.75 * u, 6 * u, 14 * u, 14 * u);
				g.font = "800 " + (5.6 * u) + "px " + L.FONT;
				g.fillText("НАШЛИ", 78.75 * u, 27.25 * u, 31 * u); g.fillText("МЕНЯ?", 78.75 * u, 33.75 * u, 31 * u);
			}
			g.restore();
			return { w: W, h: H, b: L.bytes(x.c) };
		});
	}

	/* оборот бирки: логотип PetGo — одинаковый для всех */
	function back(fmt) {
		var L = window.PGB.lib, f = F[fmt];
		return Promise.all([L.load(L.svgUrl(L.pawSvg("rgba(255,255,255,.09)"))), L.load(L.svgUrl(MARK)), L.load(L.svgUrl(WORD))]).then(function (a) {
			var x = canvas(f), g = x.g, W = x.W, H = x.H, u = x.u;
			green(L, g, W, H, -0.45, a[0], u);
			if (a[1]) g.drawImage(a[1], 27 * u, 18 * u, 46 * u, 46 * u);
			if (a[2]) g.drawImage(a[2], 20 * u, 72 * u, 60 * u, 60 * u * 63 / 172);
			g.textAlign = "center"; g.textBaseline = "middle";
			g.fillStyle = "rgba(255,255,255,.82)"; g.font = "500 " + (5.4 * u) + "px " + L.FONT;
			g.fillText("выгул и присмотр", W / 2, 102 * u, 88 * u); g.fillText("за питомцами", W / 2, 109 * u, 88 * u);
			g.fillStyle = "#BFE3D2"; g.font = "700 " + (6.2 * u) + "px " + L.FONT;
			g.fillText("petgo.kz", W / 2, 122 * u, 88 * u);
			return { w: W, h: H, b: L.bytes(x.c) };
		});
	}

	/* раскладка на A4: сколько помещается и где начало */
	function grid(f) {
		var aw = f.gap ? 190 : 200, ah = f.gap ? 277 : 287;
		var cols = Math.floor((aw + f.gap) / (f.w + f.gap)), rows = Math.floor((ah + f.gap) / (f.h + f.gap));
		return { cols: cols, rows: rows, per: cols * rows, mx: (210 - (cols * f.w + (cols - 1) * f.gap)) / 2, my: (297 - (rows * f.h + (rows - 1) * f.gap)) / 2 };
	}

	/* все листы A4 */
	function build(codes, base, fmt) {
		var L = window.PGB.lib, f = F[fmt], K = 72 / 25.4, PH = 841.89, G = grid(f), P = L.f2, pn = PANEL[f.k];
		function Y(mm) { return PH - mm * K; }
		/* метки для резки — на полях листа, напротив каждой линии реза */
		function marks(n) {
			var s = "q 0.25 0.25 0.25 RG 0.3 w\n", rowsUsed = Math.min(G.rows, Math.ceil(n / G.cols)), colsUsed = G.cols, top = G.my, bot = G.my + rowsUsed * f.h;
			for (var i = 0; i <= colsUsed; i++) {
				var xm = (G.mx + i * f.w) * K;
				s += P(xm) + " " + P(Y(top - 1.5)) + " m " + P(xm) + " " + P(Y(Math.max(1, top - 6))) + " l S\n";
				s += P(xm) + " " + P(Y(bot + 1.5)) + " m " + P(xm) + " " + P(Y(Math.min(296, bot + 6))) + " l S\n";
			}
			for (var j = 0; j <= rowsUsed; j++) {
				var ym = Y(G.my + j * f.h);
				s += P((G.mx - 1.5) * K) + " " + P(ym) + " m " + P(Math.max(1, G.mx - 8) * K) + " " + P(ym) + " l S\n";
				s += P((G.mx + G.cols * f.w + 1.5) * K) + " " + P(ym) + " m " + P(Math.min(209, G.mx + G.cols * f.w + 8) * K) + " " + P(ym) + " l S\n";
			}
			return s + "Q\n";
		}
		function text(t, cx, base, size, rgb) { return "BT /F1 " + P(size) + " Tf " + rgb + " rg " + P(cx - hw(t, size) / 2) + " " + P(base) + " Td (" + t + ") Tj ET\n"; }
		return Promise.all([bg(fmt), f.two ? back(fmt) : Promise.resolve(null)]).then(function (im) {
			var pages = [];
			for (var p = 0; p * G.per < codes.length; p++) {
				var s = "", n = Math.min(G.per, codes.length - p * G.per), bs = "";
				for (var i = 0; i < n; i++) {
					var code = codes[p * G.per + i], col = i % G.cols, row = Math.floor(i / G.cols), x = G.mx + col * (f.w + f.gap), y = G.my + row * (f.h + f.gap);
					s += "q " + P(f.w * K) + " 0 0 " + P(f.h * K) + " " + P(x * K) + " " + P(Y(y + f.h)) + " cm /BG Do Q\n";
					/* QR — векторно */
					var q = qr(base + code), qn = q.getModuleCount(), side = pn[2] * f.w, px = x + pn[0] * f.w, py = y + pn[1] * f.w, m = side / (qn + 2 * QZ);
					s += "0 0 0 rg\n";
					for (var r = 0; r < qn; r++) {
						for (var c = 0; c < qn; c++) {
							if (!q.isDark(r, c)) continue;
							var c0 = c; while (c + 1 < qn && q.isDark(r, c + 1)) c++;
							s += P((px + (QZ + c0) * m) * K) + " " + P(Y(py + (QZ + r + 1) * m) - 0.03) + " " + P((c - c0 + 1) * m * K + 0.03) + " " + P(m * K + 0.06) + " re\n";
						}
					}
					s += "f\n";
					/* код — белым шрифтом (его вводят вручную в приложении) */
					var t = code.slice(0, 4) + "-" + code.slice(4), cx = (x + f.w / 2) * K;
					if (f.k === "r") s += text(t, cx, Y(y + 0.905 * f.w), 0.066 * f.w * K, "1 1 1");
					else if (f.k === "v") { s += text(t, cx, Y(y + 1.125 * f.w), 0.084 * f.w * K, "1 1 1"); s += text("petgo.kz", cx, Y(y + 1.235 * f.w), 0.056 * f.w * K, "0.75 0.89 0.824"); }
					else {
						var fb = 0.064 * f.w * K, bx = (x + 0.7875 * f.w) * K;
						s += text(code.slice(0, 4), bx, Y(y + 0.453 * f.w), fb, "1 1 1") + text(code.slice(4), bx, Y(y + 0.533 * f.w), fb, "1 1 1");
					}
					/* линия для вырезания (у бирок — метки на полях) */
					if (f.k === "r") {
						var R = (f.w / 2 + 0.6) * K, X = (x + f.w / 2) * K, YY = Y(y + f.h / 2), k = 0.5523 * R;
						s += "q 0.72 0.77 0.75 RG 0.35 w [1.5 1.5] 0 d " + P(X + R) + " " + P(YY) + " m " + P(X + R) + " " + P(YY + k) + " " + P(X + k) + " " + P(YY + R) + " " + P(X) + " " + P(YY + R) + " c " + P(X - k) + " " + P(YY + R) + " " + P(X - R) + " " + P(YY + k) + " " + P(X - R) + " " + P(YY) + " c " + P(X - R) + " " + P(YY - k) + " " + P(X - k) + " " + P(YY - R) + " " + P(X) + " " + P(YY - R) + " c " + P(X + k) + " " + P(YY - R) + " " + P(X + R) + " " + P(YY - k) + " " + P(X + R) + " " + P(YY) + " c S Q\n";
					} else if (f.k === "b") {
						s += "q 0.72 0.77 0.75 RG 0.35 w [1.5 1.5] 0 d " + P((x - 0.6) * K) + " " + P(Y(y + f.h + 0.6)) + " " + P((f.w + 1.2) * K) + " " + P((f.h + 1.2) * K) + " re S Q\n";
					}
					/* оборот: зеркально по горизонтали — при перевороте листа по длинному краю совпадёт с лицом */
					if (f.two) {
						var bxm = G.mx + (G.cols - 1 - col) * f.w;
						bs += "q " + P(f.w * K) + " 0 0 " + P(f.h * K) + " " + P(bxm * K) + " " + P(Y(y + f.h)) + " cm /BK Do Q\n";
					}
				}
				if (f.gap === 0) s += marks(n);
				pages.push({ ops: s });
				if (f.two) pages.push({ ops: bs + marks(n) });
			}
			var imgs = { BG: im[0] };
			if (im[1]) imgs.BK = im[1];
			return L.doc(pages, imgs);
		});
	}

	var PGT = {
		paint: paint,
		pdf: function (btn) {
			var L = window.PGB && window.PGB.lib, sh = D.querySelector("[data-pgt-sheet]");
			if (!L || !sh || !window.qrcode) return;
			var codes = Array.prototype.map.call(sh.querySelectorAll(".tg[data-c]"), function (e) { return e.getAttribute("data-c"); });
			if (!codes.length) return;
			L.busy(btn, true);
			build(codes, sh.getAttribute("data-base"), sh.getAttribute("data-fmt")).then(function (blob) {
				L.give(blob, "PetGo-adresniki-" + (sh.getAttribute("data-batch") || "") + ".pdf", "PDF готов");
			}).catch(function () { L.toast("Не получилось собрать файл. Попробуйте ещё раз."); }).then(function () { L.busy(btn, false); });
		}
	};
	window.PGT = PGT;
	function ready() { if (!window.qrcode) return setTimeout(ready, 60); paint(); }
	ready();
	D.addEventListener("click", function (e) {
		var t = e.target.closest && e.target.closest("[data-pgt-pdf],[data-pgt-print]");
		if (!t || t.disabled) return;
		e.preventDefault();
		/* в приложении на iPhone окно печати браузера может не открыться — там печать через PDF */
		if (t.hasAttribute("data-pgt-print") && !(N.standalone === true || (window.matchMedia && matchMedia("(display-mode: standalone)").matches && /iPhone|iPad|iPod/.test(N.userAgent)))) return window.print();
		PGT.pdf(t);
	});
})();
