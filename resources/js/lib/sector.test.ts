import { describe, expect, it } from 'vitest';
import { collapsedSectors, rowsFromShares, rowsFromSlices, rowsFromWeights, type SectorBreakdownRow } from '@/lib/sector';

const row = (label: string, share: number, amount: number | null = null): SectorBreakdownRow => ({
    label,
    share,
    amount,
});

const labelsOf = (view: { rows: { row: SectorBreakdownRow }[] }): string[] =>
    view.rows.map((entry) => entry.row.label);

/** Huit secteurs dans le désordre : la coupe ne peut être juste que si le tri la précède. */
const eightScrambled = (): SectorBreakdownRow[] => [
    row('Conso', 50),
    row('Immobilier', 10),
    row('Santé', 80),
    row('Télécom', 30),
    row('Technologie', 90),
    row('Énergie', 20),
    row('Finance', 60),
    row('Industrie', 40),
];

describe('collapsedSectors', () => {
    it('trie les secteurs de la plus grosse part à la plus petite', () => {
        const view = collapsedSectors([row('Santé', 40), row('Technologie', 60)], false);

        expect(labelsOf(view)).toEqual(['Technologie', 'Santé']);
    });

    it('mesure les barres contre le plus gros secteur, pas contre le total', () => {
        const view = collapsedSectors([row('Santé', 40), row('Technologie', 60)], false);

        expect(view.rows.map((entry) => entry.barWidth)).toEqual(['100%', `${(40 / 60) * 100}%`]);
    });

    it('replie les secteurs au-delà du sixième, en gardant les six plus gros', () => {
        const view = collapsedSectors(eightScrambled(), false);

        expect(labelsOf(view)).toEqual(['Technologie', 'Santé', 'Finance', 'Conso', 'Industrie', 'Télécom']);
        expect(view.hiddenCount).toBe(2);
    });

    it('montre tout une fois déplié, toujours du plus gros au plus petit', () => {
        const view = collapsedSectors(eightScrambled(), true);

        expect(labelsOf(view)).toEqual([
            'Technologie', 'Santé', 'Finance', 'Conso', 'Industrie', 'Télécom', 'Énergie', 'Immobilier',
        ]);
        expect(view.hiddenCount).toBe(2);
    });

    it('ne cache rien quand il y a six secteurs ou moins', () => {
        const six = [row('Conso', 30), row('Immobilier', 10), row('Santé', 50), row('Télécom', 20), row('Technologie', 60), row('Énergie', 40)];

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

describe('conversion vers les lignes de la liste sectorielle', () => {
    it('répartit la valeur détenue sur des poids entre 0 et 1', () => {
        expect(rowsFromWeights([{ label: 'Tech', weight: 0.25 }], 1000)).toEqual([
            { label: 'Tech', share: 25, amount: 250 },
        ]);
    });

    it('laisse le montant nul quand rien n\'est détenu', () => {
        expect(rowsFromWeights([{ label: 'Tech', weight: 0.25 }], null)).toEqual([
            { label: 'Tech', share: 25, amount: null },
        ]);
    });

    it('lit une tranche déjà pesée, en pourcentage ou en part', () => {
        expect(rowsFromSlices([{ label: 'Santé', value: 300, pct: 30 }])).toEqual([{ label: 'Santé', share: 30, amount: 300 }]);
        expect(rowsFromShares([{ label: 'Actions', value: 600, share: 60 }])).toEqual([{ label: 'Actions', share: 60, amount: 600 }]);
    });
});
