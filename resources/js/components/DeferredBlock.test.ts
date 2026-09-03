import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';

/**
 * `Deferred` demande un routeur monté. Son double rend ses trois slots côte à côte, chacun sous un
 * marqueur, pour que le test lise ce que le bloc met dans chacun.
 */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: {
        props: { data: { type: [String, Array], required: true } },
        setup: (props: { data: string | string[] }, { slots }: { slots: Record<string, () => VNode[]> }) => () =>
            h('div', { 'data-deferred': String(props.data) }, [
                h('div', { 'data-slot': 'fallback' }, slots.fallback?.()),
                h('div', { 'data-slot': 'rescue' }, slots.rescue?.()),
                h('div', { 'data-slot': 'default' }, slots.default?.()),
            ]),
    },
}));

const { default: DeferredBlock } = await import('@/components/DeferredBlock.vue');

function mountBlock(props: Record<string, unknown>, slots: Record<string, () => VNode[]> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp({
        render: () => h(DeferredBlock, props as unknown as InstanceType<typeof DeferredBlock>['$props'], slots),
    }).mount(host);

    return host;
}

describe('bloc différé', () => {
    it('nomme la prop attendue et pose trois lignes de squelette par défaut', () => {
        const host = mountBlock({ data: 'transactions' });

        expect(host.querySelector('[data-deferred="transactions"]')).not.toBeNull();

        const lines = host.querySelectorAll('[data-slot="fallback"] .animate-pulse');
        expect(lines).toHaveLength(3);
        expect(lines[0].classList.contains('h-8')).toBe(true);
    });

    it('adapte le nombre et la hauteur des lignes', () => {
        const lines = mountBlock({ data: 'analysis', lines: 6, lineClass: 'h-6' })
            .querySelectorAll('[data-slot="fallback"] .animate-pulse');

        expect(lines).toHaveLength(6);
        expect(lines[0].classList.contains('h-6')).toBe(true);
    });

    it('laisse un appelant fournir son propre squelette', () => {
        const host = mountBlock({ data: 'series' }, { fallback: () => [h('div', { 'data-chart-skeleton': '' })] });

        expect(host.querySelector('[data-slot="fallback"] [data-chart-skeleton]')).not.toBeNull();
        expect(host.querySelector('[data-slot="fallback"] .animate-pulse')).toBeNull();
    });

    it('dit une seule fois que les données sont indisponibles hors-ligne, et pose la sentinelle', () => {
        const host = mountBlock({ data: 'income' });

        expect(host.querySelector('[data-slot="rescue"]')?.textContent?.trim()).toBe('Données indisponibles hors-ligne.');
        expect(host.querySelector('[data-slot="default"] span')).not.toBeNull();
    });
});
