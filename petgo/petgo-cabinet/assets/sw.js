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
	return new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' + C.off.t + '</title><body style="margin:0;min-height:100vh;display:grid;place-items:center;font:16px system-ui,sans-serif;background:#ffffff;color:#12201e;text-align:center;padding:24px"><div><img src="' + C.icon + '" width="72" height="72" style="border-radius:20px" alt=""><h1 style="font-size:22px;margin:18px 0 6px">' + C.off.t + '</h1><p style="color:#6b7a77;margin:0 0 20px">' + C.off.b + '</p><button onclick="location.reload()" style="font:600 16px system-ui;padding:12px 22px;border:0;border-radius:12px;background:#0b6b5f;color:#fff">' + C.off.r + '</button></div>', { headers: { "Content-Type": "text/html; charset=utf-8" } });
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
