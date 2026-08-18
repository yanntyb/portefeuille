import { describe, expect, it } from 'vitest';
import type { CatalogLine, CatalogTrend } from '@/lib/catalog';
import { instrumentSections, mergeInstrumentRows, type InstrumentRow, type InstrumentSection } from '@/lib/instrumentList';
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

const sectionNames = (sections: InstrumentSection[]): string[][] =>
    sections.map((section) => names(section.rows));

describe('instrumentSections', () => {
    const merged = (): InstrumentRow[] =>
        mergeInstrumentRows(
            [holding(1, 'Alpha', 1000), holding(2, 'Beta', 3000)],
            [line(1, 'Alpha', true), line(2, 'Beta', true), line(3, 'Gamma'), line(4, 'Delta')],
            undefined,
        );

    it('groupe positions puis catalogue sans recherche, la plus grosse position en tête', () => {
        const sections = instrumentSections(merged(), '');

        expect(sections.map((section) => section.label)).toEqual(['Mes positions', 'Autres instruments']);
        expect(sectionNames(sections)).toEqual([['Beta', 'Alpha'], ['Delta', 'Gamma']]);
    });

    it('traite les espaces seuls comme une recherche vide', () => {
        expect(instrumentSections(merged(), '   ').map((section) => section.label)).toEqual([
            'Mes positions',
            'Autres instruments',
        ]);
    });

    it('tait le groupe des positions quand le portefeuille est vide', () => {
        const rows = mergeInstrumentRows([], [line(3, 'Gamma'), line(4, 'Delta')], undefined);

        expect(instrumentSections(rows, '').map((section) => section.label)).toEqual(['Autres instruments']);
    });

    it('tait le groupe du catalogue quand tout est détenu', () => {
        const rows = mergeInstrumentRows([holding(1, 'Alpha', 1000)], [line(1, 'Alpha', true)], undefined);

        expect(instrumentSections(rows, '').map((section) => section.label)).toEqual(['Mes positions']);
    });

    it('annonce le groupe du catalogue en attente tant que le catalogue est différé', () => {
        const rows = mergeInstrumentRows([holding(1, 'Alpha', 1000)], undefined, undefined);
        const sections = instrumentSections(rows, '', true);

        expect(sections.map((section) => section.label)).toEqual(['Mes positions', 'Autres instruments']);
        expect(sections[1].pending).toBe(true);
        expect(sections[1].rows).toEqual([]);
        expect(sections[0].pending).toBe(false);
    });

    it('aplatit la liste dès qu\'on tape, sans en-tête', () => {
        const sections = instrumentSections(merged(), 'a');

        expect(sections).toHaveLength(1);
        expect(sections[0].label).toBeNull();
        expect(sections[0].pending).toBe(false);
    });

    it('classe les résultats par pertinence, ticker tapé d\'abord puis nom, détenu ou non', () => {
        const rows = mergeInstrumentRows(
            [holding(2, 'Beta', 3000)],
            [line(1, 'Deltana', true), line(2, 'Beta', true), line(3, 'Zeta Del'), line(4, 'Delta')],
            undefined,
        );

        expect(names(instrumentSections(rows, 'del')[0].rows)).toEqual(['Delta', 'Deltana', 'Zeta Del']);
    });

    it('départage deux résultats de même rang par leur nom', () => {
        const rows = mergeInstrumentRows([], [line(3, 'Gamma'), line(4, 'Delta')], undefined);

        expect(names(instrumentSections(rows, 'a')[0].rows)).toEqual(['Delta', 'Gamma']);
    });

    it('cherche aussi par ticker et par ISIN', () => {
        expect(names(instrumentSections(merged(), 'GAM')[0].rows)).toEqual(['Gamma']);
        expect(names(instrumentSections(merged(), 'FR0000000003')[0].rows)).toEqual(['Gamma']);
    });

    it('ne rend aucune section quand rien ne correspond', () => {
        expect(instrumentSections(merged(), 'zzz')).toEqual([]);
    });

    it('ne montre pas de squelette pendant une recherche, le catalogue différé ne se cherche pas', () => {
        const rows = mergeInstrumentRows([holding(1, 'Alpha', 1000)], undefined, undefined);

        expect(instrumentSections(rows, 'alp')[0].pending).toBe(false);
    });
});
