import { describe, expect, it } from 'vitest';
import type { CatalogLine, CatalogTrend } from '@/lib/catalog';
import { mergeInstrumentRows, visibleInstrumentRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const holding = (assetId: number, assetName: string, marketValue: number): HoldingLine => ({
    assetId,
    assetName,
    ticker: assetName.slice(0, 3).toUpperCase(),
    type: 'stock',
    typeLabel: 'Action',
    quantity: 10,
    avgCost: 80,
    lastPrice: marketValue / 10,
    marketValue,
    gain: 200,
    gainPct: 25,
});

const line = (id: number, name: string, held = false): CatalogLine => ({
    id,
    name,
    ticker: name.slice(0, 3).toUpperCase(),
    isin: `FR000000000${id}`,
    type: 'stock',
    typeLabel: 'Action',
    lastPrice: 100,
    held,
    quantity: held ? 10 : null,
    marketValue: held ? 1000 : null,
});

const names = (rows: InstrumentRow[]): string[] => rows.map((row) => row.name);

describe('mergeInstrumentRows', () => {
    it('porte le gain et le poids de la position sur la ligne détenue', () => {
        const rows = mergeInstrumentRows([holding(1, 'Alpha', 3000)], [line(1, 'Alpha', true)], undefined);

        expect(rows[0].held).toBe(true);
        expect(rows[0].gain).toBe(200);
        expect(rows[0].gainPct).toBe(25);
        expect(rows[0].share).toBe(100);
        expect(rows[0].barWidth).not.toBeNull();
    });

    it('laisse une ligne non détenue sans gain ni poids', () => {
        const rows = mergeInstrumentRows([], [line(2, 'Beta')], undefined);

        expect(rows[0].held).toBe(false);
        expect(rows[0].gain).toBeNull();
        expect(rows[0].share).toBeNull();
        expect(rows[0].barWidth).toBeNull();
        expect(rows[0].opacity).toBeNull();
    });

    it('calcule les parts sur les seules positions, jamais sur le catalogue entier', () => {
        const rows = mergeInstrumentRows(
            [holding(1, 'Alpha', 3000), holding(2, 'Beta', 1000)],
            [line(1, 'Alpha', true), line(2, 'Beta', true), line(3, 'Gamma'), line(4, 'Delta')],
            undefined,
        );

        expect(rows.find((row) => row.name === 'Alpha')?.share).toBe(75);
        expect(rows.find((row) => row.name === 'Beta')?.share).toBe(25);
    });

    it('rattache la tendance de la période par identifiant d\'actif', () => {
        const trends: CatalogTrend[] = [{ assetId: 2, changePct: 12.5, points: [1, 2, 3] }];
        const rows = mergeInstrumentRows([], [line(1, 'Alpha'), line(2, 'Beta')], trends);

        expect(rows.find((row) => row.name === 'Beta')?.changePct).toBe(12.5);
        expect(rows.find((row) => row.name === 'Beta')?.points).toEqual([1, 2, 3]);
        expect(rows.find((row) => row.name === 'Alpha')?.changePct).toBeNull();
    });

    it('tient sur les seules positions tant que le catalogue est différé', () => {
        const rows = mergeInstrumentRows([holding(1, 'Alpha', 3000)], undefined, undefined);

        expect(names(rows)).toEqual(['Alpha']);
        expect(rows[0].held).toBe(true);
        expect(rows[0].share).toBe(100);
    });

    it('garde une position absente du catalogue plutôt que de la perdre', () => {
        const rows = mergeInstrumentRows([holding(9, 'Orpheline', 500)], [line(1, 'Alpha')], undefined);

        expect(names(rows)).toContain('Orpheline');
        expect(rows.find((row) => row.name === 'Orpheline')?.held).toBe(true);
    });
});

describe('visibleInstrumentRows', () => {
    const merged = (): InstrumentRow[] =>
        mergeInstrumentRows(
            [holding(1, 'Alpha', 1000), holding(2, 'Beta', 3000)],
            [line(1, 'Alpha', true), line(2, 'Beta', true), line(3, 'Gamma'), line(4, 'Delta')],
            undefined,
        );

    it('ne montre que les positions sur une recherche vide, la plus grosse en tête', () => {
        expect(names(visibleInstrumentRows(merged(), ''))).toEqual(['Beta', 'Alpha']);
        expect(names(visibleInstrumentRows(merged(), '   '))).toEqual(['Beta', 'Alpha']);
    });

    it('ouvre le catalogue entier dès qu\'on tape, détenus d\'abord', () => {
        expect(names(visibleInstrumentRows(merged(), 'a'))).toEqual(['Beta', 'Alpha', 'Delta', 'Gamma']);
    });

    it('range les non détenus par nom', () => {
        const rows = mergeInstrumentRows([], [line(3, 'Gamma'), line(4, 'Delta')], undefined);

        expect(names(visibleInstrumentRows(rows, 'a'))).toEqual(['Delta', 'Gamma']);
    });

    it('cherche aussi par ticker et par ISIN', () => {
        expect(names(visibleInstrumentRows(merged(), 'GAM'))).toEqual(['Gamma']);
        expect(names(visibleInstrumentRows(merged(), 'FR0000000003'))).toEqual(['Gamma']);
    });

    it('rend une liste vide quand rien ne correspond', () => {
        expect(visibleInstrumentRows(merged(), 'zzz')).toEqual([]);
    });
});
