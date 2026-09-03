import { relativeBarWidth } from '@/lib/bars';

/**
 * Une classe d'actif du résumé : ce qu'elle est, ce qu'elle vaut, et l'écart à sa mise. `gainPct`
 * est nul quand rien n'a été investi.
 */
export interface AssetClass {
    key: string;
    label: string;
    /** `null` pour une classe sans page dédiée : sa ligne se lit alors sans être cliquable. */
    href: string | null;
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

/** Une seule classe d'actif dans le temps, sur sa propre grille de labels. */
export interface ClassSeries {
    labels: string[];
    value: number[];
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

/** Une enveloppe de détention : ce qu'elle tient, et les règles qu'elle déclare. */
export interface WealthAccount {
    walletId: number;
    walletName: string;
    /** Établissement qui tient le compte ; `null` quand il n'est pas renseigné. */
    broker: string | null;
    accountType: string;
    accountTypeLabel: string;
    marketValue: number;
    /** Coût de revient des positions du compte : le « Investi » de son en-tête. */
    cost: number;
    /** Gain latent, celui des positions encore ouvertes. */
    gain: number;
    gainPct: number | null;
    /** Gain déjà encaissé par les ventes du compte, positions soldées comprises. */
    realizedGain: number;
    /** Ancienneté en années ; `null` quand la date d'ouverture est inconnue. */
    ageInYears: number | null;
    /** Années de détention avant le régime favorable ; `null` quand l'enveloppe n'en a pas. */
    maturityYears: number | null;
    taxRegimeLabel: string;
    /** Actifs que l'enveloppe n'admet pas. Vide dans le cas normal. */
    ineligibleAssetNames: string[];
    /** Le compte espèces réel de l'enveloppe, à aujourd'hui. Jamais nul : `0` sans mouvement. */
    cashBalance: number;
}

/**
 * Accord du singulier : « 1 an », jamais « 1 ans ». Partagée par la carte repliée du tableau de
 * bord et l'en-tête déplié d'une enveloppe — les deux disent la même ancienneté.
 */
export const years = (n: number): string => (n === 1 ? '1 an' : `${n} ans`);

/**
 * Le seuil fiscal d'une enveloppe, franchi ou encore à venir ; `null` sans ancienneté connue, ou
 * sans seuil à atteindre. Même règle partagée que `years` : deux écrans qui lisent le même compte
 * ne peuvent pas dire deux choses différentes.
 */
export const walletMaturityLabel = (account: Pick<WealthAccount, 'ageInYears' | 'maturityYears'>): string | null => {
    const { ageInYears, maturityYears } = account;

    if (maturityYears === null || ageInYears === null) {
        return null;
    }

    return ageInYears >= maturityYears
        ? `Seuil de ${years(maturityYears)} franchi`
        : `Seuil de ${years(maturityYears)} dans ${years(maturityYears - ageInYears)}`;
};

/** Une classe d'actif dans une enveloppe : ce qu'elle y vaut, et la part qu'elle y pèse. */
export interface WalletClassSlice {
    key: string;
    label: string;
    value: number;
    /** Part de l'enveloppe, en pourcentage. */
    share: number;
}
