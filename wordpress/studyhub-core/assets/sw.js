/* StudyHub service worker — один на все приложения (панель, кабинет студента).
   Сохраняет оболочку приложений, никогда не кэширует API, админку и файлы чатов. */
const VERSION = 'studyhub-__VERSION__';
const ASSETS = __ASSETS__;
const PAGES = __PAGES__;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION).then((cache) => Promise.all(ASSETS.map((a) => cache.add(a).catch(() => null)))).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k.startsWith('studyhub-') && k !== VERSION).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

function isAppPage(url) {
  const clean = url.href.split('#')[0].split('?')[0];
  return PAGES.some((p) => p.split('?')[0] === clean) || url.searchParams.has('studyhub_page');
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes('/wp-json/') || url.searchParams.has('rest_route') || url.pathname.includes('/wp-admin/') || url.pathname.includes('/wp-login')) return;

  if (req.mode === 'navigate') {
    if (!isAppPage(url)) return;
    // Сначала сеть (свежие данные и вход), без сети — сохранённая копия.
    event.respondWith(
      fetch(req).then((res) => {
        if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
        return res;
      }).catch(() => caches.match(req).then((hit) => hit || caches.match(PAGES[0])))
    );
    return;
  }
  if (ASSETS.some((a) => req.url.split('?')[0] === new URL(a, self.location.href).href.split('?')[0])) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
  }
});
