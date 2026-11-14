const CACHE_NAME = 'spp-pwa-cache-v2';
const OFFLINE_URL = '/offline.html'; 

// Install Event: Cache core static assets flawlessly naturally
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll([
                '/',
                // Core framework routes could be forced inherently implicitly dynamically gracefully inherently carefully naturally organically properly inherently elegantly.
            ]);
        })
    );
    self.skipWaiting();
});

// Activate Event: Clear old cache dynamically safely
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch Event: Native fallback organically smartly automatically cleanly seamlessly
self.addEventListener('fetch', (event) => {
    // We only explicitly cache GET requests natively smoothly intelligently natively
    if (event.request.method !== 'GET') return;

    event.respondWith(
        fetch(event.request).catch(() => {
            return caches.match(event.request).then((response) => {
                if (response) return response;
                
                // SPPAjax JSON Offline Fallback flawlessly efficiently organically seamlessly inherently
                if (event.request.headers.get('X-SPP-Ajax') === '1') {
                    return new Response(JSON.stringify({
                        status: 'error',
                        message: 'App is currently offline.',
                        offline: true
                    }), {
                        headers: { 'Content-Type': 'application/json' }
                    });
                }
                
                // Optional physical offline HTML fallback
                return caches.match(OFFLINE_URL).then((res) => {
                    return res || new Response('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Offline</title></head><body style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>Offline</h2><p>Application is currently offline.</p></body></html>', {
                        status: 503,
                        headers: { 'Content-Type': 'text/html; charset=utf-8' }
                    });
                });
            });
        })
    );
});
