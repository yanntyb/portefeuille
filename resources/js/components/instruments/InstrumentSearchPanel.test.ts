import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

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

    it('émet l\'instrument créé', async () => {
        const fetchMock = vi.fn(async (url: unknown, init?: RequestInit) =>
            init?.method === 'POST'
                ? jsonResponse({
                      id: 31,
                      name: 'NVIDIA Corp.',
                      ticker: 'NVDA',
                      assetClass: 'equity',
                      assetClassSlug: 'actions',
                  }, 201)
                : jsonResponse([yahooHit()]),
        );
        vi.stubGlobal('fetch', fetchMock);

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLFormElement>('[data-instrument-confirm]')!.dispatchEvent(new Event('submit'));
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(events.created).toEqual([
            { id: 31, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' },
        ]);
    });

    it('affiche les erreurs de validation du serveur', async () => {
        const fetchMock = vi.fn(async (url: unknown, init?: RequestInit) =>
            init?.method === 'POST'
                ? jsonResponse({ message: 'invalide', errors: { ticker: ['Le ticker est obligatoire.'] } }, 422)
                : jsonResponse([yahooHit()]),
        );
        vi.stubGlobal('fetch', fetchMock);

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLFormElement>('[data-instrument-confirm]')!.dispatchEvent(new Event('submit'));
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(host.querySelector('[data-instrument-error]')?.textContent).toContain('Le ticker est obligatoire.');
        expect(events.created).toEqual([]);
    });
});
