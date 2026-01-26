const VERSION = new URL(location).searchParams.get('v') || '1.0.0';
const CACHE_NAME = 'billpay-cache-' + VERSION;

const ASSETS = [
  '/offline.html',
  '/uploads/logo.png'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    })
  );
});

self.addEventListener('fetch', (event) => {
  // Navigation requests (HTML pages) -> Network First
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(() => caches.match(event.request))
        .catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // Other requests -> Cache First, then Network
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
