const CACHE_NAME = 'finance-board-v2'; // Bumped version
const urlsToCache = [
    'assets/app_icon_512.png',
    'assets/tailwind.css'
];

self.addEventListener('install', event => {
    // Force the waiting service worker to become the active service worker.
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('activate', event => {
    // Take control of all pages immediately
    event.waitUntil(clients.claim());
    // Clean up old caches
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', event => {
    // Network First strategy
    event.respondWith(
        fetch(event.request)
            .then(response => {
                // If network works, return it
                return response;
            })
            .catch(() => {
                // If network fails, try cache
                return caches.match(event.request);
            })
    );
});
