const CACHE = 'gestao-acesso-v1';
const PRECACHE = ['/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.pathname.startsWith('/patio/') || url.pathname.startsWith('/patrimonios/scan/')) {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(event.request))
        );
        return;
    }

    if (url.pathname.match(/\.(css|js|png|jpg|webp|woff2?)$/)) {
        event.respondWith(
            caches.open(CACHE).then((cache) =>
                fetch(event.request)
                    .then((response) => {
                        if (response.ok) {
                            cache.put(event.request, response.clone());
                        }
                        return response;
                    })
                    .catch(() => cache.match(event.request))
            )
        );
    }
});
