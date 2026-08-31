import { relativeBarWidth } from '@/lib/bars';
import type { NamedTransactionLine } from '@/lib/instrument';

/**
 * Une classe d'actif du résumé : ce qu'elle est, ce qu'elle vaut, et l'écart à sa mise. `gainPct`
 * est nul quand rien n'a été investi.
 */
export interface AssetClass {
    key: string;
    label: string;
    href: string;
    color: string;
    value: number;
    invested: number;
    gain: number;
    gainPct: number | null;
    realizedGain: number;
}

/** Les classes arrivent dans l'ordre du registre : c'est celui des lignes et des bandes du graphe. */
export interface WealthOverview {
    totalValue: number;
    totalInvested: number;
    totalGain: number;
    totalGainPct: number | null;
    totalRealizedGain: number;
    classes: AssetClass[];
}

/** Un secteur du patrimoine : ce qu'il pèse, et la part qu'il occupe. */
export interface WealthSector {
    label: string;
    value: number;
    pct: number;
}

/** Une classe du résumé pesée contre le patrimoine entier. */
export interface AssetClassWeight {
    line: AssetClass;
    /** Part du patrimoine, en pourcentage. */
    share: number;
    /** Largeur de la barre en pourcentage CSS. */
    barWidth: string;
}

/**
 * Les classes d'actif pesées, dans l'ordre du registre : la répartition se lit comme celle du
 * serveur, jamais comme un tri d'ici. Une classe sans valeur est écartée — une ligne à zéro
 * n'apprend rien.
 *
 * La part se rapporte à `totalValue`, pas à la somme des lignes gardées : c'est le patrimoine
 * entier qui fait le tout. Un total nul ou négatif ne fait pas de tout auquel se rapporter, la part
 * vaut alors zéro.
 */
export const assetClassWeights = (overview: WealthOverview): AssetClassWeight[] => {
    const total = overview.totalValue;
    const shareOf = (line: AssetClass): number => (total > 0 ? (line.value / total) * 100 : 0);

    return overview.classes
        .filter((line: AssetClass): boolean => line.value !== 0)
        .map((line: AssetClass): AssetClassWeight => ({
            line,
            share: shareOf(line),
            /**
             * Mesurée contre le tout — 100 — et non contre la plus grosse classe : les parts
             * s'additionnent à ce tout, une barre pleine mentirait sur le poids de la plus lourde.
             * Le plancher à zéro évite une largeur CSS négative sur une classe en négatif (un bien
             * financé à plus de 100 %).
             */
            barWidth: relativeBarWidth(Math.max(0, shareOf(line)), 100),
        }));
};

/** Une classe reportée sur la grille commune. */
export interface ClassValues {
    key: string;
    label: string;
    color: string;
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

/**
 * Une opération du patrimoine. Rien de propre au patrimoine : la page d'exposition sert les mêmes
 * lignes, d'où le type partagé — le nom reste, c'est celui que le tableau de bord emploie.
 */
export type WealthTransactionLine = NamedTransactionLine;

/** Une enveloppe de détention : ce qu'elle tient, et les règles qu'elle déclare. */
export interface WealthAccount {
    walletId: number;
    walletName: string;
    /** Établissement qui tient le compte ; `null` quand il n'est pas renseigné. */
    broker: string | null;
    accountType: string;
    accountTypeLabel: string;
    marketValue: number;
    gain: number;
    gainPct: number | null;
    /** Ancienneté en années ; `null` quand la date d'ouverture est inconnue. */
    ageInYears: number | null;
    /** Années de détention avant le régime favorable ; `null` quand l'enveloppe n'en a pas. */
    maturityYears: number | null;
    taxRegimeLabel: string;
    /** Actifs que l'enveloppe n'admet pas. Vide dans le cas normal. */
    ineligibleAssetNames: string[];
}
