/* MotoBook Rider Service Worker — offline-first, background-sync proof-of-delivery queue
   Scope: /IM-101/motobook/A-rider/public/build/  (start_url lives inside)  */

const VERSION = 'mb-rider-v1.0.0';
const STATIC_CACHE = `mb-rider-static-${VERSION}`;
const RUNTIME_CACHE = `mb-rider-runtime-${VERSION}`;
const OFFLINE_QUEUE = 'mb-rider-offline-queue';

const CORE_STATIC = [
  '/IM-101/motobook/A-rider/public/build/',
  '/IM-101/motobook/A-rider/public/build/manifest.webmanifest',
  '/IM-101/motobook/A-rider/public/build/assets/rider-icon-192.svg',
  '/IM-101/motobook/A-rider/public/build/assets/rider-icon-512.svg',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(async (cache) => {
      await cache.addAll(CORE_STATIC);
      try { await cache.add('/IM-101/motobook/A-rider/public/build/index.html'); } catch (_) {}
      return self.skipWaiting();
    }),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((k) => ![STATIC_CACHE, RUNTIME_CACHE].includes(k))
          .map((k) => caches.delete(k)),
      ).then(() => self.clients.claim()),
    ),
  );
});

function sameOrigin(u) {
  try { return new URL(u, self.location.href).origin === self.location.origin; }
  catch { return false; }
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    /* Queue mutating requests (POST/PUT/PATCH/DELETE) when offline so rider
       can submit PoD / telemetry / deposit from a basement. */
    if (['POST','PUT','PATCH','DELETE'].includes(req.method) && sameOrigin(req.url)) {
      event.respondWith(
        fetch(req.clone()).catch(async () => {
          try {
            const clone = req.clone();
            const bodyBuffer = await clone.arrayBuffer();
            const headers = Object.fromEntries([...clone.headers.entries()]);
            await self.registration?.sync?.register('mb-rider-sync-queue');
            const store = await openQueueStore();
            await store.add({
              method: clone.method,
              url: clone.url,
              headers,
              body: bodyBuffer,
              queuedAt: Date.now(),
              id: `${Date.now()}-${cryptoRandomId()}`,
            });
          } catch (err) {
            // eslint-disable-next-line no-console
            console.warn('[SW] offline queue failed to enqueue', err);
          }
          return new Response(
            JSON.stringify({ ok: false, offline_queued: true, message: 'Offline — will resend automatically.' }),
            { status: 202, headers: { 'Content-Type': 'application/json' } },
          );
        }),
      );
    }
    return;
  }

  /* GET static assets → cache first; network fallback. */
  const url = new URL(req.url, self.location.href);
  const isBuild = url.pathname.startsWith('/IM-101/motobook/A-rider/public/build/assets/');
  if (isBuild) {
    event.respondWith(
      caches.match(req).then((cached) => cached || fetch(req.clone()).then((res) => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then((c) => c.put(req, copy)).catch(() => {});
        return res;
      }).catch(() => cached || Response.error())),
    );
    return;
  }

  /* GET API / session reads → stale while revalidate. */
  const isApi = url.pathname.includes('/A-rider/api/');
  if (isApi) {
    event.respondWith(
      caches.open(RUNTIME_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        const netPromise = fetch(req.clone()).then((res) => {
          cache.put(req, res.clone()).catch(() => {});
          return res;
        }).catch(() => cached || Response.error());
        return cached || netPromise;
      }),
    );
    return;
  }

  /* Other GETs → network first (index.html / PHP shells). */
  event.respondWith(
    fetch(req.clone()).then((res) => {
      const copy = res.clone();
      caches.open(RUNTIME_CACHE).then((c) => c.put(req, copy)).catch(() => {});
      return res;
    }).catch(() => caches.match(req).then((cached) => cached || Response.error())),
  );
});

/* Background sync: flush queued POST/PUT requests when rider comes back online. */
self.addEventListener('sync', (event) => {
  if (!event.tag.startsWith('mb-rider')) return;
  event.waitUntil(flushQueue());
});

self.addEventListener('message', async (ev) => {
  if (ev.data?.type === 'MB_RIDER_FLUSH') {
    ev.waitUntil?.(flushQueue());
    ev.ports?.[0]?.postMessage({ ok: true, flushed: await flushQueue() });
  }
});

async function flushQueue() {
  const store = await openQueueStore();
  const items = await store.getAll();
  let okCount = 0;
  for (const it of items) {
    try {
      await fetch(it.url, {
        method: it.method,
        headers: it.headers,
        body: it.body && it.body.byteLength ? it.body : undefined,
      });
      await store.delete(it.id);
      okCount += 1;
    } catch {
      /* keep in queue — will retry on next sync */
    }
  }
  self.clients.matchAll().then((clients) => {
    for (const c of clients) c.postMessage({ type: 'MB_RIDER_QUEUE_FLUSHED', okCount, remaining: items.length - okCount });
  });
  return okCount;
}

/* Minimal IndexedDB KV helper for offline queue. */
function openQueueStore() {
  return new Promise((resolve, reject) => {
    const dbReq = indexedDB.open(OFFLINE_QUEUE, 1);
    dbReq.onerror = () => reject(dbReq.error);
    dbReq.onupgradeneeded = () => {
      const db = dbReq.result;
      if (!db.objectStoreNames.contains('requests')) {
        const os = db.createObjectStore('requests', { keyPath: 'id' });
        os.createIndex('queuedAt', 'queuedAt', { unique: false });
      }
    };
    dbReq.onsuccess = () => {
      const db = dbReq.result;
      const tx = db.transaction('requests', 'readwrite');
      const os = tx.objectStore('requests');
      resolve({
        add: (r) => new Promise((y, n) => { const q = os.add(r); q.onsuccess=()=>y(q.result); q.onerror=()=>n(q.error); }),
        getAll: () => new Promise((y, n) => { const q = os.getAll(); q.onsuccess=()=>y(q.result); q.onerror=()=>n(q.error); }),
        delete: (id) => new Promise((y, n) => { const q = os.delete(id); q.onsuccess=()=>y(); q.onerror=()=>n(q.error); }),
      });
    };
  });
}

function cryptoRandomId() {
  try {
    const a = new Uint8Array(8);
    crypto.getRandomValues(a);
    return Array.from(a).map((b) => b.toString(16).padStart(2, '0')).join('');
  } catch {
    return `${Math.random().toString(36).slice(2,10)}`;
  }
}
