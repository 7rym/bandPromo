/**
 * Portable Service Worker
 * Enables offline functionality and PWA installation
 * Works on any domain - cache name is dynamically generated from hostname
 */

// Generate cache name from hostname for portability
// e.g., 'myband.com' -> 'myband-app-v2'
const hostname = self.location.hostname.replace(/\./g, '.').split('.')[0];
const CACHE_NAME = `${hostname}-app-v6`;
const ASSETS_TO_CACHE = [
    '/',
    '/site.webmanifest'
];

// Install: Cache assets (with error handling)
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[Service Worker] Caching assets');
            // Cache critical assets, but don't fail if some are missing
            return Promise.allSettled(
                ASSETS_TO_CACHE.map(url => cache.add(url))
            ).then((results) => {
                results.forEach((result, index) => {
                    if (result.status === 'rejected') {
                        console.warn(`[Service Worker] Failed to cache ${ASSETS_TO_CACHE[index]}:`, result.reason);
                    }
                });
            });
        }).catch(error => {
            console.error('[Service Worker] Installation error:', error);
        })
    );
    self.skipWaiting();
});

// Activate: Clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch: Network-first strategy with cache fallback
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip cross-origin requests
    if (url.origin !== self.location.origin) {
        return;
    }

    // Skip media streaming/static delivery and browser-managed assets.
    // Caching /media/* (especially audio/video) contends with the PHP built-in
    // server and bloated Cache Storage; let the browser HTTP cache handle it.
    if (request.headers.has('range') ||
        url.pathname === '/biblioteca/audio.php' ||
        url.pathname.startsWith('/media/') ||
        url.pathname === '/site.webmanifest' ||
        url.pathname === '/favicon.ico' ||
        url.pathname.startsWith('/media/icons/favicon')) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                // Only cache successful responses (200-299) excluding partial content (206)
                if (response && response.status >= 200 && response.status < 300 && response.status !== 206) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone).catch(error => {
                            console.warn('[Service Worker] Cache put failed for', request.url, error);
                        });
                    }).catch(error => {
                        console.warn('[Service Worker] Cache open failed:', error);
                    });
                }
                return response;
            })
            .catch(() => {
                // Return cached version if network fails
                return caches.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    // Return offline page or placeholder if needed
                    return new Response('Service temporarily unavailable', {
                        status: 503,
                        statusText: 'Service Unavailable'
                    });
                });
            })
    );
});

// Handle messages from clients
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
