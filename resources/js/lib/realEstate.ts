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
    byCategory: Record<string, number>;
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
