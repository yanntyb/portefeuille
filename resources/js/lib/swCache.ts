/**
 * Décisions de cache du service worker, isolées de l'API `Request` / `Cache` pour rester
 * testables sous happy-dom. Le worker n'y apporte que la traduction depuis les vrais objets.
 */

/** Répertoire de build de `laravel-vite-plugin` : ses URLs sont hashées, donc immuables. */
const BUILD_PREFIX = '/build/';

/**
 * L'instantané hors-ligne n'est pas une ressource de page : le store le demande en tâche de fond
 * et absorbe lui-même ses échecs. Le laisser tomber dans `staleWhileRevalidate` ferait diffuser
 * `FRESH` / `SERVED_STALE` depuis une requête qui ne décrit pas la page affichée — le bandeau du
 * lecteur basculerait sur l'état d'une synchronisation de fond.
 */
const SNAPSHOT_PATH = '/instantane';

/**
 * Les listes du formulaire de saisie. Même traitement que l'instantané, pour une autre raison :
 * c'est de la donnée du chemin d'écriture, et une liste d'enveloppes ou d'instruments servie depuis
 * le cache ferait saisir contre un catalogue périmé — un instrument ajouté par une synchronisation
 * n'apparaîtrait pas, une enveloppe supprimée s'y proposerait encore.
 *
 * Hors-ligne, la requête échoue donc franchement, ce qui est le comportement voulu : la saisie y
 * est bloquée.
 */
const TRANSACTION_OPTIONS_PATH = '/transactions/options';

/**
 * La recherche d'un instrument, interrogée à chaque frappe. Même raison que l'instantané et les
 * listes du formulaire : une réponse mise en cache figerait la liste sur le premier résultat tapé.
 */
const INSTRUMENT_SEARCH_PATH = '/instruments/recherche';

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
    /** Groupes encore différés, par nom de groupe → props restantes. Seule la page initiale en porte. */
    deferredProps?: Record<string, string[]>;
    [key: string]: unknown;
};

/**
 * `workerOrigin` (`self.location.origin`) sépare les requêtes du worker de celles qu'il ne fait
 * qu'intercepter en passant. Aucun bug ne vit aujourd'hui de son absence — l'application ne
 * récupère rien en cross-origin — mais un service worker intercepte tout GET pour toujours : le
 * jour où un GET tiers avec CORS apparaît, une réponse `ok` s'écrirait sous son URL complète
 * dans `argent-{version}` et y resterait tant que la version ne change pas. Les réponses
 * `no-cors` y échappent aujourd'hui par accident, parce qu'elles sont opaques (`response.ok` y
 * est toujours `false`), pas par une décision explicite.
 */
export function classifyRequest(request: RequestShape, workerOrigin: string): SwRequestKind {
    if (request.method !== 'GET') {
        return 'passthrough';
    }

    const url = new URL(request.url);

    if (url.origin !== workerOrigin) {
        return 'passthrough';
    }

    if (
        url.pathname === SNAPSHOT_PATH
        || url.pathname === TRANSACTION_OPTIONS_PATH
        || url.pathname === INSTRUMENT_SEARCH_PATH
    ) {
        return 'passthrough';
    }

    if (url.pathname.startsWith(BUILD_PREFIX)) {
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
 *
 * `page` est la page complète mise en cache à la première visite : elle porte encore son
 * `deferredProps` d'origine, qui liste TOUS les groupes comme différés. Une vraie réponse
 * partielle n'a jamais ce champ — seule la page initiale en porte un. Le laisser passer ferait
 * croire à Inertia que rien n'a été traité : `page.set()` replanifie alors `loadDeferredProps`
 * pour ces mêmes groupes dès que la réponse est posée, qui re-déclenche cette même réponse de
 * secours, indéfiniment. On l'omet donc, exactement comme le ferait une vraie réponse partielle.
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

    const { deferredProps: _deferredProps, ...base } = page;

    return {
        ...base,
        props,
        rescuedProps: [...new Set([...(page.rescuedProps ?? []), ...rescued])].sort(),
    };
}

/**
 * Dernier recours quand aucune réponse Inertia complète n'est en cache : le document HTML porte
 * la même charge utile dans le contenu texte de sa balise `<script data-page>`. C'est bien le
 * contenu du `<script>`, pas la valeur de l'attribut `data-page` — celui-ci ne porte que
 * l'identifiant du nœud racine (`"app"` par défaut), voir `Inertia\Directive::compile()`. Le
 * contenu d'un `<script type="application/json">` est du texte brut : aucune entité HTML n'y est
 * jamais échappée, donc rien à déséchapper ici.
 */
export function pagePayloadFromDocument(html: string): InertiaPage | null {
    const match = /<script data-page="[^"]*" type="application\/json">([\s\S]*?)<\/script>/.exec(html);

    if (match === null) {
        return null;
    }

    try {
        return JSON.parse(match[1]) as InertiaPage;
    } catch {
        return null;
    }
}
