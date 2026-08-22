import { describe, expect, it } from 'vitest';
import {
    acquisitionCostOf,
    capitalGainOf,
    capitalGainPctOf,
    equitySplitOf,
    expenseRows,
    incomeYears,
    loanProgress,
    loanYears,
    propertyHeroMeta,
    propertyRows,
    rentMonthStatus,
    type AmortizationLine,
    type ExpenseYear,
    type LoanSummary,
    type MonthlyCashFlow,
    type PropertyDetail,
    type PropertyOverview,
    type RentMonth,
} from '@/lib/realEstate';

const overviewLine = (overrides: Partial<PropertyOverview> = {}): PropertyOverview => ({
    id: 1,
    name: 'T2 Lyon 7e',
    currentValue: 150000,
    remainingPrincipal: 0,
    netWorth: 150000,
    monthlyCashFlow: 500,
    invested: 100000,
    ...overrides,
});

describe('propertyRows', () => {
    it('ranks the properties by net worth and weighs each one against the portfolio', () => {
        const rows = propertyRows([
            overviewLine({ id: 1, netWorth: 50000 }),
            overviewLine({ id: 2, netWorth: 150000 }),
        ]);

        expect(rows.map((row) => row.id)).toEqual([2, 1]);
        expect(rows[0].share).toBe(75);
        expect(rows[0].barWidth).toBe('100%');
        expect(rows[1].barWidth).toBe(`${(25 / 75) * 100}%`);
    });

    it('reports the gain against the cash paid out of pocket', () => {
        const rows = propertyRows([overviewLine({ netWorth: 150000, invested: 100000 })]);

        expect(rows[0].gain).toBe(50000);
        expect(rows[0].gainPct).toBe(50);
    });

    it('leaves the gain share empty when nothing was paid out of pocket', () => {
        const rows = propertyRows([overviewLine({ invested: 0 })]);

        expect(rows[0].gainPct).toBeNull();
    });
});

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
    endDate: '2040-01-01',
    monthsPaid: 24,
    principalRepaid: 30000,
    interestPaid: 2000,
    interestRemaining: 18800,
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

describe('equitySplitOf', () => {
    it('découpe la valeur estimée entre le propriétaire et la banque', () => {
        const split = equitySplitOf(
            detail({ currentValue: 150000, netWorth: 100000, loan: loan({ remainingPrincipal: 50000 }) }),
        );

        expect(split?.equity).toBe(100000);
        expect(split?.debt).toBe(50000);
        expect(split?.equityShare).toBeCloseTo(66.7, 1);
        expect(split?.isUnderwater).toBe(false);
    });

    it('ne découpe rien sans prêt : le bien est entier, la barre ne dirait rien', () => {
        expect(equitySplitOf(detail())).toBeNull();
    });

    it('ne découpe rien sans valeur estimée : aucun total à partager', () => {
        expect(equitySplitOf(detail({ currentValue: 0, loan: loan() }))).toBeNull();
    });

    it('ne laisse aucune part au propriétaire quand le restant dû dépasse la valeur', () => {
        const split = equitySplitOf(
            detail({ currentValue: 100000, netWorth: -20000, loan: loan({ remainingPrincipal: 120000 }) }),
        );

        expect(split?.equityShare).toBe(0);
        expect(split?.equity).toBe(0);
        expect(split?.debt).toBe(120000);
        expect(split?.isUnderwater).toBe(true);
    });
});

describe('propertyHeroMeta', () => {
    it('donne les deux repères que la barre patrimoine ne porte pas', () => {
        const entries = propertyHeroMeta(detail({ loan: loan() }));

        expect(entries.map((entry) => entry.label)).toEqual(['Investi', 'Cash-flow/mois']);
        expect(entries[1].gain).toBe(100);
    });

    it('colore un cash-flow négatif comme une perte', () => {
        const entries = propertyHeroMeta(detail({ metrics: { ...detail().metrics, annualCashFlow: -2400 } }));

        expect(entries[1].gain).toBe(-200);
    });
});

describe('incomeYears', () => {
    const flows: MonthlyCashFlow[] = [
        { month: '2025-11-01', rents: 600, expenses: 100, loanPayment: 400, net: 100 },
        { month: '2025-12-01', rents: 600, expenses: 0, loanPayment: 400, net: 200 },
        { month: '2026-01-01', rents: 300, expenses: 700, loanPayment: 400, net: -800 },
    ];

    const rents: RentMonth[] = [
        { month: '2026-01-01', expected: 600, effective: 300 },
        { month: '2025-12-01', expected: 600, effective: 600 },
        { month: '2025-11-01', expected: 600, effective: 600 },
    ];

    const expenses: ExpenseYear[] = [
        { year: 2026, byCategory: [{ category: 'works', label: 'Travaux', amount: 700 }], total: 700 },
        { year: 2025, byCategory: [{ category: 'tax', label: 'Taxe foncière', amount: 100 }], total: 100 },
    ];

    it('groupe par année, la plus récente en tête', () => {
        expect(incomeYears(flows, rents, expenses).map((group) => group.year)).toEqual(['2026', '2025']);
    });

    it('somme le net de l\'année', () => {
        expect(incomeYears(flows, rents, expenses)[1].net).toBe(300);
    });

    it('range les mois du plus récent au plus ancien dans chaque groupe', () => {
        expect(incomeYears(flows, rents, expenses)[1].months.map((month) => month.month)).toEqual([
            '2025-12-01',
            '2025-11-01',
        ]);
    });

    it('étiquette chaque mois d\'après le loyer attendu', () => {
        const [current, previous] = incomeYears(flows, rents, expenses);

        expect(current.months[0].status).toBe('partiel');
        expect(current.months[0].expected).toBe(600);
        expect(previous.months[0].status).toBe('plein');
    });

    it('laisse un mois sans loyer connu sans étiquette', () => {
        const [year] = incomeYears(flows, [], expenses);

        expect(year.months[0].status).toBeNull();
        expect(year.months[0].expected).toBe(0);
    });

    it('porte la ventilation des charges de l\'année', () => {
        const [year] = incomeYears(flows, rents, expenses);

        expect(year.expenseTotal).toBe(700);
        expect(year.expenseRows).toEqual([{ label: 'Travaux', share: 100, amount: 700 }]);
    });

    it('garde une année qui n\'a que des charges, son net étant leur total en négatif', () => {
        const older: ExpenseYear = {
            year: 2024,
            byCategory: [{ category: 'works', label: 'Travaux', amount: 5000 }],
            total: 5000,
        };

        const years = incomeYears(flows, rents, [...expenses, older]);

        expect(years.map((group) => group.year)).toEqual(['2026', '2025', '2024']);
        expect(years[2].months).toEqual([]);
        expect(years[2].net).toBe(-5000);
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

describe('loanProgress', () => {
    const summary = loan({
        principal: 80000,
        annualRate: 0.02,
        termMonths: 240,
        monthlyPayment: 405,
        remainingPrincipal: 74210,
        totalCost: 17200,
        endDate: '2044-03-01',
        monthsPaid: 28,
        principalRepaid: 5790,
        interestPaid: 3100,
        interestRemaining: 14100,
    });

    it('énonce les conditions du prêt puis la situation, dix repères en tout', () => {
        expect(loanProgress(summary).map((entry) => entry.label)).toEqual([
            'Emprunté',
            'Taux',
            'Mensualité',
            'Fin',
            'Payé',
            'Capital remboursé',
            'Restant dû',
            'Coût total',
            'Intérêts payés',
            'Intérêts à venir',
        ]);
    });

    it('compte les échéances réglées sur la durée totale', () => {
        expect(loanProgress(summary)[4].value).toBe('28/240 mois');
    });

    it('rapporte le capital remboursé au capital emprunté, pas aux échéances', () => {
        // 5 790 € sur 80 000 € : 7 %, quand 28 échéances sur 240 en feraient 12.
        expect(loanProgress(summary)[5].value).toContain('7 %');
    });

    it('rend une part nulle plutôt qu\'une division par zéro sur un capital nul', () => {
        expect(loanProgress(loan({ principal: 0, principalRepaid: 0 }))[5].value).toContain('0 %');
    });
});

describe('loanYears', () => {
    const line = (month: string, remaining: number): AmortizationLine => ({
        month,
        payment: 100,
        interest: 10,
        principal: 90,
        insurance: 0,
        remaining,
    });

    const lines: AmortizationLine[] = [
        line('2025-11-01', 400),
        line('2025-12-01', 310),
        line('2026-01-01', 220),
        line('2026-02-01', 130),
        line('2027-01-01', 0),
    ];

    it('groupe les années échues et en cours, la plus récente en tête', () => {
        expect(loanYears(lines, 2026).past.map((year) => year.year)).toEqual(['2026', '2025']);
    });

    it('somme mensualités, intérêts et capital de l\'année, et retient son dernier restant dû', () => {
        const [, previous] = loanYears(lines, 2026).past;

        expect(previous.payments).toBe(200);
        expect(previous.interest).toBe(20);
        expect(previous.principal).toBe(180);
        expect(previous.remaining).toBe(310);
        expect(previous.months).toHaveLength(2);
    });

    it('marque la seule année en cours', () => {
        expect(loanYears(lines, 2026).past.map((year) => year.isCurrent)).toEqual([true, false]);
    });

    it('replie les années suivantes en un seul cumul, détaillé du plus ancien au plus récent', () => {
        const future = loanYears(lines, 2026).future;

        expect(future?.payments).toBe(100);
        expect(future?.interest).toBe(10);
        expect(future?.principal).toBe(90);
        expect(future?.years.map((year) => year.year)).toEqual(['2027']);
    });

    it('rend un futur nul quand la dernière échéance est passée', () => {
        expect(loanYears(lines, 2027).future).toBeNull();
    });

    it('rend un échéancier vide sans lignes', () => {
        expect(loanYears([], 2026)).toEqual({ past: [], future: null });
    });
});
