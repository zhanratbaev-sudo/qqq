/* PetGo · бейдж сотрудника в PDF и картинку.
   Бейдж рисуется прямо на телефоне (холст), PDF собирается здесь же — сервер и сторонние сервисы не нужны.
   Лист A4: бейджи 54 × 85,6 мм в настоящем размере, по 9 на лист, с пунктиром для вырезания.
   На телефоне готовый файл отдаётся через меню «Поделиться» (Сохранить в Файлы / Напечатать / WhatsApp),
   на компьютере — обычным скачиванием. */
(function () {
	"use strict";
	if (window.PGB) return;
	var D = document, N = navigator;
	var FONT = '-apple-system,BlinkMacSystemFont,"SF Pro Text","Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
	var MONO = 'ui-monospace,"SF Mono",SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace';
	var PAW = "<path d='M50 50c10 0 17 9 22 17 4 6 3 14-4 16-6 2-11-1-18-1s-12 3-18 1c-7-2-8-10-4-16 5-8 12-17 22-17z'/><ellipse cx='24' cy='44' rx='9' ry='11' transform='rotate(-18 24 44)'/><ellipse cx='40' cy='27' rx='9.5' ry='12'/><ellipse cx='60' cy='27' rx='9.5' ry='12'/><ellipse cx='76' cy='44' rx='9' ry='11' transform='rotate(18 76 44)'/>";

	function pawSvg(c) {
		return "<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220' viewBox='0 0 220 220'><g fill='" + c + "'><g transform='translate(20 24) rotate(-22 15 15) scale(.3)'>" + PAW + "</g><g transform='translate(140 96) rotate(18 13 13) scale(.26)'>" + PAW + "</g><g transform='translate(54 156) rotate(-8 11 11) scale(.22)'>" + PAW + "</g></g></svg>";
	}
	function svgUrl(s) { return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(s); }
	function load(src) {
		return new Promise(function (ok) {
			if (!src) return ok(null);
			var i = new Image();
			i.onload = function () { ok(i.naturalWidth ? i : null); };
			i.onerror = function () { ok(null); };
			i.src = src;
		});
	}
	function tile(img, size) {
		var c = D.createElement("canvas");
		c.width = c.height = Math.max(1, Math.round(size));
		c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
		return c;
	}
	function rr(g, x, y, w, h, r) {
		r = Math.min(r, w / 2, h / 2);
		g.beginPath();
		g.moveTo(x + r, y);
		g.arcTo(x + w, y, x + w, y + h, r);
		g.arcTo(x + w, y + h, x, y + h, r);
		g.arcTo(x, y + h, x, y, r);
		g.arcTo(x, y, x + w, y, r);
		g.closePath();
	}
	/* текст с разрядкой (как letter-spacing в CSS) */
	function swidth(g, t, sp) {
		var w = 0;
		for (var i = 0; i < t.length; i++) w += g.measureText(t[i]).width;
		return w + sp * Math.max(0, t.length - 1);
	}
	function spaced(g, t, x, y, sp) {
		g.textAlign = "left";
		for (var i = 0; i < t.length; i++) { g.fillText(t[i], x, y); x += g.measureText(t[i]).width + sp; }
	}
	/* имя: до двух строк, длинное — чуть мельче, в крайнем случае с многоточием */
	function wrap(g, t, max) {
		var words = String(t).split(/\s+/), lines = [], cur = "", cut = false;
		words.forEach(function (w) {
			var n = cur ? cur + " " + w : w;
			if (!cur || g.measureText(n).width <= max) cur = n; else { lines.push(cur); cur = w; }
		});
		if (cur) lines.push(cur);
		if (lines.length > 2) { lines = [lines[0], lines.slice(1).join(" ")]; cut = true; }
		lines = lines.map(function (l) {
			if (g.measureText(l).width <= max) return l;
			cut = true;
			while (l.length > 1 && g.measureText(l + "…").width > max) l = l.slice(0, -1);
			return l + "…";
		});
		return { l: lines, cut: cut };
	}

	var assets = null;
	function getAssets() {
		if (assets) return assets;
		var svg = D.querySelector(".pgb-logo svg"), logo = "";
		if (svg && window.XMLSerializer) {
			var s = svg.cloneNode(true);
			s.setAttribute("xmlns", "http://www.w3.org/2000/svg");
			s.setAttribute("width", "508"); s.setAttribute("height", "144");
			logo = svgUrl(new XMLSerializer().serializeToString(s));
		}
		assets = Promise.all([load(logo), load(svgUrl(pawSvg("rgba(2,76,70,.05)"))), load(svgUrl(pawSvg("rgba(255,255,255,.08)")))]).then(function (a) {
			return { logo: a[0], pawL: a[1], pawW: a[2] };
		});
		return assets;
	}

	/* один бейдж на холсте шириной W пикселей; размеры — доли ширины, как на экране (cqw) */
	function draw(b, A, photo, W) {
		var u = W / 100, H = Math.round(W * 85.6 / 54);
		var c = D.createElement("canvas"); c.width = W; c.height = H;
		var g = c.getContext("2d");
		g.fillStyle = "#fff"; g.fillRect(0, 0, W, H);
		g.save(); rr(g, 0, 0, W, H, 6.4 * u); g.clip();
		g.fillStyle = "#FAFCFA"; g.fillRect(0, 0, W, H);
		if (A.pawL) { g.fillStyle = g.createPattern(tile(A.pawL, 60 * u), "repeat"); g.fillRect(0, 0, W, H); }
		/* зелёная полоса с логотипом */
		var bh = 44 * u, ang = 160 * Math.PI / 180, dx = Math.sin(ang), dy = -Math.cos(ang), L = Math.abs(W * dx) + Math.abs(bh * dy);
		var gr = g.createLinearGradient(W / 2 - dx * L / 2, bh / 2 - dy * L / 2, W / 2 + dx * L / 2, bh / 2 + dy * L / 2);
		gr.addColorStop(0, "#0B6B5C"); gr.addColorStop(0.7, "#024C46"); gr.addColorStop(1, "#024C46");
		g.fillStyle = gr; g.fillRect(0, 0, W, bh);
		if (A.pawW) { g.fillStyle = g.createPattern(tile(A.pawW, 60 * u), "repeat"); g.fillRect(0, 0, W, bh); }
		if (A.logo) g.drawImage(A.logo, 7 * u, 7 * u, 33 * u, 33 * u * 72 / 254);
		g.font = "700 " + (3.3 * u) + "px " + FONT; g.fillStyle = "rgba(255,255,255,.82)"; g.textBaseline = "middle";
		var kind = "СОТРУДНИК", ksp = 3.3 * u * 0.14;
		spaced(g, kind, W - 7 * u - swidth(g, kind, ksp), 8.4 * u + 2 * u, ksp);
		/* фото в белой рамке */
		g.save();
		g.shadowColor = "rgba(2,76,70,.25)"; g.shadowBlur = 5 * u; g.shadowOffsetY = 2 * u;
		rr(g, 25 * u, 20 * u, 50 * u, 60 * u, 7 * u); g.fillStyle = "#fff"; g.fill();
		g.restore();
		var px = 26.5 * u, py = 21.5 * u, pw = 47 * u, ph = 57 * u;
		g.save(); rr(g, px, py, pw, ph, 5.5 * u); g.clip();
		if (photo) {
			var iw = photo.naturalWidth, ih = photo.naturalHeight, s = Math.max(pw / iw, ph / ih), sw = pw / s, sh = ph / s;
			g.drawImage(photo, (iw - sw) / 2, (ih - sh) / 2, sw, sh, px, py, pw, ph);
		} else {
			g.fillStyle = "#E1F2E9"; g.fillRect(px, py, pw, ph);
			g.fillStyle = "#024C46"; g.font = "700 " + (20 * u) + "px " + FONT; g.textAlign = "center"; g.textBaseline = "middle";
			g.fillText(b.i || "", px + pw / 2, py + ph / 2);
		}
		g.restore();
		/* имя, должность, ID */
		var y = 84 * u, fs = 7.4 * u, w;
		for (;;) {
			g.font = "800 " + fs + "px " + FONT;
			w = wrap(g, b.n || "", 88 * u);
			if (!w.cut || fs <= 5.6 * u) break;
			fs -= 0.3 * u;
		}
		g.fillStyle = "#0F2A26"; g.textAlign = "center"; g.textBaseline = "middle";
		w.l.forEach(function (l) { g.fillText(l, W / 2, y + fs * 0.56); y += fs * 1.12; });
		y += 2.2 * u;
		g.font = "700 " + (4 * u) + "px " + FONT;
		var rw = g.measureText(b.r || "").width + 6.8 * u, rh = 4 * u * 1.2 + 2.2 * u;
		rr(g, W / 2 - rw / 2, y, rw, rh, rh / 2); g.fillStyle = "#E1F2E9"; g.fill();
		g.fillStyle = "#024C46"; g.fillText(b.r || "", W / 2, y + rh / 2);
		y += rh + 2.8 * u;
		g.textBaseline = "alphabetic";
		g.font = "700 " + (3.2 * u) + "px " + FONT;
		var lsp = 3.2 * u * 0.12, lw = swidth(g, "ID", lsp);
		g.font = "800 " + (6.4 * u) + "px " + MONO;
		var nsp = 6.4 * u * 0.06, nw = swidth(g, b.id || "", nsp), x0 = W / 2 - (lw + 2 * u + nw) / 2, base = y + 6.4 * u * 0.8;
		g.fillStyle = "#6B7A77"; g.font = "700 " + (3.2 * u) + "px " + FONT; spaced(g, "ID", x0, base, lsp);
		g.fillStyle = "#024C46"; g.font = "800 " + (6.4 * u) + "px " + MONO; spaced(g, b.id || "", x0 + lw + 2 * u, base, nsp);
		/* нижняя белая полоса: QR и подписи */
		var top = H - 34 * u;
		g.fillStyle = "#fff"; g.fillRect(0, top, W, 34 * u);
		g.fillStyle = "rgba(15,42,38,.08)"; g.fillRect(0, top, W, 0.4 * u);
		var cy = (top + H - 2 * u) / 2, qx = 6 * u, qy = cy - 12 * u;
		rr(g, qx - 0.4 * u, qy - 0.4 * u, 24.8 * u, 24.8 * u, 2.4 * u); g.fillStyle = "rgba(15,42,38,.1)"; g.fill();
		rr(g, qx, qy, 24 * u, 24 * u, 2 * u); g.fillStyle = "#fff"; g.fill();
		if (b.q && window.qrcode) {
			var q = window.qrcode(0, "M"); q.addData(b.q); q.make();
			var n = q.getModuleCount(), x1 = qx + u, y1 = qy + u, cs = 22 * u / n;
			g.fillStyle = "#000";
			for (var r = 0; r < n; r++) for (var k = 0; k < n; k++) if (q.isDark(r, k)) {
				var ax = Math.round(x1 + k * cs), ay = Math.round(y1 + r * cs);
				g.fillRect(ax, ay, Math.round(x1 + (k + 1) * cs) - ax, Math.round(y1 + (r + 1) * cs) - ay);
			}
		}
		var tx = qx + 24 * u + 4 * u, tmax = W - tx - 5 * u;
		var t3 = "petgo.kz · " + (b.t || ""), f3 = 3.1 * u;
		g.font = "600 " + f3 + "px " + FONT;
		while (f3 > 2.4 * u && g.measureText(t3).width > tmax) { f3 -= 0.1 * u; g.font = "600 " + f3 + "px " + FONT; }
		var h1 = 3.7 * u * 1.2, h2 = 3.1 * u * 1.3, h3 = f3 * 1.25, ty = cy - (h1 + h2 + h3 + 1.6 * u) / 2;
		g.textAlign = "left"; g.textBaseline = "middle";
		g.fillStyle = "#0F2A26"; g.font = "700 " + (3.7 * u) + "px " + FONT; g.fillText("Проверить сотрудника", tx, ty + h1 / 2, tmax); ty += h1 + 0.8 * u;
		g.fillStyle = "#6B7A77"; g.font = "400 " + (3.1 * u) + "px " + FONT; g.fillText("Наведите камеру на QR-код", tx, ty + h2 / 2, tmax); ty += h2 + 0.8 * u;
		g.fillStyle = "#024C46"; g.font = "600 " + f3 + "px " + FONT; g.fillText(t3, tx, ty + h3 / 2, tmax);
		g.fillStyle = "#1E9A5F"; g.fillRect(0, H - 2 * u, W, 2 * u);
		g.restore();
		return c;
	}

	/* холст с бейджем; если фото нельзя перенести в файл — бейдж с инициалом */
	function render(b, W, type) {
		return getAssets().then(function (A) {
			return load(b.ph).then(function (photo) {
				var c = draw(b, A, photo, W);
				try { c.toDataURL(type, 0.9); } catch (e) { c = draw(b, A, null, W); }
				return c;
			});
		});
	}
	function bytes(c) {
		var s = atob(c.toDataURL("image/jpeg", 0.92).split(",")[1]), a = new Uint8Array(s.length);
		for (var i = 0; i < s.length; i++) a[i] = s.charCodeAt(i);
		return a;
	}

	/* PDF: A4, по 9 бейджей (3 × 3) в настоящем размере, пунктир для вырезания */
	function pdf(imgs) {
		var enc = new TextEncoder(), parts = [], off = 0, offs = [];
		function put(x) { var b = typeof x === "string" ? enc.encode(x) : x; parts.push(b); off += b.length; }
		function f(v) { return (Math.round(v * 100) / 100).toString(); }
		var K = 72 / 25.4, PW = 595.28, PH = 841.89, bw = 54, bh = 85.6, gap = 6, per = 9;
		var mx = (210 - (3 * bw + 2 * gap)) / 2, my = (297 - (3 * bh + 2 * gap)) / 2;
		var pages = Math.ceil(imgs.length / per), id = 3, pg = [];
		for (var p = 0; p < pages; p++) { var o = { page: id++, cont: id++, im: [] }; for (var j = p * per; j < Math.min(imgs.length, p * per + per); j++) o.im.push(id++); pg.push(o); }
		put(new Uint8Array([37, 80, 68, 70, 45, 49, 46, 52, 10, 37, 226, 227, 207, 211, 10]));
		offs[1] = off; put("1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
		offs[2] = off; put("2 0 obj\n<< /Type /Pages /Kids [" + pg.map(function (o) { return o.page + " 0 R"; }).join(" ") + "] /Count " + pages + " >>\nendobj\n");
		pg.forEach(function (o, p) {
			var xo = o.im.map(function (n, i) { return "/I" + i + " " + n + " 0 R"; }).join(" "), s = "";
			o.im.forEach(function (n, i) {
				var col = i % 3, row = Math.floor(i / 3), x = (mx + col * (bw + gap)) * K, y = PH - (my + row * (bh + gap) + bh) * K;
				s += "q " + f(bw * K) + " 0 0 " + f(bh * K) + " " + f(x) + " " + f(y) + " cm /I" + i + " Do Q\n";
				s += "q 0.72 0.77 0.75 RG 0.4 w [2 2] 0 d " + f(x - 1.5 * K) + " " + f(y - 1.5 * K) + " " + f((bw + 3) * K) + " " + f((bh + 3) * K) + " re S Q\n";
			});
			offs[o.page] = off; put(o.page + " 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " + PW + " " + PH + "] /Resources << /XObject << " + xo + " >> >> /Contents " + o.cont + " 0 R >>\nendobj\n");
			offs[o.cont] = off; put(o.cont + " 0 obj\n<< /Length " + enc.encode(s).length + " >>\nstream\n" + s + "\nendstream\nendobj\n");
			o.im.forEach(function (n, i) {
				var im = imgs[p * per + i];
				offs[n] = off; put(n + " 0 obj\n<< /Type /XObject /Subtype /Image /Width " + im.w + " /Height " + im.h + " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " + im.b.length + " >>\nstream\n");
				put(im.b); put("\nendstream\nendobj\n");
			});
		});
		var x = off, xr = "xref\n0 " + id + "\n0000000000 65535 f \n";
		for (var k = 1; k < id; k++) xr += ("0000000000" + offs[k]).slice(-10) + " 00000 n \n";
		put(xr + "trailer\n<< /Size " + id + " /Root 1 0 R >>\nstartxref\n" + x + "\n%%EOF\n");
		return new Blob(parts, { type: "application/pdf" });
	}

	/* ---------- выдача файла ---------- */
	function css() {
		if ($("pgbd-css")) return;
		var s = D.createElement("style"); s.id = "pgbd-css";
		s.textContent = ".pgbd{position:fixed;inset:0;z-index:4000;display:flex;align-items:flex-end;justify-content:center;background:rgba(0,0,0,.32);opacity:0;transition:opacity .2s ease}.pgbd.on{opacity:1}"
			+ ".pgbd-b{width:min(440px,100%);margin:0 8px calc(env(safe-area-inset-bottom,0px) + 8px);padding:20px 16px 12px;border-radius:24px;background:var(--cd,#fff);color:var(--tx,#0F2A26);font-family:" + FONT + ";display:flex;flex-direction:column;gap:6px;text-align:center;transform:translateY(16px);transition:transform .28s cubic-bezier(.32,.72,0,1)}.pgbd.on .pgbd-b{transform:none}"
			+ ".pgbd-b b{font-size:18px}.pgbd-b span{font-size:14px;line-height:1.4;color:var(--mu,#6B7A77)}.pgbd-b button{min-height:50px;margin-top:6px;border:0;border-radius:16px;font:600 16px/1 " + FONT + ";cursor:pointer;background:var(--ac,#024C46);color:var(--on,#fff)}.pgbd-b button.x{margin-top:0;background:transparent;color:var(--mu,#6B7A77)}"
			+ ".pgbd-t{position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom,0px) + 96px);z-index:4001;transform:translateX(-50%);max-width:calc(100vw - 32px);padding:12px 18px;border-radius:16px;background:#0F2A26;color:#fff;font:500 14px/1.35 " + FONT + ";box-shadow:0 8px 24px rgba(0,0,0,.2);text-align:center}"
			+ "@media(prefers-reduced-motion:reduce){.pgbd,.pgbd-b{transition:none}}";
		D.head.appendChild(s);
	}
	function $(id) { return D.getElementById(id); }
	function toast(t) {
		css();
		var e = D.createElement("div"); e.className = "pgbd-t"; e.setAttribute("role", "status"); e.textContent = t;
		D.body.appendChild(e); setTimeout(function () { e.remove(); }, 3600);
	}
	function mobile() { return /iPhone|iPad|iPod|Android/i.test(N.userAgent) || (N.maxTouchPoints > 1 && /Mac/.test(N.platform || "")); }
	function download(blob, name) {
		var u = URL.createObjectURL(blob), a = D.createElement("a");
		a.href = u; a.download = name; a.rel = "noopener"; a.style.display = "none";
		D.body.appendChild(a); a.click(); a.remove();
		setTimeout(function () { URL.revokeObjectURL(u); }, 60000);
		toast("Файл скачан: " + name);
	}
	function give(blob, name, what) {
		var file = null;
		try { file = new File([blob], name, { type: blob.type }); } catch (e) { file = null; }
		if (!(file && mobile() && N.canShare && N.share && N.canShare({ files: [file] }))) return download(blob, name);
		/* меню «Поделиться» открывается только по нажатию — поэтому готовый файл отдаём отдельной кнопкой */
		css();
		var o = D.createElement("div"); o.className = "pgbd"; o.setAttribute("role", "dialog"); o.setAttribute("aria-label", "Файл готов");
		o.innerHTML = '<div class="pgbd-b"><b></b><span></span><button type="button" class="go"></button><button type="button" class="x">Закрыть</button></div>';
		o.querySelector("b").textContent = what;
		o.querySelector("span").textContent = "Дальше выберите «Сохранить в Файлы», «Напечатать» или отправьте в WhatsApp.";
		o.querySelector(".go").textContent = "Сохранить или отправить";
		function close() { o.classList.remove("on"); setTimeout(function () { o.remove(); }, 220); }
		o.addEventListener("click", function (e) {
			if (e.target === o || e.target.closest(".x")) return close();
			if (!e.target.closest(".go")) return;
			N.share({ files: [file], title: name }).then(close, function (er) {
				if (er && er.name === "AbortError") return;
				close(); download(blob, name);
			});
		});
		D.body.appendChild(o);
		requestAnimationFrame(function () { o.classList.add("on"); });
	}
	function data(scope) {
		return Array.prototype.slice.call((scope || D).querySelectorAll(".pgb[data-b]")).map(function (e) {
			try { return JSON.parse(e.getAttribute("data-b")); } catch (er) { return null; }
		}).filter(Boolean);
	}
	function busy(btn, on) {
		if (!btn) return;
		if (on) { btn._t = btn.innerHTML; btn.disabled = true; btn.setAttribute("aria-busy", "true"); var s = btn.querySelector("span"); if (s) s.textContent = "Готовлю файл…"; else btn.textContent = "Готовлю файл…"; }
		else { btn.disabled = false; btn.removeAttribute("aria-busy"); if (btn._t) btn.innerHTML = btn._t; }
	}
	function safe(s) { return String(s || "").replace(/[^A-Za-z0-9\-]+/g, "") || "badge"; }

	var PGB = {
		pdf: function (list, btn) {
			if (!list.length) return Promise.resolve();
			busy(btn, true);
			var out = [], i = 0;
			function next() {
				if (i >= list.length) return Promise.resolve();
				return render(list[i++], 660, "image/jpeg").then(function (c) { out.push({ w: c.width, h: c.height, b: bytes(c) }); c.width = c.height = 1; return next(); });
			}
			return next().then(function () {
				give(pdf(out), list.length === 1 ? "PetGo-badge-" + safe(list[0].id) + ".pdf" : "PetGo-badges.pdf", "PDF готов");
			}).catch(function () { toast("Не получилось собрать файл. Попробуйте ещё раз."); }).then(function () { busy(btn, false); });
		},
		png: function (b, btn) {
			busy(btn, true);
			return render(b, 1080, "image/png").then(function (c) {
				return new Promise(function (ok) { c.toBlob(ok, "image/png"); });
			}).then(function (blob) {
				if (!blob) throw new Error("png");
				give(blob, "PetGo-badge-" + safe(b.id) + ".png", "Картинка готова");
			}).catch(function () { toast("Не получилось сохранить картинку. Попробуйте ещё раз."); }).then(function () { busy(btn, false); });
		}
	};
	window.PGB = PGB;

	D.addEventListener("click", function (e) {
		var t = e.target.closest && e.target.closest("[data-pgb-pdf],[data-pgb-png],[data-pgb-print]");
		if (!t || t.disabled) return;
		e.preventDefault();
		var scope = t.closest("[data-pgb-scope]") || D, list = data(scope);
		if (t.hasAttribute("data-pgb-png")) return list.length && PGB.png(list[0], t);
		/* «Распечатать»: на iPhone в приложении с экрана «Домой» окно печати браузера может не открываться —
		   там печать идёт через тот же PDF (в меню «Поделиться» есть «Напечатать») */
		if (t.hasAttribute("data-pgb-print") && !(N.standalone === true || (window.matchMedia && matchMedia("(display-mode: standalone)").matches && /iPhone|iPad|iPod/.test(N.userAgent)))) return window.print();
		PGB.pdf(list, t);
	});
})();
