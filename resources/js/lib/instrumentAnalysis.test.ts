import { describe, expect, it } from 'vitest';
import { analysisGroups } from '@/lib/instrumentAnalysis';
import type { InstrumentAnalysis } from '@/lib/instrument';

/** Copie du helper de `instrument.test.ts` : `toLocaleString` sépare avec des espaces insécables. */
const normalizeSpaces = (value: string): string => value.replace(/[\xa0\u202f]/g, ' ');

const analysis = (overrides: Partial<InstrumentAnalysis> = {}): InstrumentAnalysis => ({
    pru: 80,
    pruGapPct: 25,
    ma200: 82.4,
    ma200GapPct: -11.2,
    rsi14: 38.4,
    high52w: 94.1,
    high52wGapPct: -22.2,
    atr: 1.9,
    atrPct: 1.9,
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

        expect(valueOf('pru')).toBe('80,00 €');
        expect(valueOf('pruGap')).toBe('+25,0 %');
        expect(valueOf('ma200')).toBe('82,40 €');
        expect(valueOf('ma200Gap')).toBe('-11,2 %');
        expect(valueOf('rsi14')).toBe('38');
        expect(valueOf('atrPct')).toBe('1,9 %');
        expect(valueOf('portfolioWeight')).toBe('4,2 %');
    });

    it('pose un signe négatif sur le drawdown, qui arrive en positif', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);

        expect(normalizeSpaces(rows.find((row) => row.indicator === 'maxDrawdown')!.value))
            .toBe('-31,4 %');
    });

    it('ne colore que l\'écart au prix de revient', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);
        const colored = rows.filter((row) => row.gain !== undefined);

        expect(colored.map((row) => row.indicator)).toEqual(['pruGap']);
    });

    it('rend un tiret sur un repère absent plutôt que de masquer sa ligne', () => {
        const rows = analysisGroups(analysis({ rsi14: null })).flatMap((group) => group.rows);

        expect(rows.find((row) => row.indicator === 'rsi14')!.value).toBe('—');
    });

    it('efface un groupe dont tous les repères manquent', () => {
        const groups = analysisGroups(analysis({
            atr: null,
            atrPct: null,
            maxDrawdown: null,
            portfolioWeightPct: null,
        }));

        expect(groups.map((group) => group.title)).toEqual([null, 'Tendance']);
    });
});
