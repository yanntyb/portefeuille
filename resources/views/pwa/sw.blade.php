const CACHE_NAME = 'dashboard-invest-v2';
const STATIC_ASSETS = [];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.pathname.match(/\.(css|js|png|jpg|jpeg|svg|gif|woff2?|ttf|eot)$/)) {
        event.respondWith(
            caches.open(CACHE_NAME).then((cache) =>
                cache.match(request).then((cached) => {
                    if (cached) {
                        return cached;
                    }

                    return fetch(request).then((response) => {
                        if (response.ok) {
                            cache.put(request, response.clone());
                        }

                        return response;
                    });
                })
            )
        );

        return;
    }

    // Ne pas cacher les pages HTML (dépendent de la session/user)
    return;
});
