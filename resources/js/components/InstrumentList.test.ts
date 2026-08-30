import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';
import type { InstrumentRow } from '@/lib/instrumentList';

/**
 * `Link` d'Inertia demande un routeur monté ; ici seul le balisage compte, donc un ancrage nu qui
 * reporte `href` et les classes suffit à décrire ce que la ligne rend.
 */
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
}));

const { default: InstrumentList } = await import('@/components/InstrumentList.vue');

const row: InstrumentRow = {
    id: 7,
    name: 'Apple',
    ticker: 'AAPL',
    isin: null,
    type: 'stock',
    typeLabel: 'Action',
    lastPrice: 200,
    held: true,
    quantity: 3,
    marketValue: 600,
    changePct: 2,
    points: [1, 2, 3],
    gain: 40,
    gainPct: 7.1,
    share: 12,
    barWidth: '50%',
};

/** Monte la liste sur un hôte neuf et rend son DOM initial. */
function mountList(): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(InstrumentList, { rows: [row], loading: false, emptyLabel: 'Aucune position.' }).mount(host);

    return host;
}

describe('ligne d\'un instrument', () => {
    it('mène à la fiche depuis la ligne entière, pas seulement depuis le nom', () => {
        const link = mountList().querySelector<HTMLAnchorElement>('[data-instrument-row] a');

        expect(link?.getAttribute('href')).toBe('/asset/7');
        expect(link?.querySelector('[data-instrument-name]')).not.toBeNull();
        expect(link?.querySelector('[data-instrument-value]')).not.toBeNull();
        expect(link?.querySelector('[data-instrument-bar]')).not.toBeNull();
        expect(link?.querySelector('[data-instrument-gain]')).not.toBeNull();
    });

    it('accuse le clic par la teinte du dashboard plutôt que par un soulignement', () => {
        const host = mountList();
        const link = host.querySelector<HTMLAnchorElement>('[data-instrument-row] a');

        expect(link?.className).toContain('hover:bg-muted');
        expect(host.querySelector('[data-instrument-name]')?.className).not.toContain('hover:underline');
    });
});
