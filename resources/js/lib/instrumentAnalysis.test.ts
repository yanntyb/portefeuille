import { describe, expect, it } from 'vitest';
import { analysisGroups } from '@/lib/instrumentAnalysis';
import type { InstrumentAnalysis } from '@/lib/instrument';

/** Copie du helper de `instrument.test.ts` : `toLocaleString` sépare avec des espaces insécables. */
const normalizeSpaces = (value: string): string => value.replace(/[\xa0\u202f]/g, ' ');

const analysis = (overrides: Partial<InstrumentAnalysis> = {}): InstrumentAnalysis => ({
    price: 100,
    pru: 80,
    pruGapPct: 25,
    high52w: 94.1,
    high52wGapPct: -22.2,
    maxDrawdown: 31.4,
    portfolioWeightPct: 4.2,
    ...overrides,
});

describe('analysisGroups', () => {
    it('range les repères en référence, tendance et risque', () => {
        expect(analysisGroups(analysis()).map((group) => group.title)).toEqual([
            null,
            'Tendance',
            'Risque',
        ]);
    });

    it('formate chaque repère selon son unité', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);
        const valueOf = (indicator: string): string =>
            normalizeSpaces(rows.find((row) => row.indicator === indicator)!.value);

        expect(valueOf('price')).toBe('100,00 €');
        expect(valueOf('pru')).toBe('80,00 €');
        expect(valueOf('pruGap')).toBe('+25,0 %');
        expect(valueOf('high52w')).toBe('94,10 €');
        expect(valueOf('high52wGap')).toBe('-22,2 %');
        expect(valueOf('portfolioWeight')).toBe('4,2 %');
    });

    it('pose un signe négatif sur le drawdown, qui arrive en positif', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);

        expect(normalizeSpaces(rows.find((row) => row.indicator === 'maxDrawdown')!.value))
            .toBe('-31,4 %');
    });

    it('ne pose aucun signe sur un drawdown nul', () => {
        const rows = analysisGroups(analysis({ maxDrawdown: 0 })).flatMap((group) => group.rows);

        expect(normalizeSpaces(rows.find((row) => row.indicator === 'maxDrawdown')!.value))
            .toBe('0,0 %');
    });

    it('ne colore que l\'écart au prix de revient', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);
        const colored = rows.filter((row) => row.gain !== undefined);

        expect(colored.map((row) => row.indicator)).toEqual(['pruGap']);
    });

    it('rend un tiret sur un repère absent plutôt que de masquer sa ligne', () => {
        const rows = analysisGroups(analysis({ high52w: null })).flatMap((group) => group.rows);

        expect(rows.find((row) => row.indicator === 'high52w')!.value).toBe('—');
    });

    it('efface un groupe dont tous les repères manquent', () => {
        const groups = analysisGroups(analysis({
            maxDrawdown: null,
            portfolioWeightPct: null,
        }));

        expect(groups.map((group) => group.title)).toEqual([null, 'Tendance']);
    });
});
