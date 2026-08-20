import { largestOf, relativeBarWidth } from '@/lib/bars';

export interface DividendReceipt {
    assetId: number;
    exDate: string;
    quantity: number;
    amountPerShare: number;
    amount: number;
}

export interface AssetDividendHistory {
    receipts: DividendReceipt[];
    totalReceived: number;
    last12Months: number;
    /** Attendu sur les douze prochains mois, extrapolé des détachements récents. */
    estimatedAnnual: number;
    /** Perçu sur douze mois rapporté au coût de la position, en pourcentage. */
    yieldOnCost: number | null;
}

/** Montants indexés par origine de revenu : `dividend` aujourd'hui, un loyer demain. */
export type IncomeBySource = Record<string, number>;

export interface IncomeSummary {
    totalReceived: number;
    last12Months: number;
    /** Attendu sur les douze prochains mois, toutes origines confondues. */
    estimatedAnnual: number;
    bySource: IncomeBySource;
}

export interface AnnualIncome {
    year: number;
    total: number;
    bySource: IncomeBySource;
}

export interface AnnualIncomeBar {
    year: number;
    total: number;
    barWidth: string;
}

/** Une barre par année, mesurée contre la meilleure année et non contre leur somme. */
export const annualIncomeBars = (rows: AnnualIncome[]): AnnualIncomeBar[] => {
    const largest = largestOf(rows.map((row) => row.total));

    return rows.map((row) => ({
        year: row.year,
        total: row.total,
        barWidth: relativeBarWidth(row.total, largest),
    }));
};

export interface DividendYear {
    year: string;
    /** Perçu sur l'année, détachements cumulés. */
    total: number;
    receipts: DividendReceipt[];
}

/** Regroupe les détachements par année, la plus récente en tête, l'ordre reçu conservé dans chaque groupe. */
export const dividendYears = (receipts: DividendReceipt[]): DividendYear[] => {
    const groups = new Map<string, DividendReceipt[]>();

    for (const receipt of receipts) {
        const year = receipt.exDate.slice(0, 4);
        groups.set(year, [...(groups.get(year) ?? []), receipt]);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => right.localeCompare(left))
        .map(([year, yearReceipts]) => ({
            year,
            total: yearReceipts.reduce((total, receipt) => total + receipt.amount, 0),
            receipts: yearReceipts,
        }));
};
