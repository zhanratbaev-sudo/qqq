/* PetGo Кабинет 27.0 — приложение: вкладки без перезагрузки, работа без интернета, чат, календарь, уведомления */
(function () {
	"use strict";
	var D = document, R = D.documentElement, W = window;
	var root = null, CFG = {}, L = {};
	var PC = "pgc-pages", IC = "pgc-img", KEEP = ["pg", "st", "rs", "rl"];

	function $(i) { return D.getElementById(i); }
	function qa(s, r) { return Array.prototype.slice.call((r || D).querySelectorAll(s)); }
	function el(t, c, x) { var e = D.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; }
	function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
	function ls(k, v) { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) { } return null; }
	function parse(html) { return new DOMParser().parseFromString(html, "text/html"); }
	function readCfg(doc) { var n = (doc || D).getElementById("pgc-cfg"); try { return n ? JSON.parse(n.textContent) : {}; } catch (e) { return {}; } }
	function abs(u) { return new URL(u, location.href).href; }
	function post(data, files) {
		var fd = new FormData();
		for (var k in data) fd.append(k, data[k]);
		if (files) for (var f in files) fd.append(f, files[f], "photo.jpg");
		return fetch(CFG.aj, { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); });
	}
	function hasCaches() { return "caches" in W; }

	/* ---------- адреса вкладок ---------- */
	function isApp(u) {
		if (!CFG.page) return false;
		var a = new URL(u, location.href), h = new URL(CFG.page);
		if (a.origin !== h.origin || a.pathname !== h.pathname) return false;
		var pid = h.searchParams.get("page_id");
		return !pid || a.searchParams.get("page_id") === pid;
	}
	function cleanKey(u) {
		var a = new URL(u, location.href), o = new URL(CFG.page);
		KEEP.forEach(function (k) { if (a.searchParams.has(k)) o.searchParams.set(k, a.searchParams.get(k)); });
		return o.href;
	}
	function isPlain(u) {
		var x = false;
		new URL(u, location.href).searchParams.forEach(function (v, k) { if (KEEP.indexOf(k) < 0 && k !== "page_id") x = true; });
		return !x;
	}

	/* ---------- тема ---------- */
	function themePref() { return ls("pgc_theme") || "auto"; }
	function applyTheme(user) {
		/* 34.4: смена темы — одним кадром, без того чтобы каждый цвет менялся со своей скоростью */
		if (user) { R.classList.add("pgc-theming"); requestAnimationFrame(function () { requestAnimationFrame(function () { R.classList.remove("pgc-theming"); }); }); }
		var t = themePref(), d = t === "dark" || (t !== "light" && W.matchMedia && matchMedia("(prefers-color-scheme: dark)").matches);
		R.classList.toggle("dark", d);
		var m = D.querySelector('meta[name="theme-color"]');
		if (m) m.setAttribute("content", d ? "#000000" : "#f2f2f7");
		qa("#pgc-theme-seg [data-theme]").forEach(function (b) { b.classList.toggle("on", b.getAttribute("data-theme") === t); });
		applyGlass();
	}
	/* iOS 27: прозрачность стекла (0 — тонированное, 100 — прозрачное) */
	function glassPref() { var g = parseInt(ls("pgc_glass"), 10); return isNaN(g) ? 50 : Math.max(0, Math.min(100, g)); }
	function applyGlass() {
		var g = glassPref(), r = $("pgc-glass");
		R.style.setProperty("--gu", g / 100);
		if (r) r.value = g;
		qa(".pgc input[type=range]").forEach(rangeFill);
	}
	function rangeFill(r) {
		var lo = parseFloat(r.min) || 0, hi = parseFloat(r.max); if (isNaN(hi)) hi = 100;
		r.style.setProperty("--rv", (hi > lo ? (parseFloat(r.value) - lo) / (hi - lo) * 100 : 0) + "%");
	}
	D.addEventListener("input", function (e) {
		var r = e.target;
		if (!r || r.type !== "range") return;
		rangeFill(r);
		if (r.id === "pgc-glass") { ls("pgc_glass", String(r.value)); R.style.setProperty("--gu", r.value / 100); }
	});
	if (W.matchMedia) { try { matchMedia("(prefers-color-scheme: dark)").addEventListener("change", applyTheme); } catch (e) { } }

	/* ---------- всплывающие сообщения ---------- */
	function toast(msg, bad) {
		var w = $("pgc-toasts");
		if (!w || !msg) return;
		var t = el("div", "pgc-toast" + (bad ? " err" : ""), msg);
		w.appendChild(t);
		requestAnimationFrame(function () { requestAnimationFrame(function () { t.classList.add("in"); }); });
		setTimeout(function () { t.classList.remove("in"); setTimeout(function () { t.remove(); }, 300); }, bad ? 5000 : 2600);
	}
	function flashes(scope) {
		qa(".pgc-msg[data-flash]", scope).forEach(function (m) { toast(m.textContent, m.classList.contains("err")); m.remove(); });
	}

	/* ---------- окна, меню, блокировка прокрутки ---------- */
	function lockSync() {
		var on = !!D.querySelector(".pgc-modal.open,.pgc-chat.open,.pgc-ov.open,.pgc-lb.open,.pgc-sheet:not([hidden])");
		R.classList.toggle("pgc-lock", on);
	}
	function closeMenus(except) {
		qa(".pgc-menu.open,.pgc-fabm.open").forEach(function (x) { if (x !== except) x.classList.remove("open"); });
		qa("[data-menu][aria-expanded=true]").forEach(function (b) { if (!except || b.getAttribute("data-menu") !== except.id) b.setAttribute("aria-expanded", "false"); });
		qa(".pgc-calt.open").forEach(function (b) { if (!except) b.classList.remove("open"); });
	}
	function openModal(m) {
		if (!m) return;
		closeMenus();
		m.classList.add("open");
		lockSync();
		if (m.id === "pgc-m-set") pushUI();
	}
	function closeModal(m) {
		if (!m) return;
		m.classList.remove("open");
		lockSync();
		/* убрать из адреса одноразовые параметры (правка питомца, сотрудника), чтобы окно не открылось снова */
		var u = new URL(location.href), ch = false;
		["edit", "edit_staff", "from_req"].forEach(function (k) { if (u.searchParams.has(k)) { u.searchParams.delete(k); ch = true; } });
		if (ch) history.replaceState(history.state, "", u.href);
	}
	function closeModals() { qa(".pgc-modal.open").forEach(function (m) { m.classList.remove("open"); }); lockSync(); }

	/* ---------- обратный отсчёт и линия времени ---------- */
	function fm(a) { var h = Math.floor(a / 3600), m = Math.floor(a % 3600 / 60); return (h ? h + " " + L.h + " " : "") + m + " " + L.m; }
	function tick() {
		var n = Math.floor(Date.now() / 1000);
		qa(".pgc-cd").forEach(function (e) {
			var df = (+e.getAttribute("data-ts")) - n, k = e.getAttribute("data-k");
			if (k === "e") e.textContent = df >= 0 ? L.ret + " " + fm(df) : L.up;
			else e.textContent = df >= 0 ? L.left + " " + fm(df) : L.late + " " + fm(-df);
			e.classList.toggle("late", df < 0);
		});
		qa(".pgc-wa").forEach(function (e) { e.classList.toggle("hot", (+e.getAttribute("data-end")) - n <= 1200); });
		qa(".pgc-prog").forEach(function (p) {
			var s = +p.getAttribute("data-s"), en = +p.getAttribute("data-e"), k = Math.max(0, Math.min(1, (n - s) / (en - s)));
			/* 34.4: полоса и точка двигаются transform-ом (scaleX и сдвиг), а не шириной и left */
			p.style.setProperty("--k", k.toFixed(4));
			p.classList.toggle("late", n > en);
		});
	}

	/* ---------- фото: уменьшаем перед отправкой ---------- */
	function shrink(file) {
		return new Promise(function (res, rej) {
			if (!file || !/^image\//.test(file.type || "image/")) { rej(); return; }
			var u = URL.createObjectURL(file), im = new Image();
			im.onload = function () {
				var k = Math.min(1, 1600 / Math.max(im.naturalWidth, im.naturalHeight)), c = D.createElement("canvas");
				c.width = Math.round(im.naturalWidth * k); c.height = Math.round(im.naturalHeight * k);
				c.getContext("2d").drawImage(im, 0, 0, c.width, c.height);
				URL.revokeObjectURL(u);
				c.toBlob(function (b) { b ? res(b) : rej(); }, "image/jpeg", 0.82);
			};
			im.onerror = function () { URL.revokeObjectURL(u); rej(); };
			im.src = u;
		});
	}

	/* ---------- сохранённые страницы (для мгновенного открытия и работы без интернета) ---------- */
	var mem = {}, curRaw = "", busyNav = 0;
	function cacheGet(key) {
		if (mem[key]) return Promise.resolve(mem[key]);
		if (!hasCaches()) return Promise.resolve(null);
		return caches.open(PC).then(function (c) { return c.match(key); }).then(function (r) { return r ? r.text() : null; }).catch(function () { return null; });
	}
	function cachePut(key, html) {
		/* одноразовые сообщения («Сохранено») в память не кладём */
		html = html.replace(/<div class="pgc-msg[^"]*" data-flash>[\s\S]*?<\/div>/g, "");
		mem[key] = html;
		if (!hasCaches()) return;
		caches.open(PC).then(function (c) { return c.put(key, new Response(html, { headers: { "Content-Type": "text/html; charset=utf-8", "X-PetGo-App": "1" } })); }).catch(function () { });
	}
	function cacheClear() {
		mem = {};
		var p = [];
		if (hasCaches()) { p.push(caches.delete(PC)); p.push(caches.delete(IC)); }
		return Promise.all(p).catch(function () { });
	}
	function rawOf(html) {
		var a = html.indexOf('id="pgc-in"'), b = html.indexOf('id="pgc-cfg"');
		return a > -1 && b > a ? html.slice(a, b) : html;
	}
	function fetchPage(u) {
		return fetch(u, { credentials: "same-origin", cache: "no-store", headers: { "X-PGC": "1" } }).then(function (r) {
			if (r.headers.get("X-PetGo-App") === "0") { hardReload(); throw new Error("auth"); }
			return r.text();
		});
	}
	function hardReload() { cacheClear().then(function () { location.reload(); }); }

	/* ---------- подменяем содержимое страницы ---------- */
	function openKeys(c) { var o = {}; qa("details[data-k]", c).forEach(function (e) { if (e.open) o[e.getAttribute("data-k")] = 1; }); return o; }
	function apply(doc, html, opts) {
		opts = opts || {};
		var n = doc.getElementById("pgc"), nin = doc.getElementById("pgc-in"), cin = $("pgc-in");
		var nc = readCfg(doc);
		if (!n || !nin || !cin || n.getAttribute("data-role") !== root.getAttribute("data-role") || nc.uid !== CFG.uid) { hardReload(); return false; }
		/* плагин обновился — новая версия стилей и скриптов: перезагружаем страницу целиком, иначе новое содержимое останется без оформления */
		if (nc.b && CFG.b && nc.b !== CFG.b) { hardReload(); return false; }
		var keep = opts.keep ? openKeys(cin) : null, y = W.scrollY;
		cin.innerHTML = nin.innerHTML;
		if (keep) qa("details[data-k]", cin).forEach(function (e) { if (keep[e.getAttribute("data-k")]) e.open = true; });
		cin.classList.remove("fresh", "enR", "enL");
		cin.style.transition = ""; cin.style.transform = ""; cin.style.opacity = "";
		if (!opts.keep) { void cin.offsetWidth; cin.classList.add(navDir > 0 ? "enR" : navDir < 0 ? "enL" : "fresh"); }
		navDir = 0;
		["pgc-mods"].forEach(function (id) { var a = doc.getElementById(id), b = $(id); if (a && b) b.innerHTML = a.innerHTML; });
		var nt = doc.querySelector(".pgc-top"), ct = D.querySelector(".pgc-top");
		if (nt && ct) ct.innerHTML = nt.innerHTML;
		var nn = doc.getElementById("pgc-nav"), cn = $("pgc-nav");
		if (nn && cn) { cn.innerHTML = nn.innerHTML; ind(false); }
		root.setAttribute("data-v", n.getAttribute("data-v"));
		root.setAttribute("data-pg", n.getAttribute("data-pg"));
		CFG = nc; L = CFG.L || L;
		curRaw = rawOf(html);
		runScripts(cin);
		page();
		if (opts.keep) W.scrollTo(0, y);
		flashes(cin);
		lockSync();
		return true;
	}
	function runScripts(c) {
		qa("script", c).forEach(function (s) {
			var t = s.getAttribute("type");
			if (t && t !== "text/javascript" && t !== "module") return;
			var x = D.createElement("script");
			x.textContent = s.textContent;
			s.parentNode.replaceChild(x, s);
		});
	}
	/* то, что нужно сделать после каждой смены содержимого */
	function page() {
		tick();
		applyTheme();
		if ($("pgc-calapp")) Cal.init();
		var u = new URL(location.href), o = parseInt(u.searchParams.get("o") || "0", 10);
		if (o) { u.searchParams.delete("o"); history.replaceState(history.state, "", u.href); Ov.open(o, "", true); }
		var ch = u.searchParams.get("c");
		if (ch) { u.searchParams.delete("c"); history.replaceState(history.state, "", u.href); Chat.auto(ch); }
		setTimeout(measure, 0);
	}

	/* ---------- переход по вкладкам: сразу, без белого экрана ---------- */
	function setActive(u) {
		var key = cleanKey(u), hit = null;
		qa("#pgc-nav a[data-pg]").forEach(function (a) { var on = cleanKey(a.href) === key; a.classList.toggle("on", on); if (on) hit = a; });
		if (hit) {
			var t = $("pgc-ttl");
			if (t) t.textContent = hit.getAttribute("data-t");
			root.setAttribute("data-pg", hit.getAttribute("data-pg"));
		}
		ind(true);
	}
	function loading() { var c = $("pgc-in"); if (c) c.innerHTML = '<div class="pgc-load"><i class="pgc-spin"></i></div>'; }
	/* направление перехода: вправо по меню — новая страница въезжает справа, как в iOS */
	var navDir = 0;
	function tabs() { return qa("#pgc-nav .pgc-navi a[data-pg]").filter(function (a) { return a.offsetParent !== null; }); }
	function tabIdx(u) { var k = cleanKey(u), l = tabs(); for (var i = 0; i < l.length; i++) if (cleanKey(l[i].href) === k) return i; return -1; }
	function go(u, push, dir) {
		u = abs(u);
		var fi = tabIdx(location.href), ti = tabIdx(u);
		navDir = dir || (fi > -1 && ti > -1 && fi !== ti ? (ti > fi ? 1 : -1) : 0);
		if (D.querySelector(".pgc-fe[data-dirty]") && !confirm(L.unsaved || "Изменения не сохранены. Уйти со страницы?")) { if (!push) history.pushState({ pgc: 1 }, "", location.href); return Promise.resolve(); }
		var seq = ++busyNav, plain = isPlain(u), key = cleanKey(u);
		closeMenus(); closeModals();
		if (Ov.isOpen()) Ov.close(true);
		if (Chat.isOpen()) Chat.close();
		setActive(u);
		if (push) history.pushState({ pgc: 1 }, "", u);
		W.scrollTo(0, 0);
		var shown = false;
		return (plain ? cacheGet(key) : Promise.resolve(null)).then(function (html) {
			if (seq !== busyNav) return;
			if (html) shown = apply(parse(html), html, {});
			else loading();
			return fetchPage(u).then(function (fresh) {
				if (seq !== busyNav) return;
				if (plain) cachePut(key, fresh);
				if (!shown || rawOf(fresh) !== curRaw) apply(parse(fresh), fresh, { keep: shown });
				else CFG = readCfg(parse(fresh));
				online(true);
			}).catch(function (e) {
				if (seq !== busyNav || (e && e.message === "auth")) return;
				online(false);
				if (!shown) { var c = $("pgc-in"); if (c) c.innerHTML = '<div class="pgc-emptyst"><svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M2 8.5a15 15 0 0 1 20 0M5.5 12a10 10 0 0 1 13 0M9 15.5a5 5 0 0 1 6 0M12 19h.01"/><path d="M3 3l18 18"/></svg><p>' + esc(L.offline) + "</p></div>"; }
			});
		});
	}
	/* тихо подтянуть свежие данные текущей вкладки */
	var refreshing = false, pending = false;
	function refresh() {
		if (refreshing) return Promise.resolve();
		refreshing = true;
		var u = location.href;
		return fetchPage(u).then(function (html) {
			if (isPlain(u)) cachePut(cleanKey(u), html);
			if (rawOf(html) !== curRaw) apply(parse(html), html, { keep: true });
			else { CFG = readCfg(parse(html)); }
			online(true);
		}).catch(function () { online(false); }).then(function () { refreshing = false; });
	}
	function canAuto() {
		if (D.hidden || D.querySelector(".pgc-modal.open,.pgc-fe[data-dirty]")) return false;
		var a = D.activeElement;
		return !(a && $("pgc-in") && $("pgc-in").contains(a) && /INPUT|TEXTAREA|SELECT/.test(a.tagName));
	}
	/* заранее сохранить остальные вкладки — чтобы они открывались мгновенно и без интернета */
	function warm() {
		if (!navigator.onLine || !CFG.warm) return;
		var last = +(ls("pgc_warm") || 0);
		if (Date.now() - last < 600000) return;
		ls("pgc_warm", String(Date.now()));
		var list = CFG.warm.filter(function (u) { return cleanKey(u) !== cleanKey(location.href); }), i = 0;
		(function next() {
			if (i >= list.length || D.hidden) return;
			var u = list[i++];
			fetchPage(u).then(function (h) { cachePut(cleanKey(u), h); }).catch(function () { }).then(function () { setTimeout(next, 700); });
		})();
	}
	function online(on) {
		var b = $("pgc-off");
		if (b) b.hidden = on;
	}

	/* ---------- индикатор под активной вкладкой ---------- */
	function ind(anim) {
		var nav = $("pgc-nav"), i = nav && nav.querySelector(".pgc-ind"), a = nav && nav.querySelector(".pgc-navi a.on");
		if (!i) return;
		if (!a || !a.offsetWidth) { i.style.width = "0"; return; }
		if (!anim) i.style.transition = "none";
		i.style.width = a.offsetWidth + "px";
		i.style.transform = "translateX(" + a.offsetLeft + "px)";
		if (!anim) { void i.offsetWidth; i.style.transition = ""; }
	}
	function measure() {
		var t = D.querySelector(".pgc-top");
		if (t) R.style.setProperty("--toph", t.offsetHeight + "px");
		ind(false);
	}

	/* ---------- отправка форм без перезагрузки ---------- */
	function submitForm(f) {
		if (f.requestSubmit) f.requestSubmit();
		else f.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
	}
	function busy(b, on) { if (b) { b.classList.toggle("busy", on); b.disabled = on; } }
	function onSubmit(e) {
		var f = e.target;
		if (!f || f.tagName !== "FORM" || e.defaultPrevented) return;
		if (f.id === "pgc-lform") { login(e, f); return; }
		if (f.id === "pgc-mform") { mlogin(e, f); return; }
		var act = f.getAttribute("action") || "";
		if ((f.getAttribute("method") || "").toLowerCase() !== "post" || !/admin-post\.php/.test(act)) return;
		e.preventDefault();
		if (!navigator.onLine) { toast(L.offline_act, true); return; }
		var btn = e.submitter || f.querySelector("[type=submit]"), fd = new FormData(f), isLang = (fd.get("action") === "petgo_lang_set");
		if (e.submitter && e.submitter.name) fd.append(e.submitter.name, e.submitter.value);
		busy(btn, true);
		var files = qa("input[type=file]", f).filter(function (i) { return i.files && i.files.length; });
		Promise.all(files.map(function (i) {
			return shrink(i.files[0]).then(function (b) { fd.delete(i.name); fd.append(i.name, b, "photo.jpg"); }).catch(function () { });
		})).then(function () {
			return fetch(act, { method: "POST", body: fd, credentials: "same-origin" });
		}).then(function (r) {
			return r.text().then(function (t) { return { r: r, t: t }; });
		}).then(function (x) {
			if (isLang) { hardReload(); return; }
			var doc = parse(x.t);
			if (!doc.getElementById("pgc-in")) {
				var msg = (doc.body && doc.body.textContent || "").replace(/\s+/g, " ").trim().slice(0, 160);
				toast(msg || L.neterr, true);
				if (/expired|устар|истек/i.test(msg)) refresh();
				return;
			}
			closeModals();
			if (Ov.isOpen()) Ov.close(true);
			var u = x.r.url;
			if (isApp(u)) history.pushState({ pgc: 1 }, "", u);
			setActive(u);
			apply(doc, x.t, {});
			if (isPlain(u)) cachePut(cleanKey(u), x.t);
		}).catch(function () { toast(L.neterr, true); }).then(function () { busy(btn, false); });
	}
	function login(e, f) {
		e.preventDefault();
		var b = f.querySelector("[type=submit]"), m = $("pgc-lerr");
		if (m) m.hidden = true;
		busy(b, true);
		var fd = new FormData(f);
		fd.append("wp-submit", "1");
		fetch(f.getAttribute("action"), { method: "POST", body: fd, credentials: "same-origin" }).then(function (x) {
			if (x.url.indexOf("wp-login.php") > -1) throw 1;
			return cacheClear().then(function () { location.href = x.url; });
		}).catch(function () { busy(b, false); if (m) m.hidden = false; });
	}

	/* вход по коду на почту: шаг 1 — почта, шаг 2 — код из письма */
	function mstep(f, two) {
		qa("[data-step2]", f).forEach(function (n) { n.hidden = !two; });
		f.email.readOnly = two;
		var b = f.querySelector("[type=submit]");
		b.textContent = b.getAttribute(two ? "data-l2" : "data-l1");
		f.setAttribute("data-step", two ? "2" : "1");
		if (two) setTimeout(function () { f.code.focus(); }, 50);
	}
	function mlogin(e, f) {
		e.preventDefault();
		var b = f.querySelector("[type=submit]"), m = $("pgc-lerr"), two = f.getAttribute("data-step") === "2";
		if (m) m.hidden = true;
		var fd = new FormData();
		fd.append("action", two ? "petgo_mcode_check" : "petgo_mcode_send");
		fd.append("n", f.n.value); fd.append("email", f.email.value.trim());
		if (two) fd.append("code", f.code.value);
		busy(b, true);
		fetch(f.getAttribute("data-aj"), { method: "POST", body: fd, credentials: "same-origin" }).then(function (r) { return r.json(); }).then(function (j) {
			busy(b, false);
			if (!j || !j.ok) { if (m) { m.textContent = (j && j.m) || L.neterr || "Error"; m.hidden = false; } return; }
			if (!two) { mstep(f, true); return; }
			return cacheClear().then(function () { location.href = j.to || f.getAttribute("data-to"); });
		}).catch(function () { busy(b, false); if (m) { m.textContent = L.neterr || "Network error"; m.hidden = false; } });
	}

	/* ---------- нажатия ---------- */
	var W8 = null;
	function onClick(e) {
		var t = e.target, b;
		if (e.defaultPrevented) return;
		b = t.closest("[data-theme]");
		if (b) { ls("pgc_theme", b.getAttribute("data-theme")); applyTheme(true); return; }
		b = t.closest("[data-lmode]");
		if (b) {
			var cm = b.getAttribute("data-lmode") === "code", er = $("pgc-lerr");
			qa("#pgc-lmode [data-lmode]").forEach(function (x) { x.classList.toggle("on", x === b); });
			if ($("pgc-lform")) $("pgc-lform").hidden = cm;
			if ($("pgc-mform")) $("pgc-mform").hidden = !cm;
			if (er) er.hidden = true;
			return;
		}
		b = t.closest("[data-mreset]");
		if (b) { var mf = $("pgc-mform"); mf.code.value = ""; mstep(mf, false); mf.email.focus(); return; }
		b = t.closest("[data-lang]");
		if (b) { D.cookie = "petgo_lang=" + b.getAttribute("data-lang") + ";path=/;max-age=31536000;samesite=lax"; location.reload(); return; }
		b = t.closest("[data-eye]");
		if (b) { var pi = b.parentNode.querySelector("input"); pi.type = pi.type === "password" ? "text" : "password"; b.classList.toggle("on", pi.type === "text"); return; }
		b = t.closest("[data-open]");
		if (b) { e.preventDefault(); openModal($(b.getAttribute("data-open"))); return; }
		b = t.closest("[data-close]");
		if (b) { closeModal(b.closest(".pgc-modal")); return; }
		if (t.classList && t.classList.contains("pgc-modal")) { closeModal(t); return; }
		b = t.closest("[data-menu]");
		if (b) {
			var mm = $(b.getAttribute("data-menu"));
			if (mm) { var was = mm.classList.contains("open"); closeMenus(); if (!was) { mm.classList.add("open"); b.setAttribute("aria-expanded", "true"); } }
			return;
		}
		b = t.closest("[data-f]");
		if (b) {
			var v = b.getAttribute("data-f");
			qa("[data-f]").forEach(function (c) { c.classList.toggle("on", c === b); });
			qa("[data-sp]").forEach(function (c) { c.style.display = (v === "*" || c.getAttribute("data-sp") === v) ? "" : "none"; });
			return;
		}
		b = t.closest("[data-logout]");
		if (b) { e.preventDefault(); logout(b.href); return; }
		b = t.closest(".pgc-arrive");
		if (b) { e.preventDefault(); arrive(b); return; }
		b = t.closest(".pgc-whyb");
		if (b) {
			e.preventDefault();
			var q = b.getAttribute("data-q");
			if (q && !confirm(q)) return;
			W8 = b.closest("form");
			$("pgc-why-t").value = "";
			openModal($("pgc-m-why"));
			return;
		}
		if (t.closest("#pgc-why-go")) {
			var wv = $("pgc-why-t").value.trim();
			if (wv.length < 5) { alert(L.short); return; }
			if (W8) { W8.querySelector("[name=why]").value = wv; closeModal($("pgc-m-why")); submitForm(W8); }
			return;
		}
		b = t.closest(".pgc-pen");
		if (b) { var p = b.closest(".pgc-ovb").querySelector(".pgc-edp"); if (p) { p.hidden = !p.hidden; b.classList.toggle("on", !p.hidden); } return; }
		b = t.closest("[data-ov]");
		if (b && !t.closest("a,button,form,input") && !b.closest("#pgc-ovb")) { Ov.open(b.getAttribute("data-ov"), b.getAttribute("data-name")); return; }
		b = t.closest("[data-pwa-install]");
		if (b) { install(); return; }
		if (t.closest("[data-push-allow]")) { sheet(false); pushOn(); return; }
		if (t.closest("[data-push-later]")) { sheet(false); ls("pgc_push_later", String(Date.now())); return; }
		if (t.closest("#pgc-push-tg")) { pushToggle(); return; }
		var a = t.closest("a");
		if (a) {
			var h = a.getAttribute("href") || "";
			if (a.querySelector("img") && /[?&]action=petgo_img|\.(jpe?g|png|webp)(\?|$)/i.test(h)) { e.preventDefault(); Lb.open(a.href); return; }
			if (!a.target && !a.hasAttribute("download") && !a.hasAttribute("data-ext") && h && h.charAt(0) !== "#" && !e.metaKey && !e.ctrlKey && !e.shiftKey && isApp(a.href) && root && root.getAttribute("data-role")) {
				e.preventDefault();
				go(a.href, true);
				return;
			}
		}
		if (!t.closest(".pgc-menu,.pgc-fabm")) closeMenus();
	}
	/* после нажатия в левом меню убираем фокус, чтобы меню свернулось */
	D.addEventListener("click", function (e) { var n = e.target.closest && e.target.closest("#pgc-nav a,#pgc-nav button"); if (n && n.blur) setTimeout(function () { n.blur(); }, 0); });
	function onChange(e) {
		var t = e.target;
		if (t.matches && t.matches("select[data-autosubmit]")) { submitForm(t.form); return; }
		if (t.matches && t.matches("input[type=file][data-preview]") && t.files && t.files[0]) {
			var u = URL.createObjectURL(t.files[0]), lb = t.closest("label");
			if (!lb) return;
			var av = lb.querySelector(".pgc-av");
			if (av) { av.textContent = ""; var im = el("img"); im.src = u; im.alt = ""; av.appendChild(im); }
			var sh = lb.querySelector(".pgc-shot-in");
			if (sh) { sh.style.backgroundImage = "url(" + u + ")"; lb.classList.add("has"); }
		}
	}

	/* ---------- «Я пришёл»: местоположение обязательно ---------- */
	function arrive(b) {
		var f = b.closest("form");
		if (!navigator.geolocation) { alert(L.noloc); return; }
		var txt = b.innerHTML;
		b.disabled = true; b.textContent = L.locating;
		navigator.geolocation.getCurrentPosition(function (p) {
			f.lat.value = p.coords.latitude; f.lng.value = p.coords.longitude; f.acc.value = Math.round(p.coords.accuracy);
			b.disabled = false; b.innerHTML = txt;
			submitForm(f);
		}, function () { alert(L.denied); b.disabled = false; b.innerHTML = txt; }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
	}

	/* ---------- выход: стираем сохранённые данные с телефона ---------- */
	function logout(href) {
		var done = function () { cacheClear().then(function () { ["pgc_warm", "pgc_cal", "pgc_push_sent"].forEach(function (k) { ls(k, null); }); location.href = href; }); };
		pushOff(true).then(done, done);
	}

	/* ---------- окно заказа ---------- */
	var Ov = (function () {
		var O, B, T, cur = 0;
		function els() { O = $("pgc-ov"); B = $("pgc-ovb"); T = $("pgc-ovt"); return !!O; }
		function render(h) { B.innerHTML = h; B.scrollTop = 0; maps(); tick(); }
		function open(id, name, quiet) {
			if (!els()) return;
			cur = +id;
			T.textContent = name || "";
			B.innerHTML = '<div class="pgc-load"><i class="pgc-spin"></i></div>';
			if (quiet) { O.style.transition = "none"; }
			O.classList.add("open"); O.setAttribute("aria-hidden", "false");
			if (quiet) { void O.offsetWidth; O.style.transition = ""; }
			lockSync();
			var key = CFG.page + (CFG.page.indexOf("?") > -1 ? "&" : "?") + "pgc_cache=order-" + cur;
			cacheGet(key).then(function (c) { if (c && cur === +id) { var d = JSON.parse(c); T.textContent = d.t; render(d.h); } });
			post({ action: "petgo_order", n: CFG.no, id: cur }).then(function (d) {
				if (cur !== +id) return;
				if (!d.ok) { close(); return; }
				T.textContent = d.t;
				render(d.h);
				cachePut(key, JSON.stringify({ t: d.t, h: d.h }));
			}).catch(function () { if (B.querySelector(".pgc-load")) B.innerHTML = '<div class="pgc-emptyst"><p>' + esc(L.offline) + "</p></div>"; });
		}
		function close(quiet) {
			if (!els()) return;
			cur = 0;
			if (quiet) { O.style.transition = "none"; }
			O.classList.remove("open"); O.setAttribute("aria-hidden", "true");
			if (quiet) { void O.offsetWidth; O.style.transition = ""; }
			lockSync();
			if (pending && canAuto()) { pending = false; refresh(); }
		}
		function maps() {
			qa(".pgc-mapd", B).forEach(function (d) { if (d.open) loadMap(d); });
		}
		function loadMap(d) {
			var m = d.querySelector(".pgc-map");
			if (!m || m.firstChild) return;
			var f = D.createElement("iframe");
			f.src = d.getAttribute("data-src"); f.loading = "lazy"; f.setAttribute("referrerpolicy", "no-referrer"); f.title = "map";
			m.appendChild(f);
		}
		D.addEventListener("toggle", function (e) { var d = e.target; if (d.classList && d.classList.contains("pgc-mapd") && d.open) loadMap(d); }, true);
		return { open: open, close: close, isOpen: function () { return els() && O.classList.contains("open"); }, bind: function () { if (!els()) return; $("pgc-ovback").addEventListener("click", function () { close(); }); swipeBack(O, function () { close(); }); } };
	})();

	/* ---------- просмотр фото ---------- */
	var Lb = {
		open: function (u) { var lb = $("pgc-lb"); if (!lb) return; $("pgc-lbimg").src = u; lb.classList.add("open"); lockSync(); },
		close: function () { var lb = $("pgc-lb"); if (lb) lb.classList.remove("open"); lockSync(); },
		bind: function () {
			if (!$("pgc-lb")) return;
			$("pgc-lbback").addEventListener("click", Lb.close);
			$("pgc-lbi").addEventListener("click", function (e) { if (e.target.id !== "pgc-lbimg") Lb.close(); });
		}
	};

	/* свайп вправо от края — назад (как в приложениях) */
	function swipeBack(P, cb, target) {
		var sx = 0, sy = 0, dx = 0, dr = false, ok = false, M = P;
		P.addEventListener("touchstart", function (e) { var t = e.touches[0]; sx = t.clientX; sy = t.clientY; dx = 0; dr = false; M = (typeof target === "function" ? target() : target) || P; ok = e.touches.length === 1 && sx < 40 && !e.target.closest("input,textarea,select,.pgc-calv"); }, { passive: true });
		P.addEventListener("touchmove", function (e) {
			if (!ok) return;
			var t = e.touches[0], x = t.clientX - sx, y = t.clientY - sy;
			if (!dr) { if (x > 10 && x > Math.abs(y) * 1.4) { dr = true; M.classList.add("drag"); } else if (Math.abs(y) > 10) { ok = false; return; } }
			if (dr) { dx = Math.max(0, x); M.style.transform = "translateX(" + dx + "px)"; }
		}, { passive: true });
		P.addEventListener("touchend", function () {
			if (!dr) return;
			dr = false; M.classList.remove("drag"); M.style.transform = "";
			if (dx > M.offsetWidth * 0.28) cb();
		});
	}

	/* ---------- клавиатура: поле ввода прямо над ней, без пустой полосы ---------- */
	var vv = W.visualViewport;
	function vp() {
		/* своя клавиатура не уменьшает окно браузера — вычитаем её высоту сами */
		var kbh = W.PGKB_H || 0;
		var h = (vv ? vv.height : W.innerHeight) - kbh, top = vv ? vv.offsetTop : 0;
		R.style.setProperty("--vvh", h + "px");
		R.style.setProperty("--vvt", top + "px");
		var a = D.activeElement, typing = a && /INPUT|TEXTAREA|SELECT/.test(a.tagName) && !/checkbox|radio|file|button|submit/.test(a.type || "");
		R.classList.toggle("kb", kbh > 0 || (!!typing && (W.innerHeight - h > 120 || /Android|iPhone|iPad/i.test(navigator.userAgent))));
		if (Chat.isOpen()) {
			/* 34.3: окно чата плавно меняет высоту вместе с клавиатурой — лента всё это время прижата вниз */
			Chat.stick();
			var t0 = Date.now();
			cancelAnimationFrame(vpRaf);
			(function f() { Chat.stick(); if (Date.now() - t0 < 420) vpRaf = requestAnimationFrame(f); })();
		}
	}
	var vpRaf = 0;
	if (vv) { vv.addEventListener("resize", vp); vv.addEventListener("scroll", vp); }
	W.addEventListener("pgkb", vp);

	/* ---------- своя клавиатура PetGo (как на сайте): РУ · ҚАЗ · EN, цифры; в цветах и теме приложения ---------- */
	if (W.PGKB) {
		var kl = (R.getAttribute("lang") || "ru").toLowerCase(), klang = kl.indexOf("kk") === 0 || kl.indexOf("kz") === 0 ? "kz" : (kl.indexOf("en") === 0 ? "en" : "ru");
		var KT = {
			kz: { "Системная клавиатура": "Жүйелік пернетақта", "Готово": "Дайын", "Стереть": "Өшіру", "Заглавные": "Бас әріптер", "Далее": "Келесі", "Пробел": "Бос орын", "Язык клавиатуры": "Пернетақта тілі" },
			en: { "Системная клавиатура": "System keyboard", "Готово": "Done", "Стереть": "Delete", "Заглавные": "Shift", "Далее": "Next", "Пробел": "Space", "Язык клавиатуры": "Keyboard language" }
		}[klang] || {};
		/* 34.2: клавиатура PetGo во всех полях приложения, включая логин и пароль — без полоски браузера над клавиатурой */
		var NOT = ":not([readonly]):not([data-nokb])";
		W.PGKB.init({
			sel: ["text", "tel", "email", "number", "search", "url", "password"].map(function (t) { return "input[type=" + t + "]" + NOT; }).concat(["input:not([type])" + NOT, "textarea" + NOT]).join(","),
			theme: "app", lang: klang, telPrefix: false, noSys: true,
			T: function (s) { return KT[s] || s; },
			pad: function () { return D.body; }
		});
	}
	W.addEventListener("resize", function () { vp(); measure(); });
	D.addEventListener("focusin", function () { setTimeout(vp, 50); });
	D.addEventListener("focusout", function () { setTimeout(vp, 120); });

	/* ---------- потяните вниз, чтобы обновить ---------- */
	function ptr() {
		var P = el("div", "pgc-ptr"), ps = 0, px = 0, pa = false, pd = 0;
		P.appendChild(el("i"));
		root.appendChild(P);
		D.addEventListener("touchstart", function (e) {
			pa = e.touches.length === 1 && W.scrollY <= 0 && !R.classList.contains("pgc-lock") && !e.target.closest(".pgc-nav,.pgc-top,input,textarea,select,.pgc-calv,.pgc-fe");
			if (pa) { ps = e.touches[0].clientY; px = e.touches[0].clientX; pd = 0; }
		}, { passive: true });
		D.addEventListener("touchmove", function (e) {
			if (!pa) return;
			var y = e.touches[0].clientY - ps, x = Math.abs(e.touches[0].clientX - px);
			if (y <= 0 || (pd === 0 && x > y) || W.scrollY > 0) { if (pd === 0) pa = false; return; }
			pd = Math.min(120, y * 0.5);
			P.style.opacity = Math.min(1, pd / 50);
			P.style.transform = "translateY(" + (pd - 50) + "px) rotate(" + (pd * 4) + "deg)";
		}, { passive: true });
		D.addEventListener("touchend", function () {
			if (!pa || !pd) { pa = false; return; }
			pa = false;
			if (pd >= 64) {
				P.classList.add("spin"); P.style.transform = "translateY(14px)";
				var t0 = Date.now();
				refresh().then(function () { setTimeout(reset, Math.max(0, 500 - (Date.now() - t0))); });
			} else reset();
			function reset() { P.classList.remove("spin"); P.style.opacity = 0; P.style.transform = ""; pd = 0; }
		});
	}

	/* ---------- свайп между вкладками: страница идёт за пальцем ---------- */
	function swipeTabs() {
		var sx = 0, sy = 0, dx = 0, t0 = 0, on = false, drag = false, w = 1, cin = null, prevT = null, nextT = null;
		function mobile() { return !(W.matchMedia && matchMedia("(min-width:1024px)").matches); }
		D.addEventListener("touchstart", function (e) {
			cin = $("pgc-in");
			on = !!cin && mobile() && e.touches.length === 1 && cin.contains(e.target) && !R.classList.contains("pgc-lock") &&
				!e.target.closest(".pgc-chips,.pgc-calapp,.pgc-tbl,.pgc-gal,.pgc-fe,.pgc-map,input,textarea,select,.pgc-menu");
			if (!on) return;
			var t = e.touches[0], l = tabs(), i = tabIdx(location.href);
			sx = t.clientX; sy = t.clientY; dx = 0; drag = false; t0 = Date.now(); w = cin.offsetWidth || 1;
			prevT = i > 0 ? l[i - 1] : null; nextT = i > -1 && i < l.length - 1 ? l[i + 1] : null;
			if (i < 0) on = false;
		}, { passive: true });
		D.addEventListener("touchmove", function (e) {
			if (!on) return;
			var t = e.touches[0], x = t.clientX - sx, y = t.clientY - sy;
			if (!drag) {
				if (Math.abs(x) > 12 && Math.abs(x) > Math.abs(y) * 1.4) { drag = true; cin.classList.remove("fresh", "enR", "enL"); cin.style.transition = "none"; }
				else if (Math.abs(y) > 12) { on = false; return; }
				else return;
			}
			var has = x < 0 ? nextT : prevT;
			dx = has ? x : x * 0.22;
			cin.style.transform = "translate3d(" + dx + "px,0,0)";
			cin.style.opacity = String(1 - Math.min(0.45, Math.abs(dx) / w * 0.6));
		}, { passive: true });
		D.addEventListener("touchend", function () {
			if (!on || !drag) { on = false; return; }
			on = false;
			var v = Math.abs(dx) / Math.max(1, Date.now() - t0), target = dx < 0 ? nextT : prevT;
			if (target && (Math.abs(dx) > w * 0.25 || (v > 0.45 && Math.abs(dx) > 40))) {
				var dir = dx < 0 ? 1 : -1;
				cin.style.transition = "transform .18s cubic-bezier(.4,0,1,1),opacity .18s";
				cin.style.transform = "translate3d(" + (-dir * w * 0.45) + "px,0,0)";
				cin.style.opacity = "0";
				setTimeout(function () { go(target.href, true, dir); }, 170);
			} else {
				cin.style.transition = "transform .42s cubic-bezier(.22,1,.36,1),opacity .3s";
				cin.style.transform = ""; cin.style.opacity = "";
			}
		});
	}

	/* ---------- уведомления и установка приложения ---------- */
	var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
	var standalone = (W.matchMedia && matchMedia("(display-mode: standalone)").matches) || navigator.standalone === true;
	var dp = null, swr = null;
	function pushOk() { return "serviceWorker" in navigator && "PushManager" in W && "Notification" in W && !!CFG.key; }
	function b64(s) { var p = "=".repeat((4 - s.length % 4) % 4), r = atob((s + p).replace(/-/g, "+").replace(/_/g, "/")), o = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) o[i] = r.charCodeAt(i); return o; }
	function swReady() { return swr ? navigator.serviceWorker.ready : Promise.reject(); }
	function getSub() { return swReady().then(function (r) { return r.pushManager.getSubscription(); }); }
	function sendSub(a, s) { return post({ action: "petgo_push_sub", n: CFG.np, "do": a, s: JSON.stringify(s) }); }
	function pushState() {
		if (!pushOk()) return Promise.resolve(ios && !standalone ? "ios" : "na");
		if (Notification.permission === "denied") return Promise.resolve("denied");
		return getSub().then(function (s) { return s && Notification.permission === "granted" ? "on" : "off"; }).catch(function () { return "off"; });
	}
	function pushOn() {
		if (!pushOk()) { toast(ios && !standalone ? L.push_ios_s : L.push_na, true); return Promise.resolve(false); }
		return Notification.requestPermission().then(function (p) {
			if (p !== "granted") { toast(L.push_den, true); return false; }
			return swReady().then(function (r) {
				return r.pushManager.getSubscription().then(function (s) { return s || r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(CFG.key) }); });
			}).then(function (s) { return sendSub("sub", s); }).then(function () { ls("pgc_push_sent", String(Date.now())); toast(L.push_on); return true; });
		}).catch(function () { toast(L.push_err, true); return false; }).then(function (r) { pushUI(); return r; });
	}
	function pushOff(quiet) {
		if (!pushOk()) return Promise.resolve();
		return getSub().then(function (s) {
			if (!s) return;
			var ep = s.endpoint;
			return s.unsubscribe().then(function () { return sendSub("unsub", { endpoint: ep }); });
		}).catch(function () { }).then(function () { if (!quiet) pushUI(); });
	}
	function pushToggle() { pushState().then(function (st) { if (st === "on") pushOff(); else pushOn(); }); }
	function pushUI() {
		var tg = $("pgc-push-tg"), sl = $("pgc-push-st");
		if (!tg) return;
		pushState().then(function (st) {
			tg.setAttribute("aria-checked", st === "on" ? "true" : "false");
			tg.disabled = st === "na" || st === "denied";
			if (sl) sl.textContent = st === "on" ? L.push_on : st === "denied" ? L.push_den : st === "na" ? L.push_na : st === "ios" ? L.push_ios_s : L.push_off;
		});
		var ir = $("pgc-inst-row");
		if (ir) ir.hidden = !dp;
	}
	function sheet(on, iosMode) {
		var s = $("pgc-push");
		if (!s) return;
		s.hidden = !on;
		if (on) {
			s.querySelector("[data-n]").hidden = !!iosMode;
			s.querySelector("[data-i]").hidden = !iosMode;
			s.querySelector("[data-push-allow]").hidden = !!iosMode;
		}
		lockSync();
	}
	/* сразу при открытии: просим разрешение на уведомления, если ещё не решено */
	function askPush() {
		var later = +(ls("pgc_push_later") || 0), wait = 3 * 86400000;
		pushState().then(function (st) {
			if (st === "on") {
				var sent = +(ls("pgc_push_sent") || 0);
				if (Date.now() - sent > 86400000) getSub().then(function (s) { if (s) sendSub("sub", s).then(function () { ls("pgc_push_sent", String(Date.now())); }); }).catch(function () { });
				return;
			}
			if (st === "off" && Notification.permission === "granted") { pushOn(); return; }
			if (Date.now() - later < wait) return;
			if (st === "off") sheet(true, false);
			else if (st === "ios") sheet(true, true);
		});
	}
	function install() {
		if (!dp) return;
		dp.prompt();
		var f = function () { dp = null; pushUI(); };
		dp.userChoice.then(f, f);
	}
	W.addEventListener("beforeinstallprompt", function (e) { e.preventDefault(); dp = e; pushUI(); });
	function sw() {
		if (!("serviceWorker" in navigator) || !CFG.sw) return Promise.resolve();
		return navigator.serviceWorker.register(CFG.sw, { scope: CFG.scope }).then(function (r) { swr = r; }).catch(function () { });
	}
	if ("serviceWorker" in navigator) {
		navigator.serviceWorker.addEventListener("message", function (e) { if (e.data && e.data.go) go(e.data.go, true); else if (e.data && e.data.tick) liveTick(); });
	}

	/* ============================================================
	   ЧАТ
	   ============================================================ */
	var Chat = (function () {
		var P, cur = 0, last = 0, tm = null, lt = null, subj = 0, info = null, LD = [], selM = false, sel = {}, msM = false, msel = {}, prev = null, own = false, sending = false;
		function api(o, d, files) { d = d || {}; d.action = "petgo_chat"; d.n = CFG.nc; d.op = o; return post(d, files); }
		function isDesk() { return W.matchMedia && matchMedia("(min-width:1024px)").matches; }
		function hue(s) { var h = 0; s = String(s); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h % 8; }
		function av(url, name, seed) {
			var a = el("span", "pgc-av" + (url ? "" : " c" + hue(seed || name)));
			if (url) { var i = el("img"); i.src = url; i.alt = ""; i.loading = "lazy"; a.appendChild(i); }
			else a.textContent = (String(name || "?").trim().charAt(0) || "?").toUpperCase();
			return a;
		}
		function badge(n) { qa(".pgc-cbd").forEach(function (x) { x.textContent = n; x.hidden = !(n > 0); }); }
		function draw() {
			var c = $("pgc-cl"), tot = 0;
			c.textContent = "";
			if (!LD.length) c.appendChild(el("p", "pgc-emptyst", L.none));
			LD.forEach(function (x) {
				tot += x.u;
				var b = el("button", "pgc-cr" + (cur === x.id ? " on" : ""));
				b.type = "button";
				if (selM) { var k = el("input"); k.type = "checkbox"; k.checked = !!sel[x.id]; k.tabIndex = -1; b.appendChild(k); }
				b.appendChild(av(x.av, x.t, x.id));
				var y = el("div", "pgc-crb"), a = el("div", "pgc-crt"), l2 = el("div", "pgc-crl"), pv = el("span", "pgc-cl2");
				a.appendChild(el("b", "", x.t));
				if (x.at) a.appendChild(el("span", "", x.at));
				if (x.w) { pv.appendChild(el("em", "", x.w + ": ")); }
				pv.appendChild(D.createTextNode(x.l || x.sub || ""));
				l2.appendChild(pv);
				if (x.u) l2.appendChild(el("i", "pgc-bd", x.u));
				y.appendChild(a); y.appendChild(l2); b.appendChild(y);
				b.onclick = function () { if (selM) { if (sel[x.id]) delete sel[x.id]; else sel[x.id] = 1; draw(); } else openT(x.id, x); };
				c.appendChild(b);
			});
			badge(tot);
		}
		function list() {
			return api("list").then(function (d) {
				if (!d.ok) return;
				LD = d.chats;
				draw();
				var au = P.getAttribute("data-auto");
				if (au) { P.setAttribute("data-auto", ""); LD.forEach(function (x) { if (x.k === au || String(x.id) === au) openT(x.id, x); }); }
			}).catch(function () { if (!LD.length) $("pgc-cl").innerHTML = '<p class="pgc-emptyst">' + esc(L.offline) + "</p>"; });
		}
		function head(x) {
			$("pgc-ctt").textContent = x.t || "";
			$("pgc-cts").textContent = x.sub || "";
			var h = $("pgc-chav");
			h.textContent = "";
			h.appendChild(av(x.av, x.t, x.id));
		}
		function dayLabel(m) {
			var t = new Date(), y = new Date(Date.now() - 86400000), k = function (d) { return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2) + "-" + ("0" + d.getDate()).slice(-2); };
			return m.d === k(t) ? L.today : m.d === k(y) ? L.yesterday : m.dl;
		}
		function bubble(m) {
			var grp = prev && prev.u === m.u && prev.d === m.d && m.ts - prev.ts < 600 && prev.k === "msg" && m.k === "msg";
			if (grp && prev.node) prev.node.classList.remove("last");
			var w = el("div", "pgc-bw" + (m.me ? " me" : "") + (grp ? "" : " first") + " last");
			w.setAttribute("data-id", m.id);
			w.appendChild(av(m.av, m.n, m.u));
			var b = el("div", "pgc-b" + (m.k !== "msg" ? " " + m.k : ""));
			if (!m.me && !grp && info && info.type !== "dm") {
				var nm = el("span", "pgc-bn", m.n);
				nm.style.color = "var(--ac)";
				if (m.k === "why") nm.appendChild(el("span", "pgc-badge done", L.expl));
				if (m.k === "late") nm.appendChild(el("span", "pgc-badge late", L.c_late));
				b.appendChild(nm);
			}
			if (m.sn && info && info.to && m.s !== m.u) b.appendChild(el("span", "pgc-bto", L.to + ": " + m.sn));
			if (m.p) { var a = el("a"); a.href = m.p; var i = el("img"); i.src = m.p; i.loading = "lazy"; i.alt = ""; a.appendChild(i); b.appendChild(a); }
			if (m.h) { var x = el("span", "pgc-bt"); x.innerHTML = m.h.replace(/<br\s*\/?>\n?/g, "\n"); b.appendChild(x); }
			b.appendChild(el("span", "pgc-btm", m.tm));
			w.appendChild(b);
			w.onclick = function (e) {
				if (e.target.closest("a")) return;
				if (msM) { if (msel[m.id]) { delete msel[m.id]; w.classList.remove("sel"); } else { msel[m.id] = 1; w.classList.add("sel"); } cnt(); return; }
				if (info && info.to && (m.s || m.u) && !m.me) { subj = m.s || m.u; var t = $("pgc-to"); t.hidden = false; t.textContent = L.to + ": " + (m.sn || m.n) + "  ✕"; }
			};
			prev = { u: m.u, d: m.d, ts: m.ts, k: m.k, node: w };
			return w;
		}
		function nearBottom() { var s = $("pgc-ms"); return s.scrollTop + s.clientHeight >= s.scrollHeight - 80; }
		function stick() { var s = $("pgc-ms"); if (s && cur) s.scrollTop = s.scrollHeight; }
		/* один запрос за раз: иначе два одновременных запроса приносят одно сообщение дважды */
		var inflight = null, again = false;
		function poll() {
			if (!cur || D.hidden) return Promise.resolve();
			if (inflight) { again = true; return inflight; }
			inflight = pollOnce().then(function () { inflight = null; if (again) { again = false; return poll(); } }, function () { inflight = null; });
			return inflight;
		}
		function pollOnce() {
			var id = cur;
			return api("msgs", { chat: cur, after: last }).then(function (d) {
				if (!d.ok || id !== cur) return;
				var s = $("pgc-ms"), first = last === 0, nb = first || nearBottom();
				if (d.chat) {
					info = d.chat;
					P.classList.toggle("dm", info.type === "dm");
					$("pgc-att").style.display = (info.ph || info.own) ? "" : "none";
					head(info);
					tl();
				}
				if (first) { s.textContent = ""; if (!d.msgs.length) s.appendChild(el("div", "pgc-cempty", L.c_empty_t)); }
				else if (d.msgs.length) { var ce = s.querySelector(".pgc-cempty"); if (ce) ce.remove(); }
				d.msgs.forEach(function (m) {
					if (m.id <= last || s.querySelector('[data-id="' + m.id + '"]')) return;
					if (!prev || prev.d !== m.d) { s.appendChild(el("div", "pgc-day", dayLabel(m))); prev = null; }
					s.appendChild(bubble(m));
					last = Math.max(last, m.id);
				});
				if (d.msgs.length && nb) stick();
			}).catch(function () { });
		}
		function sched() { clearTimeout(tm); tm = setTimeout(function () { poll().then(sched); }, D.hidden ? 15000 : 4000); }
		function openT(id, x) {
			cur = id; last = 0; subj = 0; info = null; prev = null; again = false;
			$("pgc-ms").innerHTML = '<div class="pgc-load"><i class="pgc-spin"></i></div>';
			$("pgc-to").hidden = true; $("pgc-pv").hidden = true; $("pgc-cfile").value = "";
			setMs(false);
			if (x) head(x);
			P.classList.add("thread");
			if (isDesk()) draw();
			poll().then(function () { stick(); sched(); });
			setTimeout(function () { if (isDesk()) $("pgc-ci").focus(); }, 300);
		}
		function closeT() { P.classList.remove("thread"); cur = 0; clearTimeout(tm); setMs(false); D.activeElement && D.activeElement.blur && D.activeElement.blur(); list(); }
		function openP() {
			P.classList.add("open"); P.setAttribute("aria-hidden", "false"); R.classList.add("chatopen"); lockSync(); closeMenus();
			list();
			clearInterval(lt);
			lt = setInterval(function () { if (!D.hidden && !selM && (!cur || isDesk())) list(); }, 10000);
		}
		function closeP() {
			P.classList.remove("open", "thread"); P.setAttribute("aria-hidden", "true"); R.classList.remove("chatopen");
			clearTimeout(tm); clearInterval(lt); cur = 0; closeMenus(); lockSync();
			if (D.activeElement && D.activeElement.blur) D.activeElement.blur();
			api("unread").then(function (d) { if (d.ok) badge(d.n); }).catch(function () { });
		}
		function setSel(on) { selM = on; sel = {}; if ($("pgc-sel")) $("pgc-sel").hidden = !on; }
		function cnt() { var n = Object.keys(msel).length; if ($("pgc-msdel")) $("pgc-msdel").textContent = L.del + " (" + n + ")"; }
		function setMs(on) { msM = on; msel = {}; if ($("pgc-msel")) $("pgc-msel").hidden = !on; qa(".pgc-bw.sel").forEach(function (x) { x.classList.remove("sel"); }); cnt(); }
		function tl() { if (!$("pgc-tph") || !info) return; $("pgc-tphs").textContent = info.ph ? L.tph1 : L.tph0; $("pgc-tlks").textContent = info.lk ? L.tlk1 : L.tlk0; $("pgc-tav").hidden = info.type === "dm"; }
		function grow() { var i = $("pgc-ci"); i.style.height = "42px"; i.style.height = Math.min(140, i.scrollHeight + 2) + "px"; $("pgc-cs").classList.toggle("off", !i.value.trim() && !$("pgc-cfile").files.length); }
		function send() {
			var i = $("pgc-ci"), fi = $("pgc-cfile"), v = i.value.trim();
			if (sending || (!v && !fi.files.length) || !cur) return;
			if (!navigator.onLine) { toast(L.offline_act, true); return; }
			sending = true;
			var b = $("pgc-cs"), d = { chat: cur, body: v };
			if (subj) d.subject = subj;
			b.classList.add("off");
			var pr = fi.files.length ? shrink(fi.files[0]).catch(function () { return fi.files[0]; }) : Promise.resolve(null);
			pr.then(function (ph) { return api("send", d, ph ? { photo: ph } : null); }).then(function (r) {
				if (!r.ok) { toast(r.m || L.err, true); return; }
				i.value = ""; fi.value = ""; $("pgc-pv").hidden = true; grow();
				return poll().then(stick);
			}).catch(function () { toast(L.err, true); }).then(function () { sending = false; grow(); });
		}
		function bind() {
			P = $("pgc-chat");
			if (!P) return;
			own = !!CFG.own;
			var ci = $("pgc-ci"), cs = $("pgc-cs"), fi = $("pgc-cfile");
			$("pgc-cx").addEventListener("click", closeP);
			if ($("pgc-cx2")) $("pgc-cx2").addEventListener("click", closeP);
			$("pgc-cback").addEventListener("click", closeT);
			ci.addEventListener("input", grow);
			ci.addEventListener("keydown", function (e) { if (e.key === "Enter" && !e.shiftKey && !e.isComposing && isDesk()) { e.preventDefault(); send(); } });
			/* кнопка не забирает фокус — клавиатура остаётся открытой, как в Telegram */
			cs.addEventListener("pointerdown", function (e) { e.preventDefault(); });
			cs.addEventListener("mousedown", function (e) { e.preventDefault(); });
			cs.addEventListener("click", send);
			fi.addEventListener("change", function () {
				var p = $("pgc-pv");
				if (fi.files.length) { $("pgc-pvn").textContent = fi.files[0].name; $("pgc-pvi").src = URL.createObjectURL(fi.files[0]); p.hidden = false; } else p.hidden = true;
				grow();
			});
			$("pgc-pvx").addEventListener("click", function () { fi.value = ""; $("pgc-pv").hidden = true; grow(); });
			$("pgc-to").addEventListener("click", function () { subj = 0; this.hidden = true; });
			var inThread = function () { return P.classList.contains("thread") && !isDesk(); };
			swipeBack(P, function () { if (inThread()) closeT(); else closeP(); }, function () { return inThread() ? $("pgc-cth") : P; });
			D.addEventListener("click", function (e) {
				if (e.target.closest("[data-chat]")) { if (P.classList.contains("open")) closeP(); else openP(); return; }
				var dm = e.target.closest(".pgc-dm");
				if (dm) { openP(); api("new", { users: dm.getAttribute("data-u"), title: "" }).then(function (d) { if (d.ok) openT(d.id, { t: d.t }); }); }
			});
			if (own) {
				var mt = function (id, btn) { return function (e) { e.stopPropagation(); var m = $(id), was = m.classList.contains("open"); closeMenus(); if (!was) m.classList.add("open"); }; };
				$("pgc-lhm").addEventListener("click", mt("pgc-cmenu"));
				$("pgc-cdots").addEventListener("click", mt("pgc-tmenu"));
				$("pgc-cmsel").addEventListener("click", function () { closeMenus(); setSel(true); draw(); });
				$("pgc-cmadd").addEventListener("click", function () { closeMenus(); openModal($("pgc-m-newchat")); });
				$("pgc-selx").addEventListener("click", function () { setSel(false); draw(); });
				var act = function (o, q) { var i = Object.keys(sel).join(","); if (!i || !confirm(q)) return; api(o, { ids: i }).then(function (d) { if (!d.ok) { toast(d.m || L.err, true); return; } setSel(false); list(); }); };
				$("pgc-selclr").addEventListener("click", function () { act("clear", L.clrq); });
				$("pgc-seldel").addEventListener("click", function () { act("delchat", L.delq); });
				$("pgc-tsel").addEventListener("click", function () { closeMenus(); setMs(true); });
				$("pgc-tclr").addEventListener("click", function () { closeMenus(); if (!confirm(L.clrq)) return; api("clear", { ids: cur }).then(function (d) { if (d.ok) { last = 0; prev = null; poll(); } }); });
				var perm = function () { if (info) api("perms", { chat: cur, ph: info.ph ? 1 : 0, lk: info.lk ? 1 : 0 }); };
				$("pgc-tph").addEventListener("click", function () { if (!info) return; info.ph = info.ph ? 0 : 1; tl(); perm(); });
				$("pgc-tlk").addEventListener("click", function () { if (!info) return; info.lk = info.lk ? 0 : 1; tl(); perm(); });
				$("pgc-tav").addEventListener("click", function () { closeMenus(); $("pgc-cavf").click(); });
				$("pgc-cavf").addEventListener("change", function () {
					var f = this.files[0], inp = this;
					if (!f || !cur) return;
					shrink(f).catch(function () { return f; }).then(function (b) { return api("cav", { chat: cur }, { photo: b }); }).then(function (d) {
						inp.value = "";
						if (!d.ok) { toast(d.m || L.err, true); return; }
						if (info) { info.av = d.av; head(info); }
						list();
					}).catch(function () { toast(L.err, true); });
				});
				$("pgc-msdel").addEventListener("click", function () {
					var ids = Object.keys(msel);
					if (!ids.length || !confirm(L.msgq)) return;
					api("delmsg", { chat: cur, ids: ids.join(",") }).then(function (d) { if (d.ok) { qa(".pgc-bw").forEach(function (w) { if (msel[w.getAttribute("data-id")]) w.remove(); }); setMs(false); } });
				});
				$("pgc-msx").addEventListener("click", function () { setMs(false); });
				$("pgc-cmk").addEventListener("click", function () {
					var ids = [];
					qa("#pgc-cn input[type=checkbox]:checked").forEach(function (c) { ids.push(c.value); });
					api("new", { users: ids.join(","), title: $("pgc-cnn").value }).then(function (d) {
						if (!d.ok) { toast(d.m || L.err, true); return; }
						$("pgc-cnn").value = "";
						qa("#pgc-cn input[type=checkbox]").forEach(function (c) { c.checked = false; });
						closeModal($("pgc-m-newchat"));
						openT(d.id, { t: d.t });
						list();
					});
				});
			}
			if (P.getAttribute("data-auto")) openP();
		}
		function auto(k) { if (!P) return; P.setAttribute("data-auto", k); if (P.classList.contains("open")) list(); else openP(); }
		return { close: closeP, auto: auto, bind: bind, isOpen: function () { return P && P.classList.contains("open"); }, stick: function () { if (cur && nearBottom()) stick(); else if (cur) { var s = $("pgc-ms"); if (s && s.scrollHeight - s.scrollTop - s.clientHeight < 240) stick(); } }, badge: badge };
	})();

	/* ============================================================
	   КАЛЕНДАРЬ (как Google Календарь)
	   ============================================================ */
	var Cal = (function () {
		var S = null, box = null, st = { view: null, dn: null }, firstScroll = true, nowT = null, loadingR = false;
		var DAY = 86400;
		function dnOf(ts) { return Math.floor((ts + S.tzo) / DAY); }
		function tsOf(dn) { return dn * DAY - S.tzo; }
		function dow(dn) { return new Date(dn * 864e5).getUTCDay(); }
		function ymd(dn) { var d = new Date(dn * 864e5); return [d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()]; }
		function fromYmd(y, m, d) { return Math.floor(Date.UTC(y, m, d) / 864e5); }
		function mon(dn) { return dn - ((dow(dn) + 6) % 7); }
		function hm(ts) { var d = new Date((ts + S.tzo) * 1000); return ("0" + d.getUTCHours()).slice(-2) + ":" + ("0" + d.getUTCMinutes()).slice(-2); }
		function minOf(ts) { var d = new Date((ts + S.tzo) * 1000); return d.getUTCHours() * 60 + d.getUTCMinutes(); }
		function today() { return dnOf(Math.floor(Date.now() / 1000)); }
		function color(n) { if (!n) return "cx"; for (var i = 0; i < S.staff.length; i++) if (S.staff[i].id === n) return "c" + S.staff[i].c; return "cx"; }
		function sname(n) { for (var i = 0; i < S.staff.length; i++) if (S.staff[i].id === n) return S.staff[i].nm; return L.cal_none; }
		function isDesk() { return W.matchMedia && matchMedia("(min-width:1024px)").matches; }
		function range() {
			var v = st.view, dn = st.dn;
			if (v === "day") return [dn, 1];
			if (v === "3") return [dn, 3];
			if (v === "week") return [mon(dn), 7];
			if (v === "month") { var y = ymd(dn); return [mon(fromYmd(y[0], y[1], 1)), 42]; }
			return [dn, 30];
		}
		function merge(list, from, to) {
			Object.keys(S.ev).forEach(function (k) { var e = S.ev[k]; if (e.s >= from && e.s < to) delete S.ev[k]; });
			list.forEach(function (e) { S.ev[e.i] = e; });
			S.loaded.push([from, to]);
		}
		function covered(a, b) { return S.loaded.some(function (r) { return r[0] <= a && r[1] >= b; }); }
		function ensure() {
			var r = range(), a = tsOf(r[0]), b = tsOf(r[0] + r[1]);
			if (covered(a, b) || loadingR) return;
			loadingR = true;
			bar(true);
			var pa = tsOf(r[0] - 7), pb = tsOf(r[0] + r[1] + 7);
			post({ action: "petgo_cal", n: CFG.no, from: pa, to: pb }).then(function (d) {
				if (!d.ok) return;
				merge(d.ev, d.from, d.to);
				d.staff.forEach(function (s) { if (!S.staff.some(function (x) { return x.id === s.id; })) S.staff.push(s); });
				cachePut(CFG.page + (CFG.page.indexOf("?") > -1 ? "&" : "?") + "pgc_cache=cal-" + d.from, JSON.stringify(d));
				body();
			}).catch(function () {
				cacheGet(CFG.page + (CFG.page.indexOf("?") > -1 ? "&" : "?") + "pgc_cache=cal-" + pa).then(function (c) { if (c) { var d = JSON.parse(c); merge(d.ev, d.from, d.to); body(); } });
			}).then(function () { loadingR = false; bar(false); });
		}
		function bar(on) { var b = box && box.querySelector(".pgc-calbar"); if (b) b.classList.toggle("on", on); }
		function events(dnA, n) {
			var a = tsOf(dnA), b = tsOf(dnA + n), out = [];
			Object.keys(S.ev).forEach(function (k) { var e = S.ev[k]; if (e.s >= a && e.s < b && (S.filter === null || e.n === S.filter)) out.push(e); });
			out.sort(function (x, y) { return x.s - y.s || y.d - x.d; });
			return out;
		}
		function title() {
			var r = range(), y = ymd(st.view === "month" ? st.dn : r[0]), y2 = ymd(r[0] + r[1] - 1);
			if (st.view === "month" || st.view === "list" || y[1] === y2[1]) return L.months[y[1]] + (y[0] !== ymd(today())[0] ? " " + y[0] : "");
			return L.months[y[1]].slice(0, 3) + " – " + L.months[y2[1]].slice(0, 3) + (y2[0] !== ymd(today())[0] ? " " + y2[0] : "");
		}
		var IC = {
			chev: '<svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
			l: '<svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>',
			r: '<svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>',
			cal: '<svg class="pgc-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16.5" rx="3"/><path d="M8 2.5v4M16 2.5v4"/></svg>'
		};
		var VIEWS = [["list", "cal_list"], ["day", "cal_day"], ["3", "cal_3"], ["week", "cal_week"], ["month", "cal_month"]];
		function shell() {
			var vl = "";
			VIEWS.forEach(function (v) { vl += '<button type="button" class="pgc-mi' + (st.view === v[0] ? " on" : "") + '" data-cal-view="' + v[0] + '">' + esc(L[v[1]]) + "</button>"; });
			var cur = VIEWS.filter(function (v) { return v[0] === st.view; })[0];
			var chips = "";
			if (S.staff.length > 1 || Object.keys(S.ev).some(function (k) { return !S.ev[k].n; })) {
				chips = '<div class="pgc-calf"><button type="button" class="pgc-chip' + (S.filter === null ? " on" : "") + '" data-cal-f="">' + esc(L.all) + "</button>";
				S.staff.forEach(function (s) { if (s.off) return; chips += '<button type="button" class="pgc-chip' + (S.filter === s.id ? " on" : "") + '" data-cal-f="' + s.id + '"><span class="dot c' + s.c + '"></span>' + esc(s.nm) + "</button>"; });
				chips += '<button type="button" class="pgc-chip' + (S.filter === 0 ? " on" : "") + '" data-cal-f="0"><span class="dot cx"></span>' + esc(L.cal_none) + "</button></div>";
			}
			box.innerHTML = '<div class="pgc-calh"><button type="button" class="pgc-calt" data-cal="pick"><span>' + esc(title()) + "</span>" + IC.chev + '</button><span class="grow"></span>' +
				'<button type="button" class="pgc-calb ic desk" data-cal="prev" aria-label="prev">' + IC.l + '</button><button type="button" class="pgc-calb ic desk" data-cal="next" aria-label="next">' + IC.r + "</button>" +
				'<button type="button" class="pgc-calb ic today" data-cal="today" aria-label="' + esc(L.today) + '">' + IC.cal + "<span>" + ymd(today())[2] + "</span></button>" +
				'<div class="pgc-me"><button type="button" class="pgc-calb" data-menu="pgc-calvm">' + esc(L[cur[1]]) + IC.chev + '</button><div class="pgc-menu" id="pgc-calvm">' + vl + "</div></div></div>" +
				'<div class="pgc-calpk" hidden></div>' + chips + '<div class="pgc-calhx"></div><div class="pgc-calbar"></div><div class="pgc-calv"></div>';
		}
		function lanes(list) {
			var groups = [], g = null, end = 0;
			list.forEach(function (e) {
				var s = minOf(e.s), f = s + Math.max(e.d, 25);
				if (!g || s >= end) { g = []; groups.push(g); end = 0; }
				g.push({ e: e, s: s, f: f });
				end = Math.max(end, f);
			});
			groups.forEach(function (gr) {
				var cols = [];
				gr.forEach(function (x) {
					for (var i = 0; i < cols.length; i++) if (cols[i] <= x.s) { x.c = i; cols[i] = x.f; return; }
					x.c = cols.length; cols.push(x.f);
				});
				gr.forEach(function (x) { x.n = cols.length; });
			});
			return [].concat.apply([], groups);
		}
		function grid(dnA, n) {
			var td = today(), hh = isDesk() ? 48 : 52, h = "", hd = "", lb = "";
			box.style.setProperty("--hh", hh + "px");
			for (var i = 0; i < n; i++) {
				var dn = dnA + i, y = ymd(dn);
				hd += '<button type="button" class="pgc-cgd' + (dn === td ? " td" : "") + '" data-cal-day="' + dn + '"><span>' + esc(L.wd[dow(dn)]) + "</span><b>" + y[2] + "</b></button>";
			}
			for (var k = 1; k < 24; k++) lb += '<span style="top:' + (k * hh) + 'px">' + ("0" + k).slice(-2) + ":00</span>";
			for (var j = 0; j < n; j++) {
				var d = dnA + j, col = "";
				lanes(events(d, 1)).forEach(function (x) {
					var top = x.s * hh / 60, ht = Math.max(20, Math.min(x.e.d, 1440 - x.s) * hh / 60 - 2), w = 100 / x.n;
					col += '<div class="pgc-ev ' + color(x.e.n) + " st-" + x.e.st + (ht < 34 ? " sm" : ht < 52 ? " md" : "") + '" data-ov="' + x.e.i + '" data-name="' + esc(x.e.p) + '" role="button" tabindex="0" style="top:' + top + "px;height:" + ht + "px;left:calc(" + (x.c * w) + "% + 1px);width:calc(" + w + '% - 3px)"><b>' + esc(x.e.p) + "</b><span>" + hm(x.e.s) + "–" + hm(x.e.s + x.e.d * 60) + "</span><span>" + esc(sname(x.e.n)) + "</span></div>";
				});
				if (d === td) col += '<div class="pgc-now" style="top:' + (minOf(Math.floor(Date.now() / 1000)) * hh / 60) + 'px"></div>';
				h += '<div class="pgc-cgc' + (d === td ? " td" : "") + '" data-dn="' + d + '">' + col + "</div>";
			}
			box.querySelector(".pgc-calhx").innerHTML = '<div class="pgc-cgh" style="--n:' + n + '"><span></span>' + hd + "</div>";
			return '<div class="pgc-cgb" style="--n:' + n + '"><div class="pgc-cgl">' + lb + "</div>" + h + "</div>";
		}
		function month() {
			var y = ymd(st.dn), first = fromYmd(y[0], y[1], 1), a = mon(first), td = today(), h = "", wd = "";
			for (var w = 0; w < 7; w++) wd += "<span>" + esc(L.wd[(w + 1) % 7]) + "</span>";
			for (var i = 0; i < 42; i++) {
				var dn = a + i, dy = ymd(dn), ev = events(dn, 1), max = isDesk() ? 4 : 3, inner = "";
				ev.slice(0, ev.length > max ? max - 1 : max).forEach(function (e) { inner += '<i class="pgc-mev ' + color(e.n) + " st-" + e.st + '">' + (isDesk() ? hm(e.s) + " " : "") + esc(e.p) + "</i>"; });
				if (ev.length > max) inner += '<i class="pgc-mmore">+' + (ev.length - max + 1) + "</i>";
				h += '<button type="button" class="pgc-moc' + (dy[1] !== y[1] ? " out" : "") + (dn === td ? " td" : "") + '" data-cal-day="' + dn + '"><span class="n">' + dy[2] + "</span>" + inner + "</button>";
			}
			box.querySelector(".pgc-calhx").innerHTML = '<div class="pgc-mow">' + wd + "</div>";
			return '<div class="pgc-mog">' + h + "</div>";
		}
		function schedule() {
			var td = today(), h = "";
			for (var i = 0; i < 30; i++) {
				var dn = st.dn + i, ev = events(dn, 1);
				if (!ev.length && dn !== td) continue;
				var y = ymd(dn), items = "";
				ev.forEach(function (e) { items += '<div class="pgc-lsi ' + color(e.n) + " st-" + e.st + '" data-ov="' + e.i + '" data-name="' + esc(e.p) + '" role="button" tabindex="0"><div><b>' + esc(e.p) + "</b><span>" + hm(e.s) + "–" + hm(e.s + e.d * 60) + " · " + esc(S.svc[e.sv] || "") + " · " + esc(sname(e.n)) + "</span></div></div>"; });
				if (!ev.length) items = '<p class="mu" style="padding:10px 0">' + esc(L.cal_free) + "</p>";
				h += '<div class="pgc-lsd' + (dn === td ? " td" : "") + '"><button type="button" class="pgc-lsdd" data-cal-day="' + dn + '"><span>' + esc(L.wd[dow(dn)]) + "</span><b>" + y[2] + "</b><small>" + esc(L.months_g[y[1]]) + '</small></button><div class="pgc-lse">' + items + "</div></div>";
			}
			box.querySelector(".pgc-calhx").innerHTML = "";
			return '<div class="pgc-ls">' + (h || '<div class="pgc-emptyst"><p>' + esc(L.cal_free) + "</p></div>") + "</div>";
		}
		function body(dir) {
			if (!box) return;
			var r = range(), v = box.querySelector(".pgc-calv"), t = box.querySelector(".pgc-calt span");
			if (t) t.textContent = title();
			v.innerHTML = st.view === "month" ? month() : st.view === "list" ? schedule() : grid(r[0], r[1]);
			if (dir) { v.classList.remove("slL", "slR"); void v.offsetWidth; v.classList.add(dir < 0 ? "slL" : "slR"); }
			if (st.view !== "month" && st.view !== "list" && firstScroll) {
				firstScroll = false;
				var hh = isDesk() ? 48 : 52, m = Math.max(0, minOf(Math.floor(Date.now() / 1000)) - 90), g = v.querySelector(".pgc-cgb");
				if (g) { var top = g.getBoundingClientRect().top + W.scrollY - stickH(); W.scrollTo(0, Math.max(0, top + Math.min(m, 7 * 60) * hh / 60)); }
			} else if (toTop) { toTop = false; var bt = box.getBoundingClientRect().top + W.scrollY - toph(); if (W.scrollY > bt) W.scrollTo(0, bt); }
			ensure();
			save();
		}
		var toTop = false;
		function toph() { return parseInt(getComputedStyle(R).getPropertyValue("--toph"), 10) || 64; }
		function stickH() { var a = box.querySelector(".pgc-calh"), b = box.querySelector(".pgc-calhx"); return toph() + (a ? a.offsetHeight : 0) + (b ? b.offsetHeight : 0); }
		function save() { try { sessionStorage.setItem("pgc_cal", JSON.stringify(st)); } catch (e) { } ls("pgc_calv", st.view); }
		function step(d) {
			var v = st.view;
			if (v === "month") { var y = ymd(st.dn); st.dn = fromYmd(y[0], y[1] + d, 1); }
			else st.dn += d * (v === "day" ? 1 : v === "3" ? 3 : v === "week" ? 7 : 7);
			body(d);
		}
		function picker() {
			var pk = box.querySelector(".pgc-calpk"), btn = box.querySelector(".pgc-calt");
			if (!pk.hidden) { pk.hidden = true; btn.classList.remove("open"); return; }
			btn.classList.add("open");
			pk.hidden = false;
			pk.setAttribute("data-m", st.dn);
			drawPk(st.dn);
		}
		function drawPk(anchor) {
			var pk = box.querySelector(".pgc-calpk"), y = ymd(anchor), a = mon(fromYmd(y[0], y[1], 1)), td = today(), h = "";
			for (var w = 0; w < 7; w++) h += "<span>" + esc(L.wd[(w + 1) % 7]) + "</span>";
			for (var i = 0; i < 42; i++) {
				var dn = a + i, dy = ymd(dn);
				h += '<button type="button" data-cal-go="' + dn + '" class="' + (dy[1] !== y[1] ? "out " : "") + (dn === st.dn ? "sel " : "") + (dn === td ? "td " : "") + (events(dn, 1).length ? "has" : "") + '">' + dy[2] + "</button>";
			}
			pk.innerHTML = '<div class="pgc-pkh"><button type="button" class="pgc-calb ic" data-cal-pk="-1">' + IC.l + "</button><b>" + esc(L.months[y[1]] + " " + y[0]) + '</b><button type="button" class="pgc-calb ic" data-cal-pk="1">' + IC.r + '</button></div><div class="pgc-pkg">' + h + "</div>";
			pk.setAttribute("data-m", anchor);
		}
		function newAt(dn, min) {
			var m = $("pgc-m-order");
			if (!m) return;
			var y = ymd(dn), di = m.querySelector("input[name=date]"), ti = m.querySelector("input[name=time]");
			if (di) di.value = y[0] + "-" + ("0" + (y[1] + 1)).slice(-2) + "-" + ("0" + y[2]).slice(-2);
			if (ti && min != null) { var r = Math.floor(min / 30) * 30; ti.value = ("0" + Math.floor(r / 60)).slice(-2) + ":" + ("0" + r % 60).slice(-2); }
			openModal(m);
		}
		function onClick(e) {
			if (!box || !box.contains(e.target)) return;
			var t = e.target, b;
			b = t.closest("[data-cal]");
			if (b) {
				var a = b.getAttribute("data-cal");
				if (a === "prev") step(-1);
				else if (a === "next") step(1);
				else if (a === "today") { st.dn = st.view === "month" ? today() : st.view === "week" ? today() : today(); firstScroll = true; body(); }
				else if (a === "pick") picker();
				return;
			}
			b = t.closest("[data-cal-view]");
			if (b) { st.view = b.getAttribute("data-cal-view"); closeMenus(); firstScroll = true; toTop = true; shell(); body(); return; }
			b = t.closest("[data-cal-f]");
			if (b) { var f = b.getAttribute("data-cal-f"); S.filter = f === "" ? null : +f; qa("[data-cal-f]", box).forEach(function (c) { c.classList.toggle("on", c === b); }); body(); return; }
			b = t.closest("[data-cal-pk]");
			if (b) { var pk = box.querySelector(".pgc-calpk"), y = ymd(+pk.getAttribute("data-m")); drawPk(fromYmd(y[0], y[1] + (+b.getAttribute("data-cal-pk")), 1)); return; }
			b = t.closest("[data-cal-go]");
			if (b) { st.dn = +b.getAttribute("data-cal-go"); box.querySelector(".pgc-calpk").hidden = true; box.querySelector(".pgc-calt").classList.remove("open"); body(); return; }
			b = t.closest("[data-cal-day]");
			if (b) { st.dn = +b.getAttribute("data-cal-day"); if (st.view !== "day") { st.view = "day"; shell(); } firstScroll = true; body(); return; }
			if (t.classList.contains("pgc-cgc")) {
				var r = t.getBoundingClientRect(), hh = isDesk() ? 48 : 52;
				newAt(+t.getAttribute("data-dn"), Math.max(0, (e.clientY - r.top) * 60 / hh));
			}
		}
		function swipe() {
			var sx = 0, sy = 0, ok = false;
			box.addEventListener("touchstart", function (e) { if (!e.target.closest(".pgc-calv")) { ok = false; return; } sx = e.touches[0].clientX; sy = e.touches[0].clientY; ok = e.touches.length === 1; }, { passive: true });
			box.addEventListener("touchend", function (e) {
				if (!ok) return;
				ok = false;
				var t = e.changedTouches[0], dx = t.clientX - sx, dy = t.clientY - sy;
				if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.6 && st.view !== "list") step(dx < 0 ? 1 : -1);
			}, { passive: true });
		}
		function init() {
			var n = $("pgc-cal-data");
			box = $("pgc-calapp");
			if (!n || !box || box.getAttribute("data-on")) return;
			box.setAttribute("data-on", "1");
			var d = JSON.parse(n.textContent);
			S = { tzo: d.tzo, staff: d.staff, ev: {}, loaded: [], filter: null, svc: d.svc };
			merge(d.ev, d.from, d.to);
			try { var sv = JSON.parse(sessionStorage.getItem("pgc_cal") || "null"); if (sv && sv.view) st = sv; } catch (e) { }
			if (!st.view) st.view = ls("pgc_calv") || (isDesk() ? "week" : "3");
			if (st.dn == null) st.dn = today();
			shell();
			body();
			swipe();
			clearInterval(nowT);
			nowT = setInterval(function () { if (!$("pgc-calapp")) { clearInterval(nowT); return; } var now = box.querySelector(".pgc-now"); if (now) now.style.top = (minOf(Math.floor(Date.now() / 1000)) * (isDesk() ? 48 : 52) / 60) + "px"; }, 60000);
		}
		D.addEventListener("click", onClick);
		D.addEventListener("keydown", function (e) {
			if (!box || !D.body.contains(box) || /INPUT|TEXTAREA|SELECT/.test((D.activeElement || {}).tagName || "") || R.classList.contains("pgc-lock")) return;
			if (e.key === "ArrowLeft") step(-1); else if (e.key === "ArrowRight") step(1);
		});
		return { init: init };
	})();

	/* ============================================================
	   ЗАПУСК
	   ============================================================ */
	function boot() {
		root = $("pgc");
		applyTheme();
		D.addEventListener("click", onClick);
		D.addEventListener("submit", onSubmit);
		D.addEventListener("change", onChange);
		D.addEventListener("keydown", function (e) {
			if (e.key === "Escape") { var m = D.querySelector(".pgc-modal.open"); if (m) closeModal(m); else if ($("pgc-lb") && $("pgc-lb").classList.contains("open")) Lb.close(); else if (Ov.isOpen()) Ov.close(); else if (Chat.isOpen()) Chat.close(); closeMenus(); }
			if (e.key === "Enter") { var c = e.target.closest && e.target.closest("[data-ov]"); if (c && e.target === c) Ov.open(c.getAttribute("data-ov"), c.getAttribute("data-name")); }
		});
		if (!root || !root.getAttribute("data-role")) return;
		CFG = readCfg(); L = CFG.L || {};
		var html = "<!doctype html>" + R.outerHTML;
		curRaw = rawOf(html);
		vp();
		Ov.bind(); Lb.bind(); Chat.bind();
		ptr();
		swipeTabs();
		page();
		flashes($("pgc-in"));
		try { history.replaceState({ pgc: 1 }, "", location.href); } catch (e) { }
		W.addEventListener("popstate", function () { if (isApp(location.href)) go(location.href, false); });
		W.addEventListener("scroll", function () { var t = D.querySelector(".pgc-top"); if (t) t.classList.toggle("sc", W.scrollY > 4); }, { passive: true });
		setInterval(tick, 15000);
		/* страница пришла из памяти телефона — сразу тихо обновляем */
		var age = Math.floor(Date.now() / 1000) - (CFG.t || 0);
		if (age > 15 && navigator.onLine) refresh();
		else if (isPlain(location.href)) cachePut(cleanKey(location.href), html);
		if (!navigator.onLine) online(false);
		W.addEventListener("online", function () { online(true); refresh(); });
		W.addEventListener("offline", function () { online(false); });
		/* лёгкая проверка изменений: одно число раз в 40 секунд, только когда приложение на экране */
		var hid = 0;
		setInterval(liveTick, 40000);
		D.addEventListener("visibilitychange", function () { if (D.hidden) hid = Date.now(); else { liveTick(); if (hid && Date.now() - hid > 120000 && canAuto()) refresh(); } });
		sw().then(function () { setTimeout(askPush, 700); pushUI(); setTimeout(warm, 2500); });
	}
	function liveTick() {
		if (D.hidden || !navigator.onLine) return;
		post({ action: "petgo_chat", n: CFG.nc, op: "ver" }).then(function (d) {
			if (!d.ok) { if (d.m === "403") hardReload(); return; }
			Chat.badge(d.n);
			if (d.v !== root.getAttribute("data-v")) { if (canAuto()) refresh(); else pending = true; }
			online(true);
		}).catch(function () { });
	}
	if (D.readyState === "loading") D.addEventListener("DOMContentLoaded", boot); else boot();
})();
