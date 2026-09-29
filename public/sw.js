const CACHE_NAME = 'vox-static-v3';
const STATIC_ASSETS = [
  './manifest.json',
  './icon.svg',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key)),
      ))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const requestUrl = new URL(event.request.url);
  const isStaticAsset = requestUrl.origin === self.location.origin
    && STATIC_ASSETS.some(asset => requestUrl.href === new URL(asset, self.registration.scope).href);

  if (isStaticAsset) {
    event.respondWith(caches.match(event.request).then(response => response || fetch(event.request)));
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => new Response(
        '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0b1d31"><title>Vox is offline</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#070e1c;color:#e2e8f0;font:16px system-ui,sans-serif}main{max-width:30rem;padding:2rem;text-align:center}h1{color:#67e8f9}</style><main><h1>Vox is offline</h1><p>Reconnect and refresh to load current OSSEC alerts.</p></main>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
      )),
    );
  }
});
