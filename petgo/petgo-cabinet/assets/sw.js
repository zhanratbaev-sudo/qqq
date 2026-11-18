/* PetGo: service worker. Настройки C подставляет сервер (includes/pwa.php). */
var SC = "pgc-static-" + C.v, PC = "pgc-pages", IC = "pgc-img";
var HOME = new URL(C.home), KEEP = ["pg", "st", "rs", "rl"];

function isApp(u) {
	if (u.origin !== HOME.origin || u.pathname !== HOME.pathname) return false;
	var pid = HOME.searchParams.get("page_id");
	return !pid || u.searchParams.get("page_id") === pid;
}
/* страница хранится только по «чистому» адресу (вкладка), без одноразовых параметров */
function clean(u) {
	var o = new URL(HOME.href);
	KEEP.forEach(function (k) { if (u.searchParams.has(k)) o.searchParams.set(k, u.searchParams.get(k)); });
	return o.href;
}
function plain(u) {
	var x = false;
	u.searchParams.forEach(function (v, k) { if (KEEP.indexOf(k) < 0 && k !== "page_id") x = true; });
	return !x;
}
function offline() {
	/* 34.5: экран «Нет интернета» — в цветах приложения, светлый и тёмный; связь вернулась — открывается сам */
	return new Response('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#f2f2f7"><title>' + C.off.t + '</title>' +
		'<style>:root{--bg:#f2f2f7;--tx:#12201e;--mu:#6b7a77;--ac:#0d7c6d}@media(prefers-color-scheme:dark){:root{--bg:#000;--tx:#fff;--mu:#8e8e93;--ac:#0E7A64}}' +
		'html,body{margin:0;height:100%;background:var(--bg);color:var(--tx);font:17px/1.4 -apple-system,BlinkMacSystemFont,system-ui,sans-serif;-webkit-user-select:none;user-select:none}' +
		'main{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:24px;text-align:center;box-sizing:border-box;animation:in 240ms cubic-bezier(.32,.72,0,1) both}@keyframes in{from{opacity:0;transform:translateY(8px)}}' +
		'img{width:72px;height:72px;border-radius:20px}h1{font-size:22px;margin:10px 0 0}p{color:var(--mu);margin:0 0 14px;max-width:320px}' +
		'button{font:600 17px system-ui,sans-serif;min-height:50px;padding:0 24px;border:0;border-radius:999px;background:var(--ac);color:#fff;transition:transform 150ms cubic-bezier(.32,.72,0,1)}button:active{transform:scale(.96)}' +
		'@media(prefers-reduced-motion:reduce){main{animation:none}}</style></head><body><main><img src="' + C.icon + '" alt=""><h1>' + C.off.t + '</h1><p>' + C.off.b + '</p><button onclick="location.reload()">' + C.off.r + '</button></main>' +
		'<script>addEventListener("online",function(){location.reload()})</script></body></html>', { headers: { "Content-Type": "text/html; charset=utf-8", "Cache-Control": "no-store" } });
}

self.addEventListener("install", function (e) {
	e.waitUntil(caches.open(SC).then(function (c) {
		return c.addAll(C.pre).catch(function () {});
	}).then(function () { return self.skipWaiting(); }));
});

self.addEventListener("activate", function (e) {
	e.waitUntil(caches.keys().then(function (ks) {
		return Promise.all(ks.map(function (k) { if (k.indexOf("pgc-static-") === 0 && k !== SC) return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

self.addEventListener("message", function (e) {
	if (e.data === "clear") e.waitUntil(Promise.all([caches.delete(PC), caches.delete(IC)]));
});

function nav(req) {
	var u = new URL(req.url), key = clean(u);
	return caches.open(PC).then(function (c) {
		var net = function () {
			return fetch(req).then(function (res) {
				var app = res.headers.get("X-PetGo-App");
				/* сохраняет страницы сам скрипт кабинета (без одноразовых сообщений); здесь только чистим при выходе */
				if (app === "0") { caches.delete(PC); caches.delete(IC); }
				return res;
			});
		};
		/* вкладка уже есть на телефоне: показываем сразу, свежие данные страница подтянет сама */
		if (plain(u)) {
			return c.match(key).then(function (hit) {
				return hit || net().catch(function () { return c.match(clean(HOME)).then(function (h) { return h || offline(); }); });
			});
		}
		return net().catch(function () { return c.match(key).then(function (h) { return h || offline(); }); });
	});
}

function cacheFirst(req, name, max) {
	return caches.open(name).then(function (c) {
		return c.match(req).then(function (hit) {
			if (hit) return hit;
			return fetch(req).then(function (res) {
				if (res.ok) {
					c.put(req, res.clone());
					if (max) c.keys().then(function (ks) { for (var i = 0; i < ks.length - max; i++) c.delete(ks[i]); });
				}
				return res;
			});
		});
	});
}

self.addEventListener("fetch", function (e) {
	var req = e.request;
	if (req.method !== "GET") return;
	var u = new URL(req.url);
	if (req.mode === "navigate") {
		if (isApp(u) && !u.searchParams.has("petgo_sw")) e.respondWith(nav(req));
		return;
	}
	if (u.origin !== self.location.origin) return;
	if (u.pathname.indexOf("/petgo-cabinet/assets/") > -1) e.respondWith(cacheFirst(req, SC));
	else if (/\/admin-post\.php$/.test(u.pathname) && u.searchParams.get("action") === "petgo_img") e.respondWith(cacheFirst(req, IC, 400));
});

/* уведомления: текст берётся у сервера только для вошедшего сотрудника */
self.addEventListener("push", function (e) {
	e.waitUntil(self.registration.pushManager.getSubscription().then(function (s) {
		if (!s) return [];
		var fd = new URLSearchParams();
		fd.append("action", "petgo_push_peek");
		fd.append("e", s.endpoint);
		return fetch(C.peek, { method: "POST", body: fd, credentials: "include" }).then(function (r) { return r.json(); }).then(function (d) { return (d && d.items) || []; });
	}).catch(function () { return []; }).then(function (items) {
		if (!items.length) items = [{ t: "PetGo", b: "Новое событие в кабинете", u: C.home }];
		return self.clients.matchAll({ type: "window" }).then(function (cl) {
			/* кабинет открыт на экране — о сообщениях не мешаем, чат и так обновится */
			var seen = cl.some(function (c) { return c.visibilityState === "visible" && c.focused; });
			var show = items.filter(function (i) { return !(seen && /^c\d+$/.test(i.g || "")); });
			if (!show.length) { cl.forEach(function (c) { c.postMessage({ tick: 1 }); }); return; }
			return Promise.all(show.map(function (i) {
				return self.registration.showNotification(i.t, { body: i.b, icon: C.icon, badge: C.icon, tag: "petgo-" + (i.g || "n"), renotify: true, data: { u: i.u || C.home } });
			}));
		});
	}));
});

self.addEventListener("notificationclick", function (e) {
	e.notification.close();
	var u = (e.notification.data && e.notification.data.u) || C.home;
	e.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (l) {
		for (var i = 0; i < l.length; i++) {
			if (isApp(new URL(l[i].url)) && "focus" in l[i]) {
				l[i].postMessage({ go: u });
				return l[i].focus();
			}
		}
		return self.clients.openWindow(u);
	}));
});
