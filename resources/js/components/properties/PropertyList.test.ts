import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';
import type { PropertyOverview } from '@/lib/realEstate';

/** Même ancrage nu que pour la liste des positions : seul le balisage de la ligne est en jeu. */
vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
}));

const { default: PropertyList } = await import('@/components/properties/PropertyList.vue');

const property: PropertyOverview = {
    id: 3,
    name: 'T2 Lyon 7e',
    currentValue: 200000,
    remainingPrincipal: 50000,
    netWorth: 150000,
    monthlyCashFlow: 517,
    invested: 108000,
};

/** Monte la liste sur un hôte neuf et rend son DOM initial. */
function mountList(): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(PropertyList, { properties: [property] }).mount(host);

    return host;
}

describe('ligne d\'un bien', () => {
    it('mène à la fiche depuis la ligne entière, pas seulement depuis le nom', () => {
        const link = mountList().querySelector<HTMLAnchorElement>('[data-property-row] a');

        expect(link?.getAttribute('href')).toBe('/properties/3');
        expect(link?.querySelector('[data-property-name]')).not.toBeNull();
        expect(link?.querySelector('[data-property-net]')).not.toBeNull();
        expect(link?.querySelector('[data-property-bar]')).not.toBeNull();
        expect(link?.querySelector('[data-property-cash-flow]')).not.toBeNull();
    });

    it('accuse le clic par la teinte du dashboard plutôt que par un soulignement', () => {
        const host = mountList();
        const link = host.querySelector<HTMLAnchorElement>('[data-property-row] a');

        expect(link?.className).toContain('hover:bg-muted');
        expect(host.querySelector('[data-property-name]')?.className).not.toContain('hover:underline');
    });
});
