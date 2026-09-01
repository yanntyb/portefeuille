import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';
import type { AssetClass, WealthOverview } from '@/lib/wealth';

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

const { default: WealthClassesSection } = await import('@/components/dashboard/WealthClassesSection.vue');

const classOf = (overrides: Partial<AssetClass>): AssetClass => ({
    key: 'equity',
    label: 'Actions',
    href: '/classes/equity',
    color: 'equity',
    value: 1000,
    invested: 800,
    gain: 200,
    gainPct: 25,
    realizedGain: 0,
    ...overrides,
});

function mountSection(classes: AssetClass[]): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    const overview: WealthOverview = {
        totalValue: classes.reduce((total, line): number => total + line.value, 0),
        totalInvested: 0,
        totalGain: 0,
        totalGainPct: null,
        totalRealizedGain: 0,
        classes,
    };

    createApp(WealthClassesSection, { overview }).mount(host);

    return host;
}

describe('lignes de classes', () => {
    it('mène à la page de la classe qui en a une', () => {
        const host = mountSection([classOf({})]);

        const line = host.querySelector('[data-wealth-class] a');

        expect(line?.getAttribute('href')).toBe('/classes/equity');
        /** Le chevron promet l'ouverture d'une page : il n'a de sens que sur un lien. */
        expect(line?.querySelector('svg')).not.toBeNull();
    });

    it('rend les liquidités sans lien, ni chevron, ni survol', () => {
        const host = mountSection([classOf({ key: 'cash', label: 'Liquidités', href: null })]);

        const line = host.querySelector('[data-wealth-class] > *') as HTMLElement;

        expect(host.querySelector('[data-wealth-class] a')).toBeNull();
        expect(line.tagName).toBe('DIV');
        expect(line.className).not.toContain('hover:bg-muted');
        expect(line.querySelector('svg')).toBeNull();
        /** La valeur et la part restent lisibles : seule la promesse d'un ailleurs disparaît. */
        expect(line.textContent).toContain('Liquidités');
        expect(host.querySelector('[data-wealth-share]')?.textContent?.trim()).toBe('100,0 %');
    });
});
