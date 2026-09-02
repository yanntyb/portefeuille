import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';
import type { HoldingLine } from '@/lib/portfolio';

/** `usePage` sert les props rescapées ; `Link` se réduit à l'ancrage qu'il rend. */
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ rescuedProps: [] }),
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
}));

const { default: InstrumentsSection } = await import('@/components/instruments/InstrumentsSection.vue');

const holdings: HoldingLine[] = [];

function mountSection(props: Record<string, unknown> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(InstrumentsSection, { holdings, trends: [], catalogHref: '/actions/catalogue', ...props }).mount(host);

    return host;
}

describe('en-tête de la section des instruments', () => {
    it('porte une loupe menant au catalogue de la poche', () => {
        const link = mountSection().querySelector<HTMLAnchorElement>('[data-catalog-link]');

        expect(link?.getAttribute('href')).toBe('/actions/catalogue');
        expect(link?.getAttribute('aria-label')).toBe('Rechercher un instrument');
    });

    /** La bascule du pli est une `<button>` : imbriquer le lien dedans donnerait un DOM invalide. */
    it('laisse le lien hors de la bascule du pli', () => {
        const host = mountSection();

        expect(host.querySelector('[data-section-toggle] [data-catalog-link]')).toBeNull();
        expect(host.querySelector('[data-catalog-link]')).not.toBeNull();
    });

    /** Une enveloppe n'a pas de catalogue : sans adresse, la loupe n'a nulle part à mener. */
    it('n\'affiche aucune loupe quand aucun catalogue n\'est donné', () => {
        expect(mountSection({ catalogHref: undefined }).querySelector('[data-catalog-link]')).toBeNull();
    });
});
