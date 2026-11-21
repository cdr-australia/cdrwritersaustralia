'use strict';
const VERSION = 'cwa-__BUILD__';
const PAGES = VERSION + '-pages';
const ASSETS = VERSION + '-assets';
const OFFLINE = '/offline.html';

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(PAGES)
      .then((c) => c.add(OFFLINE))
      .catch(() => {})
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin || url.pathname === '/sw.js') return;

  if (req.mode === 'navigate') {
    e.respondWith(networkFirst(req));
  } else if (/\.(?:css|js|png|jpe?g|webp|avif|svg|woff2?|webmanifest)$/i.test(url.pathname)) {
    e.respondWith(staleWhileRevalidate(req));
  }
});

/* pages: always try fresh HTML, fall back to the last copy, then the offline page */
async function networkFirst(req) {
  const cache = await caches.open(PAGES);
  try {
    const res = await fetch(req);
    if (res && res.ok) cache.put(req, res.clone());
    return res;
  } catch (_) {
    return (await cache.match(req)) || (await cache.match(OFFLINE)) || Response.error();
  }
}

/* assets: serve instantly from cache, refresh in the background */
async function staleWhileRevalidate(req) {
  const cache = await caches.open(ASSETS);
  const cached = await cache.match(req);
  const fresh = fetch(req)
    .then((res) => { if (res && res.ok) cache.put(req, res.clone()); return res; })
    .catch(() => cached);
  return cached || fresh;
}