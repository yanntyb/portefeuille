import { afterEach, describe, expect, it, vi } from 'vitest';
import type { RequestShape } from '@/lib/swCache';
import {
    cacheFirst,
    networkFirst,
    readStatus,
    rescuedResponse,
    staleWhileRevalidate,
    type Broadcast,
    type SwMessage,
} from '@/pwa/strategies';

/**
 * Cache minimal indexé par clé de chaîne. `match()` renvoie un clone à chaque appel, comme le
 * fait la vraie API Cache, pour qu'une lecture du corps dans un test ne consomme pas celle d'un
 * autre appel. `failPut` simule un `QuotaExceededError` sans dépendre d'un vrai stockage plein.
 */
class FakeCache {
    private readonly store = new Map<string, Response>();

    readonly puts: string[] = [];

    failPut = false;

    async match(key: string): Promise<Response | undefined> {
        const cached = this.store.get(key);

        return cached === undefined ? undefined : cached.clone();
    }

    async put(key: string, response: Response): Promise<void> {
        if (this.failPut) {
            throw new Error('QuotaExceededError');
        }

        this.puts.push(key);
        this.store.set(key, response);
    }
}

const useFakeCache = (cache: FakeCache = new FakeCache()): FakeCache => {
    vi.stubGlobal('caches', { open: async (): Promise<FakeCache> => cache });

    return cache;
};

const useFakeFetch = (impl: (request: Request) => Promise<Response>): void => {
    vi.stubGlobal('fetch', impl);
};

const collectingBroadcast = (): { broadcast: Broadcast; messages: SwMessage[] } => {
    const messages: SwMessage[] = [];
    const broadcast: Broadcast = async (message: SwMessage): Promise<void> => {
        messages.push(message);
    };

    return { broadcast, messages };
};

/** Faux `FetchEvent` : seuls `request` et `waitUntil` sont utilisés par les stratégies. */
const fakeEvent = (request: Request): FetchEvent & { waitUntil: ReturnType<typeof vi.fn> } => {
    const waitUntil = vi.fn();

    return { request, waitUntil } as unknown as FetchEvent & { waitUntil: ReturnType<typeof vi.fn> };
};

const shape = (overrides: Partial<RequestShape> = {}): RequestShape => ({
    method: 'GET',
    url: 'https://argent.test/',
    mode: 'navigate',
    inertia: false,
    partialData: null,
    ...overrides,
});

afterEach((): void => {
    vi.unstubAllGlobals();
});

describe('cacheFirst', () => {
    it('clone la réponse avant de la mettre en cache : l\'appelant reçoit un corps non consommé', async () => {
        const cache = useFakeCache();
        useFakeFetch(async (): Promise<Response> => new Response('contenu'));

        const response = await cacheFirst(new Request('https://argent.test/build/app.js'), 'argent-v1');

        await expect(response.text()).resolves.toBe('contenu');
        expect(cache.puts).toEqual(['https://argent.test/build/app.js']);
    });

    it('sert quand même la réponse réseau si l\'écriture en cache échoue', async () => {
        const cache = useFakeCache();
        cache.failPut = true;
        useFakeFetch(async (): Promise<Response> => new Response('contenu'));

        const response = await cacheFirst(new Request('https://argent.test/build/app.js'), 'argent-v1');

        expect(response.status).toBe(200);
        await expect(response.text()).resolves.toBe('contenu');
    });

    it('court-circuite sur une correspondance en cache : le réseau n\'est jamais sollicité', async () => {
        const cache = useFakeCache();
        await cache.put('https://argent.test/build/app.js', new Response('déjà en cache'));
        const fetchSpy = vi.fn();
        useFakeFetch(fetchSpy);

        const response = await cacheFirst(new Request('https://argent.test/build/app.js'), 'argent-v1');

        await expect(response.text()).resolves.toBe('déjà en cache');
        expect(fetchSpy).not.toHaveBeenCalled();
    });
});

describe('networkFirst', () => {
    it('diffuse FRESH et sert quand même la réponse fraîche si l\'écriture en cache échoue', async () => {
        const cache = useFakeCache();
        cache.failPut = true;
        useFakeFetch(async (): Promise<Response> => new Response('page'));
        const { broadcast, messages } = collectingBroadcast();

        const response = await networkFirst(
            new Request('https://argent.test/'),
            'argent-v1',
            '/hors-ligne',
            broadcast,
        );

        await expect(response.text()).resolves.toBe('page');
        expect(messages).toEqual([{ type: 'FRESH' }]);
    });

    it('sert la page hors-ligne quand le réseau échoue et qu\'aucune page n\'est en cache', async () => {
        const cache = useFakeCache();
        await cache.put('/hors-ligne', new Response('hors-ligne'));
        useFakeFetch(async (): Promise<Response> => {
            throw new TypeError('network error');
        });
        const { broadcast } = collectingBroadcast();

        const response = await networkFirst(
            new Request('https://argent.test/'),
            'argent-v1',
            '/hors-ligne',
            broadcast,
        );

        await expect(response.text()).resolves.toBe('hors-ligne');
    });
});

describe('staleWhileRevalidate', () => {
    it('renvoie la réponse en cache immédiatement puis diffuse FRESH via waitUntil', async () => {
        const cache = useFakeCache();
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(JSON.stringify({ component: 'Dashboard', props: {}, url: '/', version: 'v1' })),
        );
        useFakeFetch(async (): Promise<Response> => new Response('fraîche', { status: 200 }));
        const { broadcast, messages } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        const response = await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', broadcast);

        expect(await response.text()).toContain('Dashboard');
        expect(event.waitUntil).toHaveBeenCalledTimes(1);

        /** La diffusion se fait dans la promesse passée à `waitUntil`, pas avant. */
        await event.waitUntil.mock.calls[0][0];
        expect(messages).toEqual([{ type: 'FRESH' }]);
    });

    it('diffuse SERVED_STALE quand le fetch de revalidation est rejeté', async () => {
        const cache = useFakeCache();
        const cachedAt = 1_700_000_000_000;
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(JSON.stringify({ component: 'Dashboard', props: {}, url: '/', version: 'v1' }), {
                headers: { 'X-Sw-Cached-At': String(cachedAt) },
            }),
        );
        useFakeFetch(async (): Promise<Response> => {
            throw new TypeError('network error');
        });
        const { broadcast, messages } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', broadcast);
        await event.waitUntil.mock.calls[0][0];

        expect(messages).toEqual([{ type: 'SERVED_STALE', cachedAt }]);
    });

    it('diffuse SERVED_STALE quand la revalidation renvoie une réponse non-ok', async () => {
        const cache = useFakeCache();
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(JSON.stringify({ component: 'Dashboard', props: {}, url: '/', version: 'v1' })),
        );
        useFakeFetch(async (): Promise<Response> => new Response('erreur serveur', { status: 500 }));
        const { broadcast, messages } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', broadcast);
        await event.waitUntil.mock.calls[0][0];

        expect(messages).toEqual([{ type: 'SERVED_STALE', cachedAt: null }]);
    });

    it('appelle waitUntil avec la promesse de revalidation avant de renvoyer la réponse en cache', async () => {
        const cache = useFakeCache();
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(JSON.stringify({ component: 'Dashboard', props: {}, url: '/', version: 'v1' })),
        );
        let resolveNetwork: (() => void) | undefined;
        useFakeFetch(
            (): Promise<Response> =>
                new Promise((resolve) => {
                    resolveNetwork = (): void => resolve(new Response('fraîche'));
                }),
        );
        const { broadcast } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', broadcast);

        /** `waitUntil` doit déjà porter la promesse avant même que le réseau n'ait répondu. */
        expect(event.waitUntil).toHaveBeenCalledTimes(1);
        expect(typeof event.waitUntil.mock.calls[0][0].then).toBe('function');
        resolveNetwork?.();
    });

    it('ne diffuse pas SERVED_STALE à tort quand la revalidation réussit mais que l\'écriture en cache échoue', async () => {
        const cache = useFakeCache();
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(JSON.stringify({ component: 'Dashboard', props: {}, url: '/', version: 'v1' })),
        );
        cache.failPut = true;
        useFakeFetch(async (): Promise<Response> => new Response('fraîche', { status: 200 }));
        const { broadcast, messages } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', broadcast);
        await event.waitUntil.mock.calls[0][0];

        expect(messages).toEqual([{ type: 'FRESH' }]);
    });

    it('synthétise une réponse Inertia de secours depuis le JSON complet en cache quand le réseau échoue sans rien en cache pour le partiel', async () => {
        const cache = useFakeCache();
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(
                JSON.stringify({ component: 'Dashboard', props: { catalog: ['A'] }, url: '/', version: 'v1' }),
            ),
        );
        useFakeFetch(async (): Promise<Response> => {
            throw new TypeError('network error');
        });
        const { broadcast } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        const response = await staleWhileRevalidate(
            event,
            shape({ inertia: true, partialData: 'catalog' }),
            'argent-v1',
            broadcast,
        );
        const page = await response.json();

        expect(page.props).toEqual({ catalog: ['A'] });
    });

    it('diffuse FRESH quand rien n\'est en cache et que le fetch réussit', async () => {
        useFakeCache();
        useFakeFetch(async (): Promise<Response> => new Response('fraîche', { status: 200 }));
        const { broadcast, messages } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', broadcast);

        expect(messages).toEqual([{ type: 'FRESH' }]);
    });

    it('renvoie une réponse non-ok à l\'appelant sans la diffuser comme fraîche, quand rien n\'est en cache', async () => {
        useFakeCache();
        useFakeFetch(async (): Promise<Response> => new Response('erreur serveur', { status: 500 }));
        const { broadcast, messages } = collectingBroadcast();
        const event = fakeEvent(new Request('https://argent.test/'));

        const response = await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', broadcast);

        expect(response.status).toBe(500);
        await expect(response.text()).resolves.toBe('erreur serveur');
        expect(messages).toEqual([]);
    });
});

describe('rescuedResponse', () => {
    it('synthétise la réponse depuis le document en cache quand aucune réponse Inertia complète n\'y est', async () => {
        const cache = new FakeCache();
        await cache.put(
            'https://argent.test/',
            new Response(
                '<script data-page="app" type="application/json">'
                    + '{"component":"Dashboard","props":{"catalog":[]},"url":"/","version":"v1"}'
                    + '</script><div id="app"></div>',
            ),
        );
        const { broadcast } = collectingBroadcast();

        const response = await rescuedResponse(
            cache as unknown as Cache,
            shape({ inertia: true, partialData: 'catalog' }),
            broadcast,
        );
        const page = await response.json();

        expect(page.component).toBe('Dashboard');
        expect(page.props).toEqual({ catalog: [] });
    });

    it('renvoie Response.error() quand ni la réponse complète ni le document ne sont en cache', async () => {
        const cache = new FakeCache();
        const { broadcast } = collectingBroadcast();

        const response = await rescuedResponse(
            cache as unknown as Cache,
            shape({ inertia: true, partialData: 'catalog' }),
            broadcast,
        );

        expect(response.type).toBe('error');
    });

    it('diffuse SERVED_STALE avec le véritable horodatage porté par la réponse complète en cache', async () => {
        const cache = new FakeCache();
        const cachedAt = 1_700_000_000_000;
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(JSON.stringify({ component: 'Dashboard', props: {}, url: '/', version: 'v1' }), {
                headers: { 'X-Sw-Cached-At': String(cachedAt) },
            }),
        );
        const { broadcast, messages } = collectingBroadcast();

        await rescuedResponse(cache as unknown as Cache, shape({ inertia: true, partialData: 'catalog' }), broadcast);

        expect(messages).toEqual([{ type: 'SERVED_STALE', cachedAt }]);
    });
});

describe('marqueur d\'état persistant', () => {
    it('renvoie null tant que rien n\'a encore été écrit', async () => {
        useFakeCache();

        await expect(readStatus('argent-v1')).resolves.toBeNull();
    });

    it('persiste un marqueur périmé quand networkFirst sert une réponse en cache hors-ligne', async () => {
        const cache = useFakeCache();
        await cache.put(
            'https://argent.test/',
            new Response('page', { headers: { 'X-Sw-Cached-At': '1700000000000' } }),
        );
        useFakeFetch(async (): Promise<Response> => {
            throw new TypeError('network error');
        });
        const { broadcast } = collectingBroadcast();

        await networkFirst(new Request('https://argent.test/'), 'argent-v1', '/hors-ligne', broadcast);

        await expect(readStatus('argent-v1')).resolves.toEqual({ type: 'SERVED_STALE', cachedAt: 1_700_000_000_000 });
    });

    it('remplace un marqueur périmé par un marqueur frais dès que le réseau répond de nouveau', async () => {
        const cache = useFakeCache();
        await cache.put('https://argent.test/', new Response('page'));
        useFakeFetch(async (): Promise<Response> => {
            throw new TypeError('network error');
        });
        const { broadcast } = collectingBroadcast();
        await networkFirst(new Request('https://argent.test/'), 'argent-v1', '/hors-ligne', broadcast);

        await expect(readStatus('argent-v1')).resolves.toMatchObject({ type: 'SERVED_STALE' });

        useFakeFetch(async (): Promise<Response> => new Response('page fraîche', { status: 200 }));
        await networkFirst(new Request('https://argent.test/'), 'argent-v1', '/hors-ligne', broadcast);

        await expect(readStatus('argent-v1')).resolves.toEqual({ type: 'FRESH' });
    });

    it('persiste aussi le marqueur périmé quand staleWhileRevalidate le diffuse via waitUntil', async () => {
        const cache = useFakeCache();
        await cache.put(
            'https://argent.test/?__sw=inertia',
            new Response(JSON.stringify({ component: 'Dashboard', props: {}, url: '/', version: 'v1' })),
        );
        useFakeFetch(async (): Promise<Response> => {
            throw new TypeError('network error');
        });
        const event = fakeEvent(new Request('https://argent.test/'));

        await staleWhileRevalidate(event, shape({ inertia: true }), 'argent-v1', collectingBroadcast().broadcast);
        await event.waitUntil.mock.calls[0][0];

        await expect(readStatus('argent-v1')).resolves.toMatchObject({ type: 'SERVED_STALE' });
    });
});
