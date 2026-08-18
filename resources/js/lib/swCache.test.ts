import { describe, expect, it } from 'vitest';
import {
    cacheKeyFor,
    classifyRequest,
    pagePayloadFromDocument,
    partialKeysOf,
    rescuedPartialPayload,
    type InertiaPage,
    type RequestShape,
} from '@/lib/swCache';

const shape = (overrides: Partial<RequestShape> = {}): RequestShape => ({
    method: 'GET',
    url: 'https://argent.test/',
    mode: 'navigate',
    inertia: false,
    partialData: null,
    ...overrides,
});

describe('classifyRequest', () => {
    it('laisse passer tout ce qui n\'est pas un GET', () => {
        expect(classifyRequest(shape({ method: 'POST' }))).toBe('passthrough');
    });

    it('reconnaît un asset de build à son préfixe', () => {
        expect(classifyRequest(shape({ url: 'https://argent.test/build/assets/app-BR1IIldu.css' })))
            .toBe('asset');
    });

    it('reconnaît une requête Inertia avant la navigation', () => {
        expect(classifyRequest(shape({ inertia: true, mode: 'navigate' }))).toBe('inertia');
    });

    it('reconnaît une navigation de document', () => {
        expect(classifyRequest(shape({ mode: 'navigate' }))).toBe('navigation');
    });

    it('range le reste à part', () => {
        expect(classifyRequest(shape({ url: 'https://argent.test/icons/icon-192x192.png', mode: 'no-cors' })))
            .toBe('other');
    });
});

describe('cacheKeyFor', () => {
    it('distingue les trois formes d\'une même URL', () => {
        const document = cacheKeyFor(shape());
        const full = cacheKeyFor(shape({ inertia: true }));
        const partial = cacheKeyFor(shape({ inertia: true, partialData: 'catalog,trends' }));

        expect(new Set([document, full, partial]).size).toBe(3);
    });

    it('laisse l\'URL nue pour un document', () => {
        expect(cacheKeyFor(shape())).toBe('https://argent.test/');
    });

    it('trie les noms de props pour qu\'un ordre différent donne la même clé', () => {
        expect(cacheKeyFor(shape({ inertia: true, partialData: 'trends,catalog' })))
            .toBe(cacheKeyFor(shape({ inertia: true, partialData: 'catalog, trends' })));
    });

    it('conserve la chaîne de requête de la page', () => {
        const key = cacheKeyFor(shape({ url: 'https://argent.test/?range=ytd', inertia: true }));

        expect(key).toContain('range=ytd');
    });
});

describe('partialKeysOf', () => {
    it('découpe, nettoie et trie les noms de props', () => {
        expect(partialKeysOf(' trends , catalog ,')).toEqual(['catalog', 'trends']);
    });

    it('renvoie une liste vide quand l\'en-tête est absent', () => {
        expect(partialKeysOf(null)).toEqual([]);
    });
});

describe('rescuedPartialPayload', () => {
    const page: InertiaPage = {
        component: 'Dashboard',
        props: { overview: { total: 10 } },
        url: '/',
        version: 'abc',
    };

    it('marque comme rescapée chaque prop demandée qui manque', () => {
        const payload = rescuedPartialPayload(page, ['catalog', 'trends']);

        expect(payload.rescuedProps).toEqual(['catalog', 'trends']);
    });

    it('ne renvoie que les props demandées qui sont disponibles', () => {
        const payload = rescuedPartialPayload(
            { ...page, props: { overview: { total: 10 }, trends: [] } },
            ['catalog', 'trends'],
        );

        expect(payload.props).toEqual({ trends: [] });
        expect(payload.rescuedProps).toEqual(['catalog']);
    });

    it('conserve le composant et la version de la page en cache', () => {
        const payload = rescuedPartialPayload(page, ['catalog']);

        expect(payload.component).toBe('Dashboard');
        expect(payload.version).toBe('abc');
    });
});

describe('pagePayloadFromDocument', () => {
    it('extrait et déséchappe le data-page du document', () => {
        const html = '<div id="app" data-page="{&quot;component&quot;:&quot;Dashboard&quot;,'
            + '&quot;props&quot;:{},&quot;url&quot;:&quot;/&quot;,&quot;version&quot;:&quot;abc&quot;}"></div>';

        expect(pagePayloadFromDocument(html)?.component).toBe('Dashboard');
    });

    it('renvoie null quand le document ne porte pas de data-page', () => {
        expect(pagePayloadFromDocument('<html><body></body></html>')).toBeNull();
    });

    it('renvoie null quand le data-page n\'est pas du JSON valide', () => {
        expect(pagePayloadFromDocument('<div data-page="pas du json"></div>')).toBeNull();
    });
});
