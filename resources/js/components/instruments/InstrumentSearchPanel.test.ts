import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick, reactive } from 'vue';

type PostOptions = {
    onSuccess?: (body: unknown) => void;
    onHttpException?: () => void;
    onNetworkError?: () => void;
};

/**
 * Ce que le prochain `post()` doit faire, posé par chaque test avant de soumettre. Par défaut, il
 * ne répond rien — un test qui ne pose rien et pourtant s'attend à une réponse échouerait fort.
 */
let postImpl: (url: string, options?: PostOptions) => Promise<unknown> = async (): Promise<unknown> => undefined;

/** Le dernier formulaire `useHttp` créé par le composant — un seul par montage. */
let latestHttpForm: Record<string, unknown> | null = null;

/**
 * `useHttp` porte le jeton anti-CSRF (lu du cookie `XSRF-TOKEN`, posé en en-tête `X-XSRF-TOKEN` par
 * le client XHR d'Inertia v3) et parle au routeur d'Inertia, absent d'un montage nu — comme
 * `SyncButton.test.ts`. Le double garde la réactivité des champs, puisque c'est lui que `v-model`
 * écrit à l'étape de confirmation, et rend l'appel à `/instruments` observable, sans reproduire ce
 * que fait la vraie bibliothèque.
 */
vi.mock('@inertiajs/vue3', () => ({
    useHttp: (initial: Record<string, unknown>) => {
        const form = reactive({
            ...initial,
            errors: {} as Record<string, string>,
            processing: false,
            clearErrors(): void {
                form.errors = {};
            },
            transform(): unknown {
                return form;
            },
            async post(url: string, options: PostOptions = {}): Promise<unknown> {
                form.processing = true;

                try {
                    return await postImpl(url, options);
                } finally {
                    form.processing = false;
                }
            },
        });

        latestHttpForm = form;

        return form;
    },
}));

const { default: InstrumentSearchPanel } = await import('@/components/instruments/InstrumentSearchPanel.vue');

type Result = {
    symbol: string;
    name: string;
    exchange: string | null;
    type: string | null;
    typeLabel: string | null;
    existingId: number | null;
};

const yahooHit = (overrides: Partial<Result> = {}): Result => ({
    symbol: 'NVDA',
    name: 'NVIDIA Corp.',
    exchange: 'NasdaqGS',
    type: 'stock',
    typeLabel: 'Action',
    existingId: null,
    ...overrides,
});

/** Une réponse JSON minimale, comme `fetch` la rend. */
const jsonResponse = (body: unknown, status = 200): Response =>
    ({ ok: status < 400, status, json: async () => body }) as Response;

function mountPanel(props: Record<string, unknown> = {}): { host: HTMLElement; events: Record<string, unknown[]> } {
    const host = document.createElement('div');
    document.body.append(host);

    const events: Record<string, unknown[]> = { created: [], cancel: [], open: [] };

    createApp(InstrumentSearchPanel, {
        ...props,
        onCreated: (payload: unknown): void => void events.created.push(payload),
        onCancel: (): void => void events.cancel.push(null),
        onOpen: (payload: unknown): void => void events.open.push(payload),
    }).mount(host);

    return { host, events };
}

const type = async (host: HTMLElement, term: string): Promise<void> => {
    const input = host.querySelector<HTMLInputElement>('[data-instrument-search-input]')!;
    input.value = term;
    input.dispatchEvent(new Event('input'));
    await nextTick();
};

beforeEach(() => {
    vi.useFakeTimers();
    postImpl = async (): Promise<unknown> => undefined;
    latestHttpForm = null;
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('panneau de recherche d\'instruments', () => {
    it('ne cherche qu\'une fois la frappe retombée', async () => {
        const fetchMock = vi.fn(async (url: unknown) => jsonResponse([yahooHit()]));
        vi.stubGlobal('fetch', fetchMock);

        const { host } = mountPanel();

        await type(host, 'n');
        await type(host, 'nv');
        await type(host, 'nvd');

        expect(fetchMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(300);
        await nextTick();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0][0])).toContain('/instruments/recherche?q=nvd');
        expect(host.querySelectorAll('[data-instrument-result]')).toHaveLength(1);
    });

    it('cherche le terme pré-rempli dès le montage, sans attendre le débounce', async () => {
        const fetchMock = vi.fn(async (url: unknown) => jsonResponse([yahooHit()]));
        vi.stubGlobal('fetch', fetchMock);

        const { host } = mountPanel({ initialTerm: 'nvda' });

        /**
         * La recherche est déjà partie à la fin du montage : le débounce n'a de sens que pour
         * étaler des frappes, pas pour retarder une valeur déjà connue en arrivant. Sans quoi le
         * lecteur verrait « Aucun instrument ne porte ce nom » avant que la requête ne soit même
         * envoyée.
         */
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0][0])).toContain('/instruments/recherche?q=nvda');
        expect(host.querySelector('[data-instrument-searching]')).not.toBeNull();
        expect(host.querySelector('[data-instrument-none]')).toBeNull();

        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(host.querySelectorAll('[data-instrument-result]')).toHaveLength(1);
        expect(host.querySelector('[data-instrument-none]')).toBeNull();
    });

    it('mène à la fiche d\'un instrument déjà en base, sans le recréer', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit({ existingId: 12 })])));

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();

        const row = host.querySelector<HTMLElement>('[data-instrument-result]')!;
        expect(row.querySelector('[data-instrument-existing]')).not.toBeNull();

        row.querySelector('button')!.click();
        await nextTick();

        expect(events.open).toEqual([{ id: 12 }]);
        /** Pas d'étape 2 : il n'y a rien à confirmer pour un instrument connu. */
        expect(host.querySelector('[data-instrument-confirm]')).toBeNull();
    });

    it('passe à la confirmation, pré-remplie du résultat', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        const { host } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();

        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        expect(host.querySelector<HTMLInputElement>('[data-instrument-name]')?.value).toBe('NVIDIA Corp.');
        expect(host.querySelector<HTMLInputElement>('[data-instrument-ticker]')?.value).toBe('NVDA');
        expect(host.querySelector<HTMLSelectElement>('[data-instrument-type]')?.value).toBe('stock');
    });

    it('pré-remplit l\'exposition de la page quand elle est donnée', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        const { host } = mountPanel({ exposure: 'crypto' });

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        expect(host.querySelector<HTMLSelectElement>('[data-instrument-asset-class]')?.value).toBe('crypto');
    });

    it('revient à l\'étape 1 en gardant le terme cherché', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        const { host } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLElement>('[data-instrument-back]')!.click();
        await nextTick();

        expect(host.querySelector<HTMLInputElement>('[data-instrument-search-input]')?.value).toBe('nvda');
    });

    it('envoie la création par `useHttp`, seul à porter le jeton anti-CSRF', async () => {
        /**
         * La preuve du correctif : `fetch` ne sert ici qu'à la recherche (une lecture, sans jeton à
         * porter). Si la création repassait par un `fetch` nu, cette assertion sur `postSpy`
         * échouerait — c'est tout ce que la suite précédente ne pouvait pas voir, puisqu'elle
         * validait `/instruments` par un test PHP qui tourne sous `runningUnitTests()`, où
         * `PreventRequestForgery` s'efface.
         */
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        const postSpy = vi.fn(async (_url: string, options: PostOptions = {}): Promise<unknown> => {
            options.onSuccess?.({ id: 31, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' });

            return undefined;
        });
        postImpl = postSpy;

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLFormElement>('[data-instrument-confirm]')!.dispatchEvent(new Event('submit'));
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(postSpy).toHaveBeenCalledWith('/instruments', expect.anything());
        expect(events.created).toEqual([
            { id: 31, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' },
        ]);
    });

    it('affiche les erreurs de validation du serveur', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        postImpl = async (_url: string): Promise<unknown> => {
            const form = latestHttpForm as unknown as { errors: Record<string, string> };
            Object.assign(form.errors, { ticker: 'Le ticker est obligatoire.' });

            return undefined;
        };

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLFormElement>('[data-instrument-confirm]')!.dispatchEvent(new Event('submit'));
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        const error = host.querySelector('[data-instrument-error]');
        expect(error?.textContent).toContain('Le ticker est obligatoire.');
        expect(error?.getAttribute('role')).toBe('alert');
        expect(events.created).toEqual([]);
    });

    it('affiche un message générique sur un échec serveur ou réseau', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        postImpl = async (_url: string, options: PostOptions = {}): Promise<unknown> => {
            options.onHttpException?.();

            throw new Error('500');
        };

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLFormElement>('[data-instrument-confirm]')!.dispatchEvent(new Event('submit'));
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(host.querySelector('[data-instrument-error]')?.textContent).toContain("L'instrument n'a pas pu être créé.");
        expect(events.created).toEqual([]);
    });
});
