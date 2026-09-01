import { createPinia } from 'pinia';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type VNode } from 'vue';
import type { CreatedInstrument } from '@/components/instruments/InstrumentSearchPanel.vue';
import type { CatalogLine } from '@/lib/catalog';

const reload = vi.fn();
const visit = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { setup: () => () => null },
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
    router: { reload: (...args: unknown[]) => reload(...args), visit: (...args: unknown[]) => visit(...args) },
}));

/**
 * La charge que le prochain clic sur le stub émettra : chaque test la pose avant de cliquer,
 * plutôt que de rejouer toute la recherche Yahoo (déjà couverte par `InstrumentSearchPanel.test.ts`).
 */
const panelPayload: { current: CreatedInstrument | null } = { current: null };

/**
 * Stub du panneau, à la manière dont la Task 7 le montera dans le formulaire de transaction : un
 * bouton qui émet `created` avec la charge choisie par le test, et qui expose l'exposition reçue
 * pour vérifier le pré-remplissage sans reconstituer les deux étapes du vrai panneau.
 */
vi.mock('@/components/instruments/InstrumentSearchPanel.vue', () => ({
    default: {
        name: 'InstrumentSearchPanelStub',
        props: { initialTerm: { type: String, default: '' }, exposure: { type: String, default: null } },
        emits: ['created', 'cancel', 'open'],
        setup(props: { exposure: string | null }, { emit }: { emit: (event: string, payload?: unknown) => void }) {
            return () =>
                h('button', {
                    type: 'button',
                    'data-panel-stub': '',
                    'data-panel-exposure': props.exposure ?? '',
                    onClick: () => emit('created', panelPayload.current),
                });
        },
    },
}));

const { default: Catalog } = await import('@/Pages/AssetClass/Catalog.vue');

const line = (overrides: Partial<CatalogLine> = {}): CatalogLine => ({
    id: 7,
    name: 'Apple',
    ticker: 'AAPL',
    isin: 'US0378331005',
    type: 'stock',
    typeLabel: 'Action',
    lastPrice: 200,
    held: false,
    quantity: null,
    marketValue: null,
    ...overrides,
});

function mountCatalog(): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    /** `AppBottomBar` embarque `ThemeToggle`, qui lit un store Pinia : sans lui, le montage échoue. */
    createApp(Catalog, {
        assetClass: { key: 'equity', label: 'Actions', slug: 'actions' },
        catalog: [line()],
    })
        .use(createPinia())
        .mount(host);

    return host;
}

const filter = async (host: HTMLElement, term: string): Promise<void> => {
    const input = host.querySelector<HTMLInputElement>('[data-catalog-search]')!;
    input.value = term;
    input.dispatchEvent(new Event('input'));
    await nextTick();
};

/** Ouvre le volet d'ajout depuis le lien Yahoo de l'état vide du filtre. */
const openDialog = async (host: HTMLElement, term: string): Promise<void> => {
    await filter(host, term);
    host.querySelector<HTMLButtonElement>('[data-catalog-yahoo]')!.click();
    await nextTick();
};

afterEach(() => {
    reload.mockClear();
    visit.mockClear();
    panelPayload.current = null;
    document.body.innerHTML = '';
});

describe('catalogue d\'une exposition', () => {
    it('propose de chercher chez Yahoo quand le filtre ne trouve rien', async () => {
        const host = mountCatalog();

        expect(host.querySelector('[data-catalog-yahoo]')).toBeNull();

        await filter(host, 'nvidia');

        expect(host.querySelector('[data-catalog-yahoo]')?.textContent).toContain('nvidia');
    });

    it('ne propose rien tant que le filtre est vide', async () => {
        const host = mountCatalog();

        await filter(host, '');

        expect(host.querySelector('[data-catalog-yahoo]')).toBeNull();
    });

    it('pré-remplit le panneau de l\'exposition de la page', async () => {
        const host = mountCatalog();

        await openDialog(host, 'nvidia');

        expect(document.querySelector('[data-panel-stub]')?.getAttribute('data-panel-exposure')).toBe('equity');
    });

    it('recharge le catalogue en place et vide le filtre quand l\'instrument reste dans l\'exposition de la page', async () => {
        const host = mountCatalog();

        await openDialog(host, 'nvidia');

        /** Même exposition (`equity`) que la page : la nouvelle ligne doit apparaître ici, sans quitter la page. */
        panelPayload.current = { id: 31, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' };
        document.querySelector<HTMLButtonElement>('[data-panel-stub]')!.click();
        await nextTick();

        expect(reload).toHaveBeenCalledWith({ only: ['catalog'] });
        expect(visit).not.toHaveBeenCalled();
        expect(host.querySelector<HTMLInputElement>('[data-catalog-search]')?.value).toBe('');
    });

    it('quitte vers le catalogue de l\'autre exposition, sans recharger celui-ci, quand l\'instrument en change', async () => {
        const host = mountCatalog();

        await openDialog(host, 'nvidia');

        /**
         * Exposition différente (`crypto`) de celle de la page (`equity`) : rechargée ici, la ligne
         * n'apparaîtrait jamais et la création semblerait ratée — on va donc à son propre catalogue.
         */
        panelPayload.current = { id: 32, name: 'Bitcoin', ticker: 'BTC', assetClass: 'crypto', assetClassSlug: 'crypto' };
        document.querySelector<HTMLButtonElement>('[data-panel-stub]')!.click();
        await nextTick();

        expect(visit).toHaveBeenCalledWith('/crypto/catalogue');
        expect(reload).not.toHaveBeenCalled();
    });
});
