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

const WORKER_ORIGIN = 'https://argent.test';

describe('classifyRequest', () => {
    it('laisse passer tout ce qui n\'est pas un GET', () => {
        expect(classifyRequest(shape({ method: 'POST' }), WORKER_ORIGIN)).toBe('passthrough');
    });

    it('laisse passer une requête dont l\'origine diffère de celle du worker', () => {
        expect(classifyRequest(shape({ url: 'https://cdn.tiers.test/police.woff2' }), WORKER_ORIGIN))
            .toBe('passthrough');
    });

    it('reconnaît un asset de build à son préfixe', () => {
        expect(classifyRequest(shape({ url: 'https://argent.test/build/assets/app-BR1IIldu.css' }), WORKER_ORIGIN))
            .toBe('asset');
    });

    it('reconnaît une requête Inertia avant la navigation', () => {
        expect(classifyRequest(shape({ inertia: true, mode: 'navigate' }), WORKER_ORIGIN)).toBe('inertia');
    });

    it('reconnaît une navigation de document', () => {
        expect(classifyRequest(shape({ mode: 'navigate' }), WORKER_ORIGIN)).toBe('navigation');
    });

    it('range le reste à part', () => {
        expect(classifyRequest(shape({ url: 'https://argent.test/icons/icon-192x192.png', mode: 'no-cors' }), WORKER_ORIGIN))
            .toBe('other');
    });

    it('laisse passer l\'instantané hors-ligne, que le store gère lui-même', () => {
        const shape = {
            method: 'GET',
            url: 'https://argent.test/instantane',
            mode: 'cors',
            inertia: false,
            partialData: null,
        };

        expect(classifyRequest(shape, 'https://argent.test')).toBe('passthrough');
    });

    it('laisse passer les listes du formulaire de saisie, jamais servies périmées', () => {
        const shape = {
            method: 'GET',
            url: 'https://argent.test/transactions/options',
            mode: 'cors',
            inertia: false,
            partialData: null,
        };

        /**
         * Sans ce cas, `staleWhileRevalidate` les servirait depuis le cache et l'on saisirait
         * contre un catalogue périmé.
         */
        expect(classifyRequest(shape, 'https://argent.test')).toBe('passthrough');
    });

    it('laisse passer la recherche d\'instruments sans la mettre en cache', () => {
        const shape = {
            method: 'GET',
            url: 'https://argent.test/instruments/recherche?q=apple',
            mode: 'cors',
            inertia: false,
            partialData: null,
        };

        expect(classifyRequest(shape, 'https://argent.test')).toBe('passthrough');
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

    it('fusionne les props rescapées existantes avec les nouvelles', () => {
        const pageWithRescued: InertiaPage = {
            ...page,
            rescuedProps: ['performances'],
        };
        const payload = rescuedPartialPayload(pageWithRescued, ['catalog']);

        expect(payload.rescuedProps).toEqual(['catalog', 'performances']);
    });

    /**
     * `deferredProps` n'appartient qu'à la toute première page : une vraie réponse partielle ne
     * le porte jamais (vérifié sur une vraie réponse d'Inertia). Le laisser passer ferait croire
     * au client que tous les groupes restent différés, qui les redemande en boucle — c'est
     * exactement le bug que le parcours hors-ligne réel a révélé.
     */
    it('retire le deferredProps hérité de la page complète : une vraie réponse partielle ne le porte jamais', () => {
        const pageWithDeferred: InertiaPage = {
            ...page,
            deferredProps: { tendances: ['trends'], performances: ['performances'] },
        };

        const payload = rescuedPartialPayload(pageWithDeferred, ['trends']);

        expect(payload.deferredProps).toBeUndefined();
    });
});

describe('pagePayloadFromDocument', () => {
    /**
     * Balisage réel de `inertiajs/inertia-laravel` v3.3.1 (`Directive::compile()`), vérifié par
     * `curl -sk https://argent.test/` : le JSON est le contenu texte du `<script data-page>`, pas
     * la valeur d'un attribut — celui-ci ne porte que l'identifiant du nœud racine (`"app"`).
     */
    it('extrait le JSON du contenu texte de la balise <script data-page>', () => {
        const html = '<body>'
            + '<script data-page="app" type="application/json">'
            + '{"component":"Dashboard","props":{},"url":"/","version":"abc"}'
            + '</script><div id="app"></div>'
            + '<div id="pwa-banner"></div></body>';

        expect(pagePayloadFromDocument(html)?.component).toBe('Dashboard');
    });

    it('renvoie null quand le document ne porte pas de balise <script data-page>', () => {
        expect(pagePayloadFromDocument('<html><body></body></html>')).toBeNull();
    });

    it('renvoie null quand le contenu de la balise n\'est pas du JSON valide', () => {
        expect(pagePayloadFromDocument('<script data-page="app" type="application/json">pas du json</script>'))
            .toBeNull();
    });
});
