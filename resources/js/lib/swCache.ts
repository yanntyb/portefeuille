/**
 * Décisions de cache du service worker, isolées de l'API `Request` / `Cache` pour rester
 * testables sous happy-dom. Le worker n'y apporte que la traduction depuis les vrais objets.
 */

/** Répertoire de build de `laravel-vite-plugin` : ses URLs sont hashées, donc immuables. */
const BUILD_PREFIX = '/build/';

export type SwRequestKind = 'passthrough' | 'asset' | 'inertia' | 'navigation' | 'other';

/** Ce que le worker retient d'une requête pour décider quoi en faire. */
export type RequestShape = {
    method: string;
    url: string;
    mode: string;
    inertia: boolean;
    partialData: string | null;
};

/** Charge utile d'une page Inertia, réduite aux champs que le worker manipule. */
export type InertiaPage = {
    component: string;
    props: Record<string, unknown>;
    url: string;
    version: string | null;
    rescuedProps?: string[];
    [key: string]: unknown;
};

export function classifyRequest(request: RequestShape): SwRequestKind {
    if (request.method !== 'GET') {
        return 'passthrough';
    }

    if (new URL(request.url).pathname.startsWith(BUILD_PREFIX)) {
        return 'asset';
    }

    /** Avant la navigation : une visite Inertia porte elle aussi `mode: navigate`. */
    if (request.inertia) {
        return 'inertia';
    }

    return request.mode === 'navigate' ? 'navigation' : 'other';
}

export function partialKeysOf(partialData: string | null): string[] {
    if (partialData === null) {
        return [];
    }

    return partialData
        .split(',')
        .map((key: string): string => key.trim())
        .filter((key: string): boolean => key !== '')
        .sort();
}

/**
 * L'API Cache indexe sur l'URL seule, or `/` produit trois réponses distinctes selon les
 * en-têtes. La clé synthétique les sépare ; elle ne sert jamais au `fetch` réel.
 */
export function cacheKeyFor(request: RequestShape): string {
    const url = new URL(request.url);

    if (!request.inertia) {
        return url.toString();
    }

    const keys = partialKeysOf(request.partialData);

    url.searchParams.set('__sw', keys.length === 0 ? 'inertia' : `partial:${keys.join(',')}`);

    return url.toString();
}

/**
 * Réponse partielle de secours : les props demandées que la page en cache ne porte pas sont
 * listées dans `rescuedProps`, ce qui fait rendre le slot `#rescue` d'Inertia plutôt que de
 * laisser un squelette tourner indéfiniment.
 */
export function rescuedPartialPayload(page: InertiaPage, requestedKeys: string[]): InertiaPage {
    const props: Record<string, unknown> = {};
    const rescued: string[] = [];

    for (const key of requestedKeys) {
        if (key in page.props) {
            props[key] = page.props[key];

            continue;
        }

        rescued.push(key);
    }

    return {
        ...page,
        props,
        rescuedProps: [...new Set([...(page.rescuedProps ?? []), ...rescued])].sort(),
    };
}

const HTML_ENTITIES: Record<string, string> = {
    '&quot;': '"',
    '&#039;': "'",
    '&#39;': "'",
    '&lt;': '<',
    '&gt;': '>',
    '&amp;': '&',
};

/**
 * Dernier recours quand aucune réponse Inertia complète n'est en cache : le document HTML
 * porte la même charge utile dans son attribut `data-page`.
 */
export function pagePayloadFromDocument(html: string): InertiaPage | null {
    const match = /data-page="([^"]*)"/.exec(html);

    if (match === null) {
        return null;
    }

    /**
     * Une seule passe de `replace` avec une alternation combinée : aucune entité produite par un
     * remplacement n'est réexaminée. Des `.replace()` successifs par entité réintroduiraient le
     * bug de double-déséchappement (ex. `&amp;amp;` → `&amp;` → `&`).
     */
    const json = match[1].replace(
        /&quot;|&#0?39;|&lt;|&gt;|&amp;/g,
        (entity: string): string => HTML_ENTITIES[entity] ?? entity,
    );

    try {
        return JSON.parse(json) as InertiaPage;
    } catch {
        return null;
    }
}
