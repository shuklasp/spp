/**
 * SPPDocs Enterprise Offline Service Worker
 * Network-First for dynamic HTML pages with fallback to cache.
 * Cache-First for static assets (CSS, JS, fonts, images).
 */

const CACHE_NAME = 'sppdocs-cache-v1';
const STATIC_ASSETS = [
    '/school1/public/manifest.json',
    '/school1/public/assets/logo.jpg'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Only cache GET requests
    if (req.method !== 'GET') return;

    // Ignore cross-origin non-http(s) requests
    if (!url.protocol.startsWith('http')) return;

    // Static Assets: Cache-First
    if (url.pathname.match(/\.(css|js|woff2?|ttf|eot|png|jpe?g|svg|ico)$/)) {
        event.respondWith(
            caches.match(req).then((cached) => {
                if (cached) return cached;
                return fetch(req).then((response) => {
                    if (response && response.status === 200) {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // HTML & Pages: Network-First with Offline Cache Fallback
    event.respondWith(
        fetch(req).then((response) => {
            if (response && response.status === 200) {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
            }
            return response;
        }).catch(() => {
            return caches.match(req).then((cached) => {
                if (cached) return cached;
                return new Response(
                    `<!DOCTYPE html><html><head><meta charset="utf-8"><title>Offline - SPPDocs</title><style>body{font-family:system-ui,sans-serif;padding:3rem;text-align:center;color:#1e293b;background:#f8fafc;}.card{background:#fff;max-width:480px;margin:0 auto;padding:2rem;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);}h1{color:#ea580c;margin-top:0;}</style></head><body><div class="card"><h1>⚡ SPPDocs Offline Mode</h1><p>You are currently offline and this page was not yet cached.</p><button onclick="window.location.reload()" style="background:#ea580c;color:#fff;border:none;padding:0.6rem 1.2rem;border-radius:6px;cursor:pointer;font-weight:bold;">Retry Connection</button></div></body></html>`,
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            });
        })
    );
});
