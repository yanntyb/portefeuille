import { eur, signedEur } from '@/lib/format';
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

/**
 * Pied de l'en-tête, calqué sur celui d'un instrument : le patrimoine net porte le grand chiffre,
 * ces quatre repères disent d'où il vient. Le cash-flow est mensualisé pour se lire à l'échelle
 * d'une quittance, et coloré comme un gain — un bien qui coûte chaque mois doit le montrer.
 */
export const propertyHeroMeta = (property: PropertyDetail): HeroMetaEntry[] => {
    const monthlyCashFlow = property.metrics.annualCashFlow / 12;

    return [
        { label: 'Valeur estimée', value: eur(property.currentValue) },
        { label: 'Restant dû', value: eur(property.loan?.remainingPrincipal ?? 0) },
        { label: 'Investi', value: eur(acquisitionCostOf(property)) },
        { label: 'Cash-flow/mois', value: signedEur(monthlyCashFlow), gain: monthlyCashFlow },
    ];
};

export interface CashFlowYear {
    year: string;
    /** Net cumulé de l'année : ce que le bien a laissé en poche, ou coûté. */
    net: number;
    months: MonthlyCashFlow[];
}

/**
 * Groupe le cash-flow par année, la plus récente en tête et, dans chaque groupe, le mois le plus
 * récent d'abord : la fenêtre glissante arrive dans l'ordre chronologique, l'inverse de la lecture.
 */
export const cashFlowYears = (flows: MonthlyCashFlow[]): CashFlowYear[] => {
    const groups = new Map<string, MonthlyCashFlow[]>();

    for (const flow of flows) {
        const year = flow.month.slice(0, 4);
        groups.set(year, [flow, ...(groups.get(year) ?? [])]);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => right.localeCompare(left))
        .map(([year, months]) => ({
            year,
            net: months.reduce((net, month) => net + month.net, 0),
            months,
        }));
};

export interface RentYear {
    year: string;
    received: number;
    expected: number;
    months: RentMonth[];
}

/** Groupe les loyers par année, l'ordre reçu — le plus récent d'abord — conservé dans chaque groupe. */
export const rentYears = (months: RentMonth[]): RentYear[] => {
    const groups = new Map<string, RentMonth[]>();

    for (const month of months) {
        const year = month.month.slice(0, 4);
        groups.set(year, [...(groups.get(year) ?? []), month]);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => right.localeCompare(left))
        .map(([year, yearMonths]) => ({
            year,
            received: yearMonths.reduce((total, month) => total + month.effective, 0),
            expected: yearMonths.reduce((total, month) => total + month.expected, 0),
            months: yearMonths,
        }));
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
