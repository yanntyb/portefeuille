import { describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type VNode } from 'vue';
import type { HoldingLine } from '@/lib/portfolio';

/**
 * `usePage` sert les props rescapées ; `Link` se réduit à l'ancrage qu'il rend. `Deferred` demande
 * un routeur monté ; le composant ne le rend que sans positions, et les tests lui en donnent le
 * cas échéant — un composant vide suffit donc à satisfaire l'import.
 */
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ rescuedProps: [] }),
    Deferred: { setup: () => () => null },
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

describe('positions différées', () => {
    /**
     * `holdings` à `null` distingue « pas encore arrivé » d'« arrivé et vide » : sans cette
     * distinction, la section affirmerait l'absence de position avant même d'avoir reçu la
     * réponse (voir la page d'une enveloppe, qui sert `positions` en différé).
     */
    it('n\'affirme pas l\'absence de position tant que holdings vaut null', async () => {
        const host = mountSection({ holdings: null, deferKey: 'positions' });

        host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
        await nextTick();

        expect(host.querySelector('[data-instrument-empty]')).toBeNull();
    });

    /** `holdings` par défaut est le tableau vide du harnais de test, pas une valeur posée par le composant : positions arrivées, aucune ligne. */
    it('affiche le libellé vide quand les positions sont arrivées sans aucune ligne', async () => {
        const host = mountSection();

        host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
        await nextTick();

        expect(host.querySelector('[data-instrument-empty]')).not.toBeNull();
    });

    it('n\'affirme pas l\'absence de position tant qu\'elles ne sont pas arrivées', async () => {
        const host = mountSection({ holdings: null });

        host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
        await nextTick();

        expect(host.querySelector('[data-instrument-empty]')).toBeNull();
        expect(host.querySelector('[data-instrument-row]')).toBeNull();
    });
});
