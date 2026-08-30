import { describe, expect, it } from 'vitest';
import { analysisGroups } from '@/lib/instrumentAnalysis';
import { indicatorHelp } from '@/lib/indicatorHelp';
import type { InstrumentAnalysis } from '@/lib/instrument';

const full: InstrumentAnalysis = {
    pru: 80,
    pruGapPct: 25,
    ma200: 82.4,
    ma200GapPct: -11.2,
    rsi14: 38,
    high52w: 94.1,
    high52wGapPct: -22.2,
    atr: 1.9,
    atrPct: 1.9,
    maxDrawdown: 31.4,
    portfolioWeightPct: 4.2,
};

describe('indicatorHelp', () => {
    it('couvre chaque repère que la section peut afficher', () => {
        const shown = analysisGroups(full).flatMap((group) => group.rows.map((row) => row.indicator));

        shown.forEach((indicator) => {
            expect(indicatorHelp[indicator], indicator).toBeDefined();
        });
    });

    it('donne à chaque aide un titre et au moins un paragraphe', () => {
        Object.entries(indicatorHelp).forEach(([indicator, help]) => {
            expect(help.title.length, indicator).toBeGreaterThan(0);
            expect(help.subtitle.length, indicator).toBeGreaterThan(0);
            expect(help.body.length, indicator).toBeGreaterThan(0);
            help.body.forEach((paragraph) => expect(paragraph.trim().length).toBeGreaterThan(0));
        });
    });

    it('nomme chaque aide « Comment lire… », comme l\'aide des performances', () => {
        Object.values(indicatorHelp).forEach((help) => {
            expect(help.title.startsWith('Comment lire')).toBe(true);
        });
    });
});
