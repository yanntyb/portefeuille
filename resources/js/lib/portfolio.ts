import { largestOf, relativeBarWidth } from '@/lib/bars';

export interface HoldingLine {
    assetId: number;
    assetName: string;
    ticker: string | null;
    type: string;
    typeLabel: string;
    assetClass: string;
    assetClassLabel: string;
    walletId: number;
    walletName: string;
    accountType: string;
    accountTypeLabel: string;
    quantity: number;
    avgCost: number | null;
    lastPrice: number | null;
    marketValue: number | null;
    gain: number | null;
    gainPct: number | null;
}

export interface PortfolioOverview {
    totalValue: number;
    totalCost: number;
    totalGain: number;
    totalGainPct: number | null;
    /** Gain déjà encaissé sur l'exposition, ventes comprises sur les actifs soldés. */
    totalRealizedGain: number;
    /**
     * Le cash de l'utilisateur, toutes enveloppes confondues : jamais ventilé par exposition, il
     * vaut le même montant sur chaque page d'exposition. C'est ce qui reste à replacer.
     */
    cash: number;
    holdings: HoldingLine[];
}

export interface EvolutionSeries {
    labels: string[];
    perAsset: { assetId: number; name: string; value: number[]; invested: number[] }[];
}

export interface HoldingWeight {
    line: HoldingLine;
    /** Part du portefeuille, en pourcentage. */
    share: number;
    /** Largeur de la barre en pourcentage CSS, mesurée contre la position la plus lourde. */
    barWidth: string;
}

/**
 * Les lignes du portefeuille, triées et pondérées. La limite ne coupe que le rendu : le total, les
 * parts et l'échelle des barres restent calculés sur l'ensemble, sinon une position de 1 200 € dans
 * un portefeuille de 7 800 € afficherait le poids qu'elle a parmi les seules lignes visibles.
 */
export const holdingWeights = (holdings: HoldingLine[], limit?: number): HoldingWeight[] => {
    const sorted = [...holdings].sort(
        (left: HoldingLine, right: HoldingLine): number => (right.marketValue ?? 0) - (left.marketValue ?? 0),
    );

    const total = sorted.reduce((sum: number, line: HoldingLine): number => sum + (line.marketValue ?? 0), 0);
    const shareOf = (line: HoldingLine): number => (total > 0 ? ((line.marketValue ?? 0) / total) * 100 : 0);
    const largest = largestOf(sorted.map(shareOf));

    const visible = limit === undefined ? sorted : sorted.slice(0, limit);

    return visible.map((line: HoldingLine): HoldingWeight => ({
        line,
        share: shareOf(line),
        barWidth: relativeBarWidth(shareOf(line), largest),
    }));
};
