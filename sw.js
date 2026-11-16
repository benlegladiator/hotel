const CACHE_NAME = 'alma-hotel-pwa-v3';

const ASSETS_TO_CACHE = [
  './index.php',
  './dashboard.php',
  './modules/checkin_checkout/express_checkin.php',
  './assets/css/style.css',
  './assets/css/print.css',
  './assets/js/main.js',
  './assets/js/hotel-offline-sync.js',
  './assets/img/pwa/icon-192.png',
  './assets/img/pwa/icon-512.png',
  './assets/img/pwa/favicon.png',
  './manifest.json',
  'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then(async (cache) => {
      console.log('[Hotel PWA SW] Mise en cache des ressources critiques...');
      await Promise.allSettled(
        ASSETS_TO_CACHE.map(async (url) => {
          try {
            const response = await fetch(url, { cache: 'reload' });
            if (response && (response.ok || response.type === 'opaque')) {
              await cache.put(url, response);
            }
          } catch (e) {
            console.warn('[Hotel PWA SW] Non pré-chargé:', url);
          }
        })
      );
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((k) => {
          if (k !== CACHE_NAME) {
            return caches.delete(k);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  if (!event.request.url.startsWith('http')) return;

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const resClone = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, resClone);
          });
        }
        return networkResponse;
      })
      .catch(async () => {
        const cached = await caches.match(event.request, { ignoreSearch: true });
        if (cached) return cached;

        const isHtml = event.request.mode === 'navigate' || 
          (event.request.headers.get('accept') && event.request.headers.get('accept').includes('text/html'));
        if (isHtml) {
          const expressFallback = await caches.match('./modules/checkin_checkout/express_checkin.php', { ignoreSearch: true });
          if (expressFallback) return expressFallback;
          const dashFallback = await caches.match('./dashboard.php', { ignoreSearch: true });
          if (dashFallback) return dashFallback;
        }

        return new Response('Mode Hors-Ligne Alma Hotel : cette ressource n\'est pas encore disponible en cache.', {
          status: 503,
          headers: { 'Content-Type': 'text/plain; charset=utf-8' }
        });
      })
  );
});
