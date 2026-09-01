import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';
import type { CatalogLine } from '@/lib/catalog';

/** Même feinte que pour `InstrumentList` : seul le balisage compte, pas le routeur d'Inertia. */
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
}));

const { default: CatalogList } = await import('@/components/instruments/CatalogList.vue');

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

function mountList(lines: CatalogLine[]): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(CatalogList, { lines, emptyLabel: 'Aucun instrument.' }).mount(host);

    return host;
}

describe('catalogue d\'une exposition', () => {
    it('mène à la fiche depuis la ligne entière', () => {
        const link = mountList([line()]).querySelector<HTMLAnchorElement>('[data-catalog-row] a');

        expect(link?.getAttribute('href')).toBe('/asset/7');
        expect(link?.querySelector('[data-catalog-name]')?.textContent).toContain('Apple');
        expect(link?.querySelector('[data-catalog-type]')?.textContent?.trim()).toBe('Action');
    });

    it('ne marque « Détenu » que les lignes du portefeuille', () => {
        const host = mountList([line(), line({ id: 8, name: 'Amazon', held: true, quantity: 3, marketValue: 600 })]);

        const rows = host.querySelectorAll('[data-catalog-row]');
        expect(rows).toHaveLength(2);
        expect(rows[0].querySelector('[data-catalog-held]')).toBeNull();
        expect(rows[1].querySelector('[data-catalog-held]')?.textContent?.trim()).toBe('Détenu');
    });

    it('pose un tiret plutôt qu\'un zéro pour un instrument sans cours', () => {
        const host = mountList([line({ lastPrice: null })]);

        expect(host.querySelector('[data-catalog-price]')?.textContent?.trim()).toBe('—');
    });

    it('affiche l\'état vide quand rien ne reste', () => {
        const host = mountList([]);

        expect(host.querySelector('[data-catalog-empty]')?.textContent?.trim()).toBe('Aucun instrument.');
    });
});
