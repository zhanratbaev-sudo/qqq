/* PetGo Сайт: service worker (34.5). Настройки C подставляет сервер (includes/site-offline.php).
   - Нет интернета: открывается сохранённая страница с плашкой «Нет интернета» или экран PetGo «Нет интернета»
     с кнопками «Повторить» и «Позвонить» — вместо белого экрана или «динозавра».
   - Медленный интернет: если страница не пришла за C.wait мс — показываем сохранённую копию, а если её нет —
     экран «Загружаем…»; как только страница дошла, экран сам открывает её.
   - Файлы оформления и скрипты PetGo хранятся на телефоне — сайт открывается быстрее.
   Личные страницы (кабинет, анкета по ссылке) не сохраняются. Приложение сотрудников, вход и админка не трогаются. */
var SC = "pgs-static-" + C.v, PC = "pgs-pages", IC = "pgs-img";
var BASE = new URL(C.home), READY = {}, PEND = {};
var DROP = ["utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content", "gclid", "gbraid", "wbraid", "yclid", "fbclid", "ttclid", "_ga", "_gl", "ysclid"];

function mine(u) {
	if (u.origin !== BASE.origin || u.pathname.indexOf(BASE.pathname) !== 0) return false;
	if (/\/wp-(admin|login|json|cron)|admin-ajax\.php|xmlrpc\.php|\/feed\/?$|\.xml$|robots\.txt$/.test(u.pathname)) return false;
	if (u.searchParams.has("petgo_site_sw") || u.searchParams.has("petgo_sw") || u.searchParams.has("petgo_manifest") || u.searchParams.has("preview") || u.searchParams.has("customize_changeset_uuid") || u.searchParams.has("elementor-preview")) return false;
	for (var i = 0; i < C.skip.length; i++) {
		var s = C.skip[i];
		if (s.p && u.pathname.indexOf(s.p) === 0 && (!s.q || u.search.indexOf(s.q) > -1)) return false;
	}
	return true;
}
/* страница хранится без рекламных меток: /uslugi/?utm_source=… и /uslugi/ — одна запись */
function keyOf(u) {
	var o = new URL(u.href);
	o.hash = "";
	DROP.forEach(function (k) { o.searchParams.delete(k); });
	return o.href;
}
function lang(u) { var l = u.searchParams.get("lang"); return C.tx[l] ? l : "ru"; }
function isHTML(res) { return (res.headers.get("Content-Type") || "").indexOf("text/html") > -1; }

/* экран PetGo: «Нет интернета» или «Загружаем…» — свой, в цветах сайта, без внешних файлов */
function shell(kind, u, key) {
	var t = C.tx[lang(u)], off = kind === "off";
	var h = '<!doctype html><html lang="' + lang(u) + '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#FBF9E8"><title>' + (off ? t.off_t : t.wait_t) + ' — PetGo</title>' +
		"<style>:root{--bg:#FBF9E8;--tx:#024C46;--mu:#5f8480;--fill:rgba(2,76,70,.08);--acc:#024C46}" +
		"@media(prefers-color-scheme:dark){:root{--bg:#000;--tx:#F2F2F7;--mu:#8E8E93;--fill:rgba(255,255,255,.1);--acc:#0E7A64}}" +
		"*{box-sizing:border-box}html,body{margin:0;height:100%;background:var(--bg);color:var(--tx);font:17px/1.4 -apple-system,BlinkMacSystemFont,'SF Pro Text','Segoe UI',Roboto,sans-serif;-webkit-user-select:none;user-select:none}" +
		"main{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:32px 24px calc(32px + env(safe-area-inset-bottom));text-align:center;animation:in 240ms cubic-bezier(.32,.72,0,1) both}" +
		"@keyframes in{from{opacity:0;transform:translateY(8px)}}" +
		".logo{width:76px;height:76px;border-radius:22px;display:grid;place-items:center;background:var(--acc);color:#fff;margin-bottom:8px}.logo svg{width:40px;height:40px}" +
		"h1{margin:4px 0 0;font-size:24px;letter-spacing:-.02em}p{margin:0;max-width:330px;color:var(--mu);font-size:16px}" +
		".row{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:16px}" +
		"a,button{appearance:none;border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:50px;padding:0 22px;border-radius:999px;font-weight:600;font-size:17px;line-height:1;font-family:inherit;text-decoration:none;transition:transform 150ms cubic-bezier(.32,.72,0,1)}" +
		"a:active,button:active{transform:scale(.96)}.p{background:var(--acc);color:#fff}.s{background:var(--fill);color:var(--tx)}" +
		".spin{width:34px;height:34px;border-radius:50%;border:3px solid var(--fill);border-top-color:var(--acc);animation:sp .8s linear infinite,fade 240ms cubic-bezier(.32,.72,0,1) 300ms both;margin-top:14px}" +
		"@keyframes sp{to{transform:rotate(360deg)}}@keyframes fade{from{opacity:0}}" +
		"@media(prefers-reduced-motion:reduce){main{animation:none}.spin{animation:sp .8s linear infinite}}" +
		"[hidden]{display:none!important}</style></head><body><main>" +
		'<span class="logo"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12.3c-2.7 0-4.8 2.1-4.8 4 0 1.7 1.4 2.4 2.8 2 .9-.3 1.4-.6 2-.6s1.1.3 2 .6c1.4.4 2.8-.3 2.8-2 0-1.9-2.1-4-4.8-4z"/><ellipse cx="6.6" cy="10" rx="1.5" ry="2"/><ellipse cx="9.9" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="14.1" cy="6.5" rx="1.5" ry="2.1"/><ellipse cx="17.4" cy="10" rx="1.5" ry="2"/></svg></span>' +
		'<div id="w"' + (off ? " hidden" : "") + '><h1>' + t.wait_t + "</h1><p>" + t.wait_b + '</p><div style="display:flex;justify-content:center"><i class="spin"></i></div></div>' +
		'<div id="o"' + (off ? "" : " hidden") + "><h1>" + t.off_t + "</h1><p>" + t.off_b + '</p><div class="row"><button class="p" onclick="location.reload()">' + t.retry + "</button>" +
		(C.phone ? '<a class="s" href="tel:+' + C.phone + '">' + t.call + "</a>" : "") + "</div></div>" +
		"</main><script>(function(){var K=" + JSON.stringify(key) + ",o=document.getElementById('o'),w=document.getElementById('w');" +
		"function off(){w.hidden=true;o.hidden=false}" +
		"addEventListener('online',function(){location.reload()});" +
		"var sw=navigator.serviceWorker;if(" + (off ? "false" : "true") + "&&sw&&sw.controller){sw.addEventListener('message',function(e){var d=e.data||{};if(d.key!==K)return;if(d.t==='ready')location.reload();else off()});sw.controller.postMessage({t:'wait',key:K})}" +
		"else if(" + (off ? "false" : "true") + ")setTimeout(function(){location.reload()},4000);" +
		"})()</script></body></html>";
	return new Response(h, { headers: { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-store" } });
}

/* сохранённая страница + отметка для сайта: показать плашку «Нет интернета» или «Медленный интернет» */
function mark(res, kind) {
	return res.text().then(function (t) {
		var tag = '<script>window.PG_SW_COPY="' + kind + '"</script>';
		t = t.indexOf("<head>") > -1 ? t.replace("<head>", "<head>" + tag) : tag + t;
		return new Response(t, { headers: { "Content-Type": "text/html; charset=utf-8" } });
	});
}

function navigate(req) {
	var u = new URL(req.url), key = keyOf(u);
	if (READY[key]) { var r = READY[key]; delete READY[key]; return Promise.resolve(r); }
	var net = fetch(req).then(function (res) {
		if (res.ok && isHTML(res) && res.type === "basic") {
			if (res.headers.get("X-PetGo-Private")) caches.open(PC).then(function (c) { c.delete(key); });
			else { var copy = res.clone(); caches.open(PC).then(function (c) { c.put(key, copy); }); }
		}
		return res;
	});
	return new Promise(function (resolve) {
		var done = false;
		var t = setTimeout(function () {
			caches.open(PC).then(function (c) { return c.match(key); }).then(function (hit) {
				if (done) return;
				done = true;
				if (hit) { resolve(mark(hit, "slow")); net.catch(function () {}); }
				else { PEND[key] = net; net.catch(function () {}); resolve(shell("wait", u, key)); }
			});
		}, C.wait);
		net.then(function (res) {
			if (done) return;
			done = true; clearTimeout(t); resolve(res);
		}).catch(function () {
			if (done) return;
			done = true; clearTimeout(t);
			caches.open(PC).then(function (c) { return c.match(key); }).then(function (hit) { resolve(hit ? mark(hit, "offline") : shell("off", u, key)); });
		});
	});
}

/* страницы, которые сайт подгружает заранее (переходы без перезагрузки): из сети, без связи — сохранённые */
function page(req) {
	var key = keyOf(new URL(req.url));
	return fetch(req).then(function (res) {
		if (res.ok && isHTML(res) && res.type === "basic" && !res.headers.get("X-PetGo-Private")) { var copy = res.clone(); caches.open(PC).then(function (c) { c.put(key, copy); }); }
		return res;
	}).catch(function () {
		return caches.open(PC).then(function (c) { return c.match(key); }).then(function (hit) { return hit || Response.error(); });
	});
}

/* оформление, скрипты и иконки PetGo: сразу из телефона, в фоне обновляются */
function stale(req, name, max) {
	return caches.open(name).then(function (c) {
		return c.match(req).then(function (hit) {
			var net = fetch(req).then(function (res) {
				if (res.ok && res.type === "basic") {
					c.put(req, res.clone());
					if (max) c.keys().then(function (ks) { for (var i = 0; i < ks.length - max; i++) c.delete(ks[i]); });
				}
				return res;
			});
			return hit || net;
		});
	});
}

self.addEventListener("install", function (e) {
	e.waitUntil(caches.open(SC).then(function (c) {
		return Promise.all(C.pre.map(function (u) { return c.add(new Request(u, { credentials: "same-origin" })).catch(function () {}); }));
	}).then(function () { return self.skipWaiting(); }));
});

self.addEventListener("activate", function (e) {
	e.waitUntil(caches.keys().then(function (ks) {
		return Promise.all(ks.map(function (k) { if (k.indexOf("pgs-static-") === 0 && k !== SC) return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

/* экран «Загружаем…» ждёт свою страницу; пришла — экран обновляется и сразу её показывает */
self.addEventListener("message", function (e) {
	var d = e.data || {}, src = e.source;
	if (d === "clear") { e.waitUntil(caches.delete(PC)); return; }
	if (d.t !== "wait" || !src) return;
	var p = PEND[d.key];
	if (!p) { src.postMessage({ t: "ready", key: d.key }); return; }
	e.waitUntil(p.then(function (res) { delete PEND[d.key]; READY[d.key] = res; src.postMessage({ t: "ready", key: d.key }); })
		.catch(function () { delete PEND[d.key]; src.postMessage({ t: "off", key: d.key }); }));
});

self.addEventListener("fetch", function (e) {
	var req = e.request;
	if (req.method !== "GET") return;
	var u = new URL(req.url);
	if (!mine(u)) return;
	if (req.mode === "navigate") { e.respondWith(navigate(req)); return; }
	if (/\/wp-content\/plugins\/petgo-[a-z0-9-]+\/assets\//.test(u.pathname)) { e.respondWith(stale(req, SC, 0)); return; }
	if (/\/wp-content\/uploads\/.+\.(jpe?g|png|webp|gif|svg)$/i.test(u.pathname)) { e.respondWith(stale(req, IC, 80)); return; }
	if (u.pathname.indexOf("/wp-content/") < 0 && !/\.[a-z0-9]{2,5}$/i.test(u.pathname)) { e.respondWith(page(req)); return; }
});
