/// <reference lib="webworker" />

import {
    cacheKeyFor,
    classifyRequest,
    pagePayloadFromDocument,
    partialKeysOf,
    rescuedPartialPayload,
    type InertiaPage,
    type RequestShape,
} from '@/lib/swCache';

/** Constantes préfixées par `ServiceWorkerScript` : elles ne viennent pas du bundle. */
declare const self: ServiceWorkerGlobalScope & {
    CACHE_VERSION: string;
    PRECACHE_URLS: string[];
    OFFLINE_URL: string;
};

export type SwMessage =
    | { type: 'FRESH' }
    | { type: 'SERVED_STALE'; cachedAt: number | null };

const CACHE_NAME = `argent-${self.CACHE_VERSION}`;

/** Horodatage du dernier succès réseau, porté par la réponse en cache elle-même. */
const CACHED_AT_HEADER = 'X-Sw-Cached-At';

const shapeOf = (request: Request): RequestShape => ({
    method: request.method,
    url: request.url,
    mode: request.mode,
    inertia: request.headers.get('X-Inertia') !== null,
    partialData: request.headers.get('X-Inertia-Partial-Data'),
});

const broadcast = async (message: SwMessage): Promise<void> => {
    const clients = await self.clients.matchAll({ type: 'window' });

    for (const client of clients) {
        client.postMessage(message);
    }
};

const stamped = (response: Response): Response => {
    const headers = new Headers(response.headers);
    headers.set(CACHED_AT_HEADER, String(Date.now()));

    return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers,
    });
};

const cachedAtOf = (response: Response): number | null => {
    const raw = response.headers.get(CACHED_AT_HEADER);

    return raw === null ? null : Number(raw);
};

/** URLs hashées : une correspondance en cache est vraie par construction, jamais revalidée. */
const cacheFirst = async (request: Request): Promise<Response> => {
    const cache = await caches.open(CACHE_NAME);
    const hit = await cache.match(request.url);

    if (hit !== undefined) {
        return hit;
    }

    const response = await fetch(request);

    if (response.ok) {
        await cache.put(request.url, stamped(response.clone()));
    }

    return response;
};

const networkFirst = async (request: Request): Promise<Response> => {
    const cache = await caches.open(CACHE_NAME);

    try {
        const response = await fetch(request);

        if (response.ok) {
            await cache.put(request.url, stamped(response.clone()));
            await broadcast({ type: 'FRESH' });
        }

        return response;
    } catch {
        const hit = await cache.match(request.url);

        if (hit !== undefined) {
            await broadcast({ type: 'SERVED_STALE', cachedAt: cachedAtOf(hit) });

            return hit;
        }

        const offline = await cache.match(self.OFFLINE_URL);

        return offline ?? Response.error();
    }
};

/**
 * Base de la réponse de secours : la réponse Inertia complète si elle est en cache, sinon la
 * charge utile portée par le document HTML.
 */
const cachedPagePayload = async (cache: Cache, shape: RequestShape): Promise<InertiaPage | null> => {
    const full = await cache.match(cacheKeyFor({ ...shape, partialData: null }));

    if (full !== undefined) {
        return (await full.json()) as InertiaPage;
    }

    const document = await cache.match(new URL(shape.url).toString());

    return document === undefined ? null : pagePayloadFromDocument(await document.text());
};

const rescuedResponse = async (cache: Cache, shape: RequestShape): Promise<Response> => {
    const page = await cachedPagePayload(cache, shape);

    if (page === null) {
        return Response.error();
    }

    await broadcast({ type: 'SERVED_STALE', cachedAt: null });

    return new Response(JSON.stringify(rescuedPartialPayload(page, partialKeysOf(shape.partialData))), {
        headers: {
            'Content-Type': 'application/json',
            'X-Inertia': 'true',
            Vary: 'X-Inertia',
        },
    });
};

const staleWhileRevalidate = async (request: Request, shape: RequestShape): Promise<Response> => {
    const cache = await caches.open(CACHE_NAME);
    const key = cacheKeyFor(shape);
    const hit = await cache.match(key);

    const network = fetch(request)
        .then(async (response: Response): Promise<Response | null> => {
            if (!response.ok) {
                return response;
            }

            await cache.put(key, stamped(response.clone()));
            await broadcast({ type: 'FRESH' });

            return response;
        })
        .catch((): null => null);

    if (hit !== undefined) {
        /** Revalidation en arrière-plan : son échec ne fait que signaler la péremption. */
        void network.then(async (response: Response | null): Promise<void> => {
            if (response === null) {
                await broadcast({ type: 'SERVED_STALE', cachedAt: cachedAtOf(hit) });
            }
        });

        return hit;
    }

    const response = await network;

    if (response !== null) {
        return response;
    }

    return shape.inertia && shape.partialData !== null
        ? rescuedResponse(cache, shape)
        : Response.error();
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
    if ((event.data as SwMessage | { type: string } | null)?.type === 'SKIP_WAITING') {
        void self.skipWaiting();
    }
});

self.addEventListener('fetch', (event: FetchEvent): void => {
    const shape = shapeOf(event.request);

    switch (classifyRequest(shape)) {
        case 'passthrough':
            return;
        case 'asset':
            event.respondWith(cacheFirst(event.request));

            return;
        case 'navigation':
            event.respondWith(networkFirst(event.request));

            return;
        default:
            event.respondWith(staleWhileRevalidate(event.request, shape));
    }
});
