import { describe, expect, it } from 'vitest';
import { analysisGroups, type IndicatorId } from '@/lib/instrumentAnalysis';
import { indicatorHelp } from '@/lib/indicatorHelp';
import type { InstrumentAnalysis } from '@/lib/instrument';

const full: InstrumentAnalysis = {
    pru: 80,
    pruGapPct: 25,
    high52w: 94.1,
    high52wGapPct: -22.2,
    atr: 1.9,
    atrPct: 1.9,
    maxDrawdown: 31.4,
    portfolioWeightPct: 4.2,
};

describe('indicatorHelp', () => {
    it('n\'explique que l\'ATR : les autres repères se lisent dans leur libellé', () => {
        expect(Object.keys(indicatorHelp)).toEqual(['atrPct']);
    });

    it('n\'explique aucun repère que la section n\'affiche pas', () => {
        const shown = analysisGroups(full).flatMap((group) => group.rows.map((row) => row.indicator));

        Object.keys(indicatorHelp).forEach((indicator) => {
            expect(shown).toContain(indicator as IndicatorId);
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
