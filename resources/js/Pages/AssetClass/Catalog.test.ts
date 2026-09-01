import { createPinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type VNode } from 'vue';
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
});
