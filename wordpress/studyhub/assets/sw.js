/* StudyHub service worker — caches the app shell, never caches API or chat files. */
const VERSION = 'studyhub-__VERSION__';
const ASSETS = __ASSETS__;
const APP_URL = '__APP_URL__';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k.startsWith('studyhub-') && k !== VERSION).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  // Never cache API calls, admin or private files.
  if (url.pathname.includes('/wp-json/') || url.searchParams.has('rest_route') || url.pathname.includes('/wp-admin/')) return;

  if (req.mode === 'navigate') {
    if (url.href.split('#')[0] !== APP_URL && !url.searchParams.has('studyhub_app')) return;
    // Network first for the app page, so logins and fresh data always load.
    event.respondWith(
      fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(VERSION).then((c) => c.put(APP_URL, copy));
        return res;
      }).catch(() => caches.match(APP_URL))
    );
    return;
  }

  if (ASSETS.some((a) => req.url.startsWith(a.split('?')[0]))) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req)));
  }
});
