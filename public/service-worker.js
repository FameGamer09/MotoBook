const CACHE_PREFIX = 'motobook-pwa-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;

self.addEventListener('install', (event) => {
  const appBase = new URL(self.registration.scope);
  const offlinePage = new URL('offline.html', appBase).href;
  const icon192 = new URL('images/motobook-app-192.png', appBase).href;
  const icon512 = new URL('images/motobook-app-512.png', appBase).href;

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll([offlinePage, icon192, icon512]))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys
          .filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME)
          .map((key) => caches.delete(key)),
      ))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(request.url);
  if (requestUrl.origin !== self.location.origin) {
    return;
  }

  if (request.mode === 'navigate') {
    const offlinePage = new URL('offline.html', self.registration.scope).href;

    event.respondWith(
      fetch(request).catch(async () => {
        const cachedOfflinePage = await caches.match(offlinePage);
        return cachedOfflinePage || Response.error();
      }),
    );
    return;
  }

  const isStaticAsset = ['style', 'script', 'image', 'font'].includes(request.destination)
    || /\.(?:css|js|mjs|png|jpe?g|webp|svg|ico|woff2?)$/i.test(requestUrl.pathname);

  if (!isStaticAsset) {
    return;
  }

  event.respondWith(
    caches.match(request).then(async (cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      const response = await fetch(request);
      if (response.ok && response.type === 'basic') {
        const cache = await caches.open(CACHE_NAME);
        await cache.put(request, response.clone());
      }

      return response;
    }),
  );
});
