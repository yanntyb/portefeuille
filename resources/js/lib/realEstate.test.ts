import { describe, expect, it } from 'vitest';
import {
    acquisitionCostOf,
    capitalGainOf,
    capitalGainPctOf,
    cashFlowYears,
    expenseRows,
    propertyHeroMeta,
    rentMonthStatus,
    rentYears,
    type ExpenseYear,
    type LoanSummary,
    type MonthlyCashFlow,
    type PropertyDetail,
    type RentMonth,
} from '@/lib/realEstate';

describe('rentMonthStatus', () => {
    it('labels a full rent', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 500 })).toBe('plein');
    });

    it('labels a partial payment', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 250 })).toBe('partiel');
    });

    it('labels a default', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 0 })).toBe('impayé');
    });

    it('labels vacancy', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 0, effective: 0 })).toBe('vacance');
    });
});

/** Fiche minimale : chaque test ne surcharge que ce qu'il regarde. */
const detail = (overrides: Partial<PropertyDetail> = {}): PropertyDetail => ({
    id: 1,
    name: 'T2 Lyon 7e',
    address: null,
    acquisitionDate: '2020-01-01',
    acquisitionPrice: 100000,
    acquisitionFees: 8000,
    currentValue: 150000,
    netWorth: 100000,
    metrics: { grossYield: null, netYield: null, annualCashFlow: 1200, cashOnCash: null, ltv: null },
    monthlyCashFlows: [],
    rentHistory: [],
    expenseYears: [],
    loan: null,
    ...overrides,
});

const loan = (overrides: Partial<LoanSummary> = {}): LoanSummary => ({
    principal: 80000,
    annualRate: 0.02,
    termMonths: 240,
    startDate: '2020-01-01',
    monthlyInsurance: 10,
    monthlyPayment: 420,
    remainingPrincipal: 50000,
    totalCost: 20800,
    ...overrides,
});

describe('acquisitionCostOf', () => {
    it('additionne le prix et les frais : investi, c\'est tout ce qui est sorti à l\'achat', () => {
        expect(acquisitionCostOf(detail())).toBe(108000);
    });
});

describe('capitalGainOf', () => {
    it('mesure la plus-value contre le coût d\'acquisition, frais compris', () => {
        expect(capitalGainOf(detail())).toBe(42000);
    });

    it('rend une moins-value quand le bien vaut moins que son coût', () => {
        expect(capitalGainOf(detail({ currentValue: 90000 }))).toBe(-18000);
    });
});

describe('capitalGainPctOf', () => {
    it('rend la plus-value en points de pourcentage, prête pour pct()', () => {
        expect(capitalGainPctOf(detail({ currentValue: 129600 }))).toBeCloseTo(20);
    });

    it('rend null sans coût d\'acquisition : aucun pourcentage n\'a de sens', () => {
        expect(capitalGainPctOf(detail({ acquisitionPrice: 0, acquisitionFees: 0 }))).toBeNull();
    });
});

describe('propertyHeroMeta', () => {
    it('donne les quatre repères du bien, le cash-flow mensuel coloré comme un gain', () => {
        const entries = propertyHeroMeta(detail({ loan: loan() }));

        expect(entries.map((entry) => entry.label)).toEqual([
            'Valeur estimée',
            'Restant dû',
            'Investi',
            'Cash-flow/mois',
        ]);
        expect(entries[3].gain).toBe(100);
    });

    it('rend un restant dû nul quand le bien n\'a pas de prêt', () => {
        expect(propertyHeroMeta(detail())[1].value).toContain('0');
    });

    it('colore un cash-flow négatif comme une perte', () => {
        const entries = propertyHeroMeta(detail({ metrics: { ...detail().metrics, annualCashFlow: -2400 } }));

        expect(entries[3].gain).toBe(-200);
    });
});

describe('cashFlowYears', () => {
    const flows: MonthlyCashFlow[] = [
        { month: '2025-11-01', rents: 600, expenses: 100, loanPayment: 400, net: 100 },
        { month: '2025-12-01', rents: 600, expenses: 0, loanPayment: 400, net: 200 },
        { month: '2026-01-01', rents: 600, expenses: 700, loanPayment: 400, net: -500 },
    ];

    it('groupe par année, la plus récente en tête', () => {
        expect(cashFlowYears(flows).map((group) => group.year)).toEqual(['2026', '2025']);
    });

    it('somme le net de l\'année', () => {
        expect(cashFlowYears(flows)[1].net).toBe(300);
    });

    it('range les mois du plus récent au plus ancien dans chaque groupe', () => {
        expect(cashFlowYears(flows)[1].months.map((month) => month.month)).toEqual(['2025-12-01', '2025-11-01']);
    });
});

describe('rentYears', () => {
    const months: RentMonth[] = [
        { month: '2026-01-01', expected: 600, effective: 0 },
        { month: '2025-12-01', expected: 600, effective: 300 },
        { month: '2025-11-01', expected: 600, effective: 600 },
    ];

    it('groupe par année, la plus récente en tête', () => {
        expect(rentYears(months).map((group) => group.year)).toEqual(['2026', '2025']);
    });

    it('somme le perçu et l\'attendu de l\'année', () => {
        const [, previous] = rentYears(months);

        expect(previous.received).toBe(900);
        expect(previous.expected).toBe(1200);
    });

    it('conserve l\'ordre reçu dans chaque groupe, le plus récent d\'abord', () => {
        expect(rentYears(months)[1].months.map((month) => month.month)).toEqual(['2025-12-01', '2025-11-01']);
    });
});

describe('expenseRows', () => {
    const year: ExpenseYear = {
        year: 2025,
        byCategory: [
            { category: 'works', label: 'Travaux', amount: 750 },
            { category: 'tax', label: 'Taxe foncière', amount: 250 },
        ],
        total: 1000,
    };

    it('traduit une ventilation en lignes de barres, part en pourcentage', () => {
        expect(expenseRows(year)).toEqual([
            { label: 'Travaux', share: 75, amount: 750 },
            { label: 'Taxe foncière', share: 25, amount: 250 },
        ]);
    });

    it('rend une part nulle plutôt qu\'une division par zéro sur une année vide', () => {
        expect(expenseRows({ year: 2025, byCategory: [{ category: 'tax', label: 'Taxe foncière', amount: 0 }], total: 0 })[0].share).toBe(0);
    });
});
