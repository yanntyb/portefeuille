import { describe, expect, it } from 'vitest';
import { annualIncomeBars, dividendMarks, dividendYears, type AnnualIncome, type DividendReceipt } from './income';

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

describe('dividendMarks', () => {
    /** Série hebdomadaire : un détachement tombe presque toujours entre deux points. */
    const weekly = ['2026-01-05', '2026-01-12', '2026-01-19', '2026-01-26'];

    it('cale chaque détachement sur le dernier point qui le précède', () => {
        const marks = dividendMarks(weekly, [receipt({ exDate: '2026-01-15' })]);

        expect(marks.map((mark) => mark.index)).toEqual([1]);
    });

    it('cale un détachement tombant pile sur un point sur ce point', () => {
        const marks = dividendMarks(weekly, [receipt({ exDate: '2026-01-19' })]);

        expect(marks.map((mark) => mark.index)).toEqual([2]);
    });

    it('cale un détachement postérieur au dernier point sur ce dernier point', () => {
        const marks = dividendMarks(weekly, [receipt({ exDate: '2026-01-30' })]);

        expect(marks.map((mark) => mark.index)).toEqual([3]);
    });

    it('ignore un détachement antérieur au premier point : la position n\'y était pas encore valorisée', () => {
        expect(dividendMarks(weekly, [receipt({ exDate: '2025-12-20' })])).toEqual([]);
    });

    it('garde une entrée par détachement quand deux tombent sur le même point', () => {
        const marks = dividendMarks(weekly, [
            receipt({ exDate: '2026-01-13', amount: 5 }),
            receipt({ exDate: '2026-01-16', amount: 3 }),
        ]);

        expect(marks.map((mark) => mark.index)).toEqual([1, 1]);
    });

    it('libelle la date réelle du détachement et non celle du point sur lequel il se cale', () => {
        const marks = dividendMarks(weekly, [receipt({ exDate: '2026-01-15' })]);

        expect(marks[0].dateLabel).toBe('15 janv.');
    });

    it('libelle le montant signé au centime : la précision du montant par action ne se lit pas ici', () => {
        const marks = dividendMarks(weekly, [receipt({ exDate: '2026-01-15', amount: 12.4 })]);

        expect(marks[0].amountLabel.replace(/[\xa0 ]/g, ' ')).toBe('+12,40 €');
    });

    it('ordonne les repères comme l\'axe, du plus ancien au plus récent', () => {
        const marks = dividendMarks(weekly, [
            receipt({ exDate: '2026-01-22' }),
            receipt({ exDate: '2026-01-08' }),
        ]);

        expect(marks.map((mark) => mark.index)).toEqual([0, 2]);
    });

    it('ne rend aucun repère sans point de valorisation', () => {
        expect(dividendMarks([], [receipt()])).toEqual([]);
    });

    it('ne rend aucun repère sans détachement', () => {
        expect(dividendMarks(weekly, [])).toEqual([]);
    });
});
