import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { SectorBreakdownRow } from '@/lib/sector';

/** `Deferred` demande un routeur monté ; le bloc d'attente ne rend rien ici, c'est ce qu'on vérifie. */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: { setup: () => () => null },
}));

const { default: SectorsSection } = await import('@/components/SectorsSection.vue');

const rows: SectorBreakdownRow[] = [
    { label: 'Technologie', share: 60, amount: 600 },
    { label: 'Santé', share: 40, amount: 400 },
];

function mountSection(props: Record<string, unknown>): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(SectorsSection, { section: 'wealth-sectors', deferKey: 'sectors', ...props }).mount(host);

    return host;
}

const open = async (host: HTMLElement): Promise<void> => {
    host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
    await nextTick();
};

describe('section sectorielle', () => {
    it('se replie sous un titre et liste les secteurs au dépli', async () => {
        const host = mountSection({ rows });

        expect(host.querySelector('[data-section="wealth-sectors"]')).not.toBeNull();
        expect(host.querySelectorAll('[data-sector-label]')).toHaveLength(0);

        await open(host);

        expect(host.querySelectorAll('[data-sector-label]')).toHaveLength(2);
    });

    it('dit l\'absence de secteurs quand la liste est arrivée vide', async () => {
        const host = mountSection({ rows: [] });
        await open(host);

        expect(host.textContent).toContain('Pas encore de données sectorielles.');
    });

    it('ne dit rien tant que la liste n\'est pas arrivée', async () => {
        const host = mountSection({ rows: null });
        await open(host);

        expect(host.querySelector('[data-sector-label]')).toBeNull();
        expect(host.textContent).not.toContain('Pas encore');
    });

    it('se rend en bloc étiqueté, sans pli, dans une analyse', () => {
        const host = mountSection({ rows, variant: 'block', deferKey: 'sectorBreakdown' });

        expect(host.querySelector('[data-sectors-block]')).not.toBeNull();
        expect(host.querySelector('[data-section-toggle]')).toBeNull();
        expect(host.querySelectorAll('[data-sector-label]')).toHaveLength(2);
        expect(host.querySelector('[data-sector-toggle]')).toBeNull();
    });
});
