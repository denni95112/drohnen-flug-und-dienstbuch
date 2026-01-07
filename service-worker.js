const CACHE_NAME = 'dashboard-cache-v1';
const urlsToCache = [
    '/',
    '/index.php',
    '/css/styles.css',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png'
];

// Install the service worker
self.addEventListener('install', (event) => {
    console.log('Service Worker installing.');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            // Cache each URL individually to handle failures gracefully
            return Promise.allSettled(
                urlsToCache.map((url) => {
                    return cache.add(url).catch((error) => {
                        console.warn(`Failed to cache ${url}:`, error);
                        // Return null to indicate failure, but don't throw
                        return null;
                    });
                })
            );
        })
    );
    // Skip waiting to activate immediately
    self.skipWaiting();
});

// Activate the service worker
self.addEventListener('activate', (event) => {
    console.log('Service Worker activated.');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Fetch requests and serve cached assets
self.addEventListener('fetch', (event) => {
    console.log('Fetching:', event.request.url);
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});
