import { describe, expect, it } from 'vitest';
import { annualIncomeBars, dividendYears, type AnnualIncome, type DividendReceipt } from './income';

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

const receipt = (overrides: Partial<DividendReceipt> = {}): DividendReceipt => ({
    assetId: 1,
    exDate: '2026-03-05',
    quantity: 10,
    amountPerShare: 0.5,
    amount: 5,
    ...overrides,
});

describe('dividendYears', () => {
    it('regroupe les détachements par année, la plus récente en tête', () => {
        const years = dividendYears([
            receipt({ exDate: '2024-11-28' }),
            receipt({ exDate: '2026-03-05' }),
            receipt({ exDate: '2025-06-04' }),
        ]);

        expect(years.map((group) => group.year)).toEqual(['2026', '2025', '2024']);
    });

    it('garde dans chaque année ses seuls détachements', () => {
        const years = dividendYears([
            receipt({ exDate: '2026-03-05' }),
            receipt({ exDate: '2026-09-11' }),
            receipt({ exDate: '2025-06-04' }),
        ]);

        expect(years[0].receipts).toHaveLength(2);
        expect(years[0].receipts.map((entry) => entry.exDate)).toEqual(['2026-03-05', '2026-09-11']);
        expect(years[1].receipts.map((entry) => entry.exDate)).toEqual(['2025-06-04']);
    });

    it('cumule le perçu de l\'année sur ses détachements', () => {
        const years = dividendYears([
            receipt({ exDate: '2026-03-05', amount: 5 }),
            receipt({ exDate: '2026-09-11', amount: 3.5 }),
        ]);

        expect(years[0].total).toBe(8.5);
    });

    it('ne rend aucun groupe sans détachement', () => {
        expect(dividendYears([])).toEqual([]);
    });
});
