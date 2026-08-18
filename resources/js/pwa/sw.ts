/// <reference lib="webworker" />

import { classifyRequest, type RequestShape } from '@/lib/swCache';
import { cacheFirst, networkFirst, staleWhileRevalidate, type Broadcast, type SwMessage } from './strategies';

/** Constantes préfixées par `ServiceWorkerScript` : elles ne viennent pas du bundle. */
declare const self: ServiceWorkerGlobalScope & {
    CACHE_VERSION: string;
    PRECACHE_URLS: string[];
    OFFLINE_URL: string;
};

const CACHE_NAME = `argent-${self.CACHE_VERSION}`;

const shapeOf = (request: Request): RequestShape => ({
    method: request.method,
    url: request.url,
    mode: request.mode,
    inertia: request.headers.get('X-Inertia') !== null,
    partialData: request.headers.get('X-Inertia-Partial-Data'),
});

const broadcast: Broadcast = async (message: SwMessage): Promise<void> => {
    const clients = await self.clients.matchAll({ type: 'window' });

    for (const client of clients) {
        client.postMessage(message);
    }
};

self.addEventListener('install', (event: ExtendableEvent): void => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache: Cache): Promise<void> => cache.addAll(self.PRECACHE_URLS)),
    );
});

self.addEventListener('activate', (event: ExtendableEvent): void => {
    event.waitUntil((async (): Promise<void> => {
        const names = await caches.keys();

        await Promise.all(
            names
                .filter((name: string): boolean => name !== CACHE_NAME)
                .map((name: string): Promise<boolean> => caches.delete(name)),
        );

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event: ExtendableMessageEvent): void => {
    if ((event.data as { type?: string } | null)?.type === 'SKIP_WAITING') {
        void self.skipWaiting();
    }
});

self.addEventListener('fetch', (event: FetchEvent): void => {
    const shape = shapeOf(event.request);

    switch (classifyRequest(shape)) {
        case 'passthrough':
            return;
        case 'asset':
            event.respondWith(cacheFirst(event.request, CACHE_NAME));

            return;
        case 'navigation':
            event.respondWith(networkFirst(event.request, CACHE_NAME, self.OFFLINE_URL, broadcast));

            return;
        default:
            event.respondWith(staleWhileRevalidate(event, shape, CACHE_NAME, broadcast));
    }
});
