import { eur, fractionPct, frMonthYear, signedEur } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';
import type { SectorBreakdownRow } from '@/lib/sector';

export interface PropertyOverview {
    id: number;
    name: string;
    currentValue: number;
    remainingPrincipal: number;
    netWorth: number;
    monthlyCashFlow: number;
}

export interface RealEstateOverview {
    properties: PropertyOverview[];
    totalValue: number;
    totalRemaining: number;
    totalNetWorth: number;
}

/**
 * Indicateurs de rentabilité d'un bien locatif, tous avant impôt. `grossYield`, `netYield`,
 * `cashOnCash` et `ltv` sont des ratios en fraction (0,0655 = 6,55 %), pas des points de
 * pourcentage : les mettre à l'échelle avant de les passer à `pct()`. Nuls quand leur
 * dénominateur ne s'y prête pas (coût d'acquisition, apport ou valeur actuelle nuls ou négatifs).
 */
export interface PropertyMetrics {
    grossYield: number | null;
    netYield: number | null;
    annualCashFlow: number;
    cashOnCash: number | null;
    ltv: number | null;
}

export interface MonthlyCashFlow {
    month: string;
    rents: number;
    expenses: number;
    loanPayment: number;
    net: number;
}

export interface RentMonth {
    month: string;
    expected: number;
    effective: number;
}

export interface ExpenseYear {
    year: number;
    byCategory: { category: string; label: string; amount: number }[];
    total: number;
}

export interface LoanSummary {
    principal: number;
    annualRate: number;
    termMonths: number;
    startDate: string;
    monthlyInsurance: number;
    monthlyPayment: number;
    remainingPrincipal: number;
    totalCost: number;
    /** Mois de la dernière échéance. */
    endDate: string;
    monthsPaid: number;
    principalRepaid: number;
    interestPaid: number;
    interestRemaining: number;
}

export interface AmortizationLine {
    month: string;
    payment: number;
    interest: number;
    principal: number;
    insurance: number;
    remaining: number;
}

export interface PropertyDetail {
    id: number;
    name: string;
    address: string | null;
    acquisitionDate: string;
    acquisitionPrice: number;
    acquisitionFees: number;
    currentValue: number;
    netWorth: number;
    metrics: PropertyMetrics;
    monthlyCashFlows: MonthlyCashFlow[];
    rentHistory: RentMonth[];
    expenseYears: ExpenseYear[];
    loan: LoanSummary | null;
}

/** Étiquette d'un mois de loyer : plein, partiel, impayé ou vacance. */
export type RentMonthStatus = 'plein' | 'partiel' | 'impayé' | 'vacance';

export const rentMonthStatus = (month: RentMonth): RentMonthStatus => {
    if (month.expected === 0) {
        return 'vacance';
    }
    if (month.effective === 0) {
        return 'impayé';
    }
    return month.effective < month.expected ? 'partiel' : 'plein';
};

/** Ce que l'achat a réellement coûté : prix payé plus frais de notaire et d'agence. */
export const acquisitionCostOf = (property: PropertyDetail): number =>
    property.acquisitionPrice + property.acquisitionFees;

/** Plus-value latente du bien : sa valeur estimée moins son coût d'acquisition. */
export const capitalGainOf = (property: PropertyDetail): number =>
    property.currentValue - acquisitionCostOf(property);

/**
 * Plus-value en points de pourcentage, à l'échelle attendue par `pct()` — et non en fraction
 * comme les ratios de `PropertyMetrics`. Nulle sans coût d'acquisition : aucun rapport à établir.
 */
export const capitalGainPctOf = (property: PropertyDetail): number | null => {
    const cost = acquisitionCostOf(property);

    return cost <= 0 ? null : (capitalGainOf(property) / cost) * 100;
};

/** Découpage de la valeur estimée : ce qui est déjà à soi, et ce qui reste à la banque. */
export interface EquitySplit {
    equity: number;
    debt: number;
    /** Part du propriétaire en pourcentage de la valeur estimée, plancher à zéro. */
    equityShare: number;
    /** Vrai quand le restant dû dépasse la valeur estimée : le bien ne couvre plus son prêt. */
    isUnderwater: boolean;
}

/**
 * Découpe la valeur estimée en « à moi » / « à la banque ». Nul sans prêt — un bien détenu en
 * propre n'a rien à partager, une barre pleine ne dirait rien — et nul sans valeur estimée : il n'y
 * a alors pas de total à découper.
 */
export const equitySplitOf = (property: PropertyDetail): EquitySplit | null => {
    if (property.loan === null || property.currentValue <= 0) {
        return null;
    }

    const isUnderwater = property.netWorth < 0;

    return {
        equity: isUnderwater ? 0 : property.netWorth,
        debt: property.loan.remainingPrincipal,
        equityShare: isUnderwater ? 0 : (property.netWorth / property.currentValue) * 100,
        isUnderwater,
    };
};

/**
 * Pied de l'en-tête, calqué sur celui d'un instrument : le patrimoine net porte le grand chiffre,
 * la barre patrimoine dit déjà la valeur estimée et le restant dû, ces deux repères disent ce
 * qu'elle ne montre pas. Le cash-flow est mensualisé pour se lire à l'échelle d'une quittance, et
 * coloré comme un gain — un bien qui coûte chaque mois doit le montrer.
 */
export const propertyHeroMeta = (property: PropertyDetail): HeroMetaEntry[] => {
    const monthlyCashFlow = property.metrics.annualCashFlow / 12;

    return [
        { label: 'Investi', value: eur(acquisitionCostOf(property)) },
        { label: 'Cash-flow/mois', value: signedEur(monthlyCashFlow), gain: monthlyCashFlow },
    ];
};

/** Un mois de la liste « Revenus & charges » : ce qui est entré, ce qui est sorti, ce qui reste. */
export interface IncomeMonth {
    month: string;
    rents: number;
    /** Loyer attendu du mois, nul quand aucun bail ne le couvre. */
    expected: number;
    expenses: number;
    loanPayment: number;
    net: number;
    /** Nul quand le mois précède l'historique des loyers : rien à dire de ce qui n'est pas connu. */
    status: RentMonthStatus | null;
}

export interface IncomeYear {
    year: string;
    /** Net cumulé de l'année : ce que le bien a laissé en poche, ou coûté. */
    net: number;
    months: IncomeMonth[];
    /** Ventilation des charges de l'année, en lignes à barres. */
    expenseRows: SectorBreakdownRow[];
    expenseTotal: number;
}

/**
 * Une seule liste pour les loyers, les charges et l'échéance : trois découpages du même mois se
 * lisaient en trois accordéons jumeaux. Les années descendent de la plus récente, les mois aussi —
 * la fenêtre glissante arrive dans l'ordre chronologique, l'inverse de la lecture.
 *
 * Une année qui n'a que des charges garde sa ligne : des travaux avant le premier bail sortent de
 * la fenêtre du cash-flow, et son net vaut alors leur total en négatif. La ventilation reste celle
 * de l'année entière, même quand la fenêtre n'en couvre qu'une partie : c'est le même total que la
 * déclaration de revenus, pas la somme des mois affichés.
 */
export const incomeYears = (
    flows: MonthlyCashFlow[],
    rents: RentMonth[],
    expenseYears: ExpenseYear[],
): IncomeYear[] => {
    const expectedByMonth = new Map<string, number>(rents.map((month) => [month.month, month.expected]));
    const expensesByYear = new Map<string, ExpenseYear>(
        expenseYears.map((year) => [String(year.year), year]),
    );

    const monthsByYear = new Map<string, IncomeMonth[]>();

    for (const flow of flows) {
        const year = flow.month.slice(0, 4);
        const expected = expectedByMonth.get(flow.month) ?? 0;
        const month: IncomeMonth = {
            month: flow.month,
            rents: flow.rents,
            expected,
            expenses: flow.expenses,
            loanPayment: flow.loanPayment,
            net: flow.net,
            status: expectedByMonth.has(flow.month)
                ? rentMonthStatus({ month: flow.month, expected, effective: flow.rents })
                : null,
        };

        monthsByYear.set(year, [month, ...(monthsByYear.get(year) ?? [])]);
    }

    const years = [...new Set([...monthsByYear.keys(), ...expensesByYear.keys()])].sort((left, right) =>
        right.localeCompare(left),
    );

    return years.map((year) => {
        const months = monthsByYear.get(year) ?? [];
        const expenses = expensesByYear.get(year);

        return {
            year,
            net:
                months.length > 0
                    ? months.reduce((net, month) => net + month.net, 0)
                    : -(expenses?.total ?? 0),
            months,
            expenseRows: expenses === undefined ? [] : expenseRows(expenses),
            expenseTotal: expenses?.total ?? 0,
        };
    });
};

/**
 * Ventilation d'une année en lignes à barres, celles de la répartition sectorielle : même
 * vocabulaire visuel pour deux découpages d'un même total.
 */
export const expenseRows = (year: ExpenseYear): SectorBreakdownRow[] =>
    year.byCategory.map((entry) => ({
        label: entry.label,
        share: year.total === 0 ? 0 : (entry.amount / year.total) * 100,
        amount: entry.amount,
    }));

/**
 * Repères d'un prêt en cours : d'abord ses conditions, puis où il en est. Mêmes paires
 * libellé/valeur que l'en-tête d'un bien, donc la même grille à deux colonnes les affiche.
 */
export const loanProgress = (loan: LoanSummary): HeroMetaEntry[] => {
    /** Part du capital, et non des échéances : les premières mensualités remboursent peu. */
    const repaidShare = loan.principal <= 0 ? 0 : (loan.principalRepaid / loan.principal) * 100;

    return [
        { label: 'Emprunté', value: eur(loan.principal) },
        { label: 'Taux', value: fractionPct(loan.annualRate) },
        { label: 'Mensualité', value: eur(loan.monthlyPayment) },
        { label: 'Fin', value: frMonthYear(loan.endDate) },
        { label: 'Payé', value: `${loan.monthsPaid}/${loan.termMonths} mois` },
        { label: 'Capital remboursé', value: `${eur(loan.principalRepaid, 0)} · ${Math.round(repaidShare)} %` },
        { label: 'Restant dû', value: eur(loan.remainingPrincipal) },
        { label: 'Coût total', value: eur(loan.totalCost) },
        { label: 'Intérêts payés', value: eur(loan.interestPaid) },
        { label: 'Intérêts à venir', value: eur(loan.interestRemaining) },
    ];
};

export interface LoanYear {
    year: string;
    payments: number;
    interest: number;
    principal: number;
    /** Capital restant dû après la dernière échéance de l'année. */
    remaining: number;
    isCurrent: boolean;
    months: AmortizationLine[];
}

export interface LoanFuture {
    payments: number;
    interest: number;
    principal: number;
    /** Détail année par année, du plus proche au plus lointain. */
    years: LoanYear[];
}

export interface LoanSchedule {
    /** Années échues et année en cours, la plus récente en tête. */
    past: LoanYear[];
    /** Cumul des années suivantes, ou `null` quand la dernière échéance est passée. */
    future: LoanFuture | null;
}

/** Une année de l'échéancier : les lignes arrivent dans l'ordre chronologique, il est conservé. */
const loanYearOf = (year: string, months: AmortizationLine[], currentYear: number): LoanYear => ({
    year,
    payments: months.reduce((total, month) => total + month.payment, 0),
    interest: months.reduce((total, month) => total + month.interest, 0),
    principal: months.reduce((total, month) => total + month.principal, 0),
    remaining: months[months.length - 1].remaining,
    isCurrent: year === String(currentYear),
    months,
});

/**
 * L'échéancier replié par année : ce qui a été payé se lit ligne par ligne, ce qui reste dû tient
 * en une seule ligne cumulée — sur vingt ans, les années à venir écraseraient sinon le passé.
 */
export const loanYears = (lines: AmortizationLine[], currentYear: number): LoanSchedule => {
    const groups = new Map<string, AmortizationLine[]>();

    for (const line of lines) {
        const year = line.month.slice(0, 4);
        groups.set(year, [...(groups.get(year) ?? []), line]);
    }

    const years = [...groups.entries()].map(([year, months]) => loanYearOf(year, months, currentYear));

    const past = years
        .filter((year) => Number(year.year) <= currentYear)
        .sort((left, right) => right.year.localeCompare(left.year));

    const future = years
        .filter((year) => Number(year.year) > currentYear)
        .sort((left, right) => left.year.localeCompare(right.year));

    return {
        past,
        future: future.length === 0 ? null : {
            payments: future.reduce((total, year) => total + year.payments, 0),
            interest: future.reduce((total, year) => total + year.interest, 0),
            principal: future.reduce((total, year) => total + year.principal, 0),
            years: future,
        },
    };
};
