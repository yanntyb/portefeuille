/// <reference lib="webworker" />

import {
    cacheKeyFor,
    pagePayloadFromDocument,
    partialKeysOf,
    rescuedPartialPayload,
    type InertiaPage,
    type RequestShape,
} from '@/lib/swCache';

export type SwMessage =
    | { type: 'FRESH' }
    | { type: 'SERVED_STALE'; cachedAt: number | null };

/** Diffuse un message aux fenêtres clientes ; injecté par `sw.ts`, seul à connaître `self.clients`. */
export type Broadcast = (message: SwMessage) => Promise<void>;

/** Horodatage du dernier succès réseau, porté par la réponse en cache elle-même. */
const CACHED_AT_HEADER = 'X-Sw-Cached-At';

export const stamped = (response: Response): Response => {
    const headers = new Headers(response.headers);
    headers.set(CACHED_AT_HEADER, String(Date.now()));

    return new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers,
    });
};

export const cachedAtOf = (response: Response): number | null => {
    const raw = response.headers.get(CACHED_AT_HEADER);

    return raw === null ? null : Number(raw);
};

/**
 * Écriture de cache best-effort : à `CACHE_VERSION` constante, le cache accumule indéfiniment
 * chaque document et chaque charge utile Inertia. Un `QuotaExceededError` ne doit jamais dégrader
 * une réponse réseau déjà obtenue avec succès.
 */
const putQuietly = async (cache: Cache, key: string, response: Response): Promise<void> => {
    try {
        await cache.put(key, response);
    } catch {
        /* Best-effort : voir le commentaire de la fonction. */
    }
};

/** Clé réservée : aucune vraie requête ne peut jamais cibler ce chemin, donc aucune collision. */
const STATUS_KEY = '/__sw-status';

/**
 * Persiste le dernier état de fraîcheur connu dans le cache lui-même. Une diffusion `postMessage`
 * ne suffit pas : sur une navigation, le client qui la reçoit est celui qu'on est en train de
 * quitter, pas la page qui vient de se charger ; et une simple variable de module ne survivrait
 * pas à la terminaison du worker entre deux requêtes. Le cache, lui, survit aux deux — le client
 * n'a qu'à le lui redemander à l'amorçage (voir `readStatus`).
 */
const writeStatus = async (cache: Cache, status: SwMessage): Promise<void> => {
    await putQuietly(cache, STATUS_KEY, new Response(JSON.stringify(status)));
};

/** Relit le dernier état persisté par `writeStatus`, ou `null` si rien n'a encore été écrit. */
export const readStatus = async (cacheName: string): Promise<SwMessage | null> => {
    const cache = await caches.open(cacheName);
    const hit = await cache.match(STATUS_KEY);

    if (hit === undefined) {
        return null;
    }

    try {
        return (await hit.json()) as SwMessage;
    } catch {
        return null;
    }
};

/** Les deux mêmes voies à chaque changement d'état : la diffusion immédiate, et sa persistance. */
const notify = async (cache: Cache, broadcast: Broadcast, message: SwMessage): Promise<void> => {
    await writeStatus(cache, message);
    await broadcast(message);
};

/** URLs hashées : une correspondance en cache est vraie par construction, jamais revalidée. */
export const cacheFirst = async (request: Request, cacheName: string): Promise<Response> => {
    const cache = await caches.open(cacheName);
    const hit = await cache.match(request.url);

    if (hit !== undefined) {
        return hit;
    }

    const response = await fetch(request);

    if (response.ok) {
        await putQuietly(cache, request.url, stamped(response.clone()));
    }

    return response;
};

export const networkFirst = async (
    request: Request,
    cacheName: string,
    offlineUrl: string,
    broadcast: Broadcast,
): Promise<Response> => {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);

        if (response.ok) {
            await putQuietly(cache, request.url, stamped(response.clone()));
            await notify(cache, broadcast, { type: 'FRESH' });
        }

        return response;
    } catch {
        const hit = await cache.match(request.url);

        if (hit !== undefined) {
            await notify(cache, broadcast, { type: 'SERVED_STALE', cachedAt: cachedAtOf(hit) });

            return hit;
        }

        const offline = await cache.match(offlineUrl);

        return offline ?? Response.error();
    }
};

/**
 * Base de la réponse de secours : la réponse Inertia complète si elle est en cache, sinon la
 * charge utile portée par le document HTML. L'horodatage suit la réponse réellement trouvée,
 * pour que le bandeau puisse afficher une vraie date plutôt qu'une âge inconnue.
 */
const cachedPagePayload = async (
    cache: Cache,
    shape: RequestShape,
): Promise<{ page: InertiaPage; cachedAt: number | null } | null> => {
    const full = await cache.match(cacheKeyFor({ ...shape, partialData: null }));

    if (full !== undefined) {
        return { page: (await full.json()) as InertiaPage, cachedAt: cachedAtOf(full) };
    }

    const document = await cache.match(new URL(shape.url).toString());

    if (document === undefined) {
        return null;
    }

    const page = pagePayloadFromDocument(await document.text());

    return page === null ? null : { page, cachedAt: cachedAtOf(document) };
};

export const rescuedResponse = async (
    cache: Cache,
    shape: RequestShape,
    broadcast: Broadcast,
): Promise<Response> => {
    const cached = await cachedPagePayload(cache, shape);

    if (cached === null) {
        return Response.error();
    }

    await notify(cache, broadcast, { type: 'SERVED_STALE', cachedAt: cached.cachedAt });

    return new Response(JSON.stringify(rescuedPartialPayload(cached.page, partialKeysOf(shape.partialData))), {
        headers: {
            'Content-Type': 'application/json',
            'X-Inertia': 'true',
            Vary: 'X-Inertia',
        },
    });
};

/** Ce qu'une revalidation réseau rapporte : la réponse obtenue (`ok` ou pas), ou `null` si le réseau a échoué. */
type RevalidationOutcome = { response: Response | null };

const revalidate = async (request: Request, cache: Cache, key: string): Promise<RevalidationOutcome> => {
    try {
        const response = await fetch(request);

        if (response.ok) {
            await putQuietly(cache, key, stamped(response.clone()));
        }

        return { response };
    } catch {
        return { response: null };
    }
};

export const staleWhileRevalidate = async (
    event: FetchEvent,
    shape: RequestShape,
    cacheName: string,
    broadcast: Broadcast,
): Promise<Response> => {
    const cache = await caches.open(cacheName);
    const key = cacheKeyFor(shape);
    const hit = await cache.match(key);
    const network = revalidate(event.request, cache, key);

    if (hit !== undefined) {
        /**
         * `waitUntil` garde le worker vivant jusqu'à la revalidation : sans lui, le navigateur
         * peut le terminer dès que la réponse en cache est renvoyée, perdant le `fetch` en vol,
         * l'écriture en cache et la diffusion `FRESH`/`SERVED_STALE`.
         */
        event.waitUntil(
            network.then(async ({ response }): Promise<void> => {
                await notify(
                    cache,
                    broadcast,
                    response !== null && response.ok
                        ? { type: 'FRESH' }
                        : { type: 'SERVED_STALE', cachedAt: cachedAtOf(hit) },
                );
            }),
        );

        return hit;
    }

    const { response } = await network;

    if (response !== null) {
        if (response.ok) {
            await notify(cache, broadcast, { type: 'FRESH' });
        }

        return response;
    }

    return shape.inertia && shape.partialData !== null
        ? rescuedResponse(cache, shape, broadcast)
        : Response.error();
};
