import { describe, expect, it } from 'vitest';
import { correlationGrid, basketRows } from '@/lib/basketAnalysis';
import type { BasketAnalysis } from '@/lib/basketAnalysis';

const analysis = (overrides: Partial<BasketAnalysis> = {}): BasketAnalysis => ({
    maxDrawdown: 31.4,
    high52wGapPct: -12.5,
    instruments: [
        { assetId: 1, label: 'CW8' },
        { assetId: 2, label: 'SP5' },
    ],
    correlations: [
        [1, 0.94],
        [0.94, 1],
    ],
    ...overrides,
});

describe('basketRows', () => {
    it('rend la chute maximale en négatif et la distance au plus-haut', () => {
        const rows = basketRows(analysis());

        expect(rows.map((row) => [row.label, row.value])).toEqual([
            ['Sous le plus-haut', '-12,5 %'],
            ['Max drawdown', '-31,4 %'],
        ]);
    });

    it('rend un tiret sur un repère que la poche ne sait pas mesurer', () => {
        const rows = basketRows(analysis({ maxDrawdown: null, high52wGapPct: null }));

        expect(rows.map((row) => row.value)).toEqual(['—', '—']);
    });

    it('écrit une chute nulle sans signe', () => {
        expect(basketRows(analysis({ maxDrawdown: 0 }))[1].value).toBe('0,0 %');
    });
});

describe('correlationGrid', () => {
    it('rend une ligne par instrument, libellée par son étiquette', () => {
        const grid = correlationGrid(analysis());

        expect(grid.map((row) => row.label)).toEqual(['CW8', 'SP5']);
    });

    it('formate chaque corrélation à deux décimales', () => {
        expect(correlationGrid(analysis())[0].cells[1].value).toBe('0,94');
    });

    it('laisse la diagonale muette : un instrument se corrèle toujours à lui-même', () => {
        const cell = correlationGrid(analysis())[0].cells[0];

        expect(cell.isSelf).toBe(true);
        expect(cell.value).toBe('');
    });

    it('rend un tiret sur une paire sans assez d’histoire commune', () => {
        const grid = correlationGrid(
            analysis({
                correlations: [
                    [1, null],
                    [null, 1],
                ],
            }),
        );

        expect(grid[0].cells[1].value).toBe('—');
    });

    it('chauffe la case des paires qui bougent ensemble et refroidit celle des autres', () => {
        const grid = correlationGrid(
            analysis({
                correlations: [
                    [1, 0.94, 0.5, -0.6],
                    [0.94, 1, 0.5, -0.6],
                    [0.5, 0.5, 1, -0.6],
                    [-0.6, -0.6, -0.6, 1],
                ],
                instruments: [
                    { assetId: 1, label: 'A' },
                    { assetId: 2, label: 'B' },
                    { assetId: 3, label: 'C' },
                    { assetId: 4, label: 'D' },
                ],
            }),
        );

        const tones = grid[0].cells.map((cell) => cell.tone);

        expect(tones[1]).not.toBe(tones[2]);
        expect(tones[3]).not.toBe(tones[1]);
    });

    it('ne rend aucune grille sans instrument', () => {
        expect(correlationGrid(analysis({ instruments: [], correlations: [] }))).toEqual([]);
    });
});
