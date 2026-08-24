const CACHE_NAME = 'bsis-attendance-v10';
const ASSETS_TO_CACHE = [
  '/',
  '/student',
  '/css/bsis-theme.css',
  '/images/bsis-logo.png',
  '/manifest.json',
  '/js/storage.js',
  '/js/student-app.js'
];

// Install Event — Cache Core Assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activate Event — Clean Old Caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Fetch Event — Network-First Strategy with Cache Fallback
self.addEventListener('fetch', (event) => {
  if (event.request.url.includes('/api/')) {
    // API calls bypass cache for live data
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response && response.status === 200) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        return caches.match(event.request).then((cachedResponse) => {
          return cachedResponse || caches.match('/student');
        });
      })
  );
});
