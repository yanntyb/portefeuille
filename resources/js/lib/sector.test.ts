import { describe, expect, it } from 'vitest';
import { collapsedSectors, type SectorBreakdownRow } from '@/lib/sector';

const row = (label: string, share: number, amount: number | null = null): SectorBreakdownRow => ({
    label,
    share,
    amount,
});

const labelsOf = (view: { rows: { row: SectorBreakdownRow }[] }): string[] =>
    view.rows.map((entry) => entry.row.label);

describe('collapsedSectors', () => {
    it('trie les secteurs de la plus grosse part à la plus petite', () => {
        const view = collapsedSectors([row('Santé', 40), row('Technologie', 60)], false);

        expect(labelsOf(view)).toEqual(['Technologie', 'Santé']);
    });

    it('mesure les barres contre le plus gros secteur, pas contre le total', () => {
        const view = collapsedSectors([row('Santé', 40), row('Technologie', 60)], false);

        expect(view.rows.map((entry) => entry.barWidth)).toEqual(['100%', `${(40 / 60) * 100}%`]);
    });

    it('replie les secteurs au-delà du sixième et annonce le nombre caché', () => {
        const eight = Array.from({ length: 8 }, (_unused, index) => row(`S${index}`, 80 - index * 10));

        const view = collapsedSectors(eight, false);

        expect(view.rows).toHaveLength(6);
        expect(view.hiddenCount).toBe(2);
    });

    it('montre tout une fois déplié', () => {
        const eight = Array.from({ length: 8 }, (_unused, index) => row(`S${index}`, 80 - index * 10));

        const view = collapsedSectors(eight, true);

        expect(view.rows).toHaveLength(8);
        expect(view.hiddenCount).toBe(2);
    });

    it('ne cache rien quand il y a six secteurs ou moins', () => {
        const six = Array.from({ length: 6 }, (_unused, index) => row(`S${index}`, 60 - index * 10));

        expect(collapsedSectors(six, false).hiddenCount).toBe(0);
    });

    it('porte le montant du secteur quand il y en a un, et rien quand il n\'y en a pas', () => {
        const view = collapsedSectors([row('Technologie', 60, 900), row('Santé', 40)], false);

        expect(view.rows[0].row.amount).toBe(900);
        expect(view.rows[1].row.amount).toBeNull();
    });

    it('rend une vue vide sans secteur', () => {
        const view = collapsedSectors([], false);

        expect(view.rows).toEqual([]);
        expect(view.hiddenCount).toBe(0);
    });
});
