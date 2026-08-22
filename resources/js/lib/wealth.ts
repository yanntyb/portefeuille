/**
 * Une classe d'actif du résumé : ce qu'elle est, ce qu'elle vaut, et l'écart à sa mise. `gainPct`
 * est nul quand rien n'a été investi.
 */
export interface AssetClass {
    key: string;
    label: string;
    href: string;
    value: number;
    invested: number;
    gain: number;
    gainPct: number | null;
}

/** Les classes arrivent dans l'ordre du registre : c'est celui des lignes et des bandes du graphe. */
export interface WealthOverview {
    totalValue: number;
    totalInvested: number;
    totalGain: number;
    totalGainPct: number | null;
    classes: AssetClass[];
}

/** Une classe reportée sur la grille commune. */
export interface ClassValues {
    key: string;
    label: string;
    values: number[];
}

/** Toutes les séries partagent `labels` : le graphe empile leurs indices un à un. */
export interface WealthSeries {
    labels: string[];
    classes: ClassValues[];
    invested: number[];
}

export interface IncomeOrigin {
    label: string;
    amount: number;
}

export interface WealthIncome {
    monthlyTotal: number;
    origins: IncomeOrigin[];
}
