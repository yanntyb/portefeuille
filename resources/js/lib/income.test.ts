import { describe, expect, it } from 'vitest';
import { annualIncomeBars, type AnnualIncome } from './income';

const year = (year: number, total: number): AnnualIncome => ({ year, total, bySource: { dividend: total } });

describe('annualIncomeBars', () => {
    it('mesure chaque barre contre la plus grande année', () => {
        const bars = annualIncomeBars([year(2025, 50), year(2026, 100)]);

        expect(bars.map((bar) => bar.barWidth)).toEqual(['50%', '100%']);
    });

    it('rend une barre nulle quand aucune année ne porte de revenu', () => {
        expect(annualIncomeBars([year(2026, 0)])[0].barWidth).toBe('0%');
    });

    it('ne rend rien sans année', () => {
        expect(annualIncomeBars([])).toEqual([]);
    });
});
