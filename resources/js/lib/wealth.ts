/** Une classe d'actif du résumé. `gainPct` est nul quand rien n'a été investi. */
export interface AssetClass {
    value: number;
    invested: number;
    gain: number;
    gainPct: number | null;
}

export interface WealthOverview {
    totalValue: number;
    totalInvested: number;
    totalGain: number;
    totalGainPct: number | null;
    securities: AssetClass;
    realEstate: AssetClass;
}

/** Les trois séries partagent `labels` : le graphe empile leurs indices un à un. */
export interface WealthSeries {
    labels: string[];
    securities: number[];
    realEstate: number[];
    invested: number[];
}

export interface WealthIncome {
    monthlyTotal: number;
    monthlyDividends: number;
    monthlyRentalNet: number;
}
