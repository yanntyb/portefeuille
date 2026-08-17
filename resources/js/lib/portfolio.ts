import { largestOf, relativeBarWidth } from '@/lib/bars';

export interface HoldingLine {
    assetId: number;
    assetName: string;
    ticker: string | null;
    type: string;
    typeLabel: string;
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
    totalGainPct: number;
    holdings: HoldingLine[];
}

export interface EvolutionSeries {
    labels: string[];
    perAsset: { assetId: number; name: string; value: number[]; invested: number[] }[];
}

/** Opacité de la barre la plus pâle : en dessous, elle disparaît du fond dans les deux thèmes. */
const FAINTEST_BAR_OPACITY = 0.35;

export interface HoldingWeight {
    line: HoldingLine;
    /** Part du portefeuille, en pourcentage. */
    share: number;
    /** Largeur de la barre en pourcentage CSS, mesurée contre la position la plus lourde. */
    barWidth: string;
    opacity: number;
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

    return visible.map((line: HoldingLine, index: number): HoldingWeight => ({
        line,
        share: shareOf(line),
        barWidth: relativeBarWidth(shareOf(line), largest),
        /** Un seul dégradé monotone sur les lignes rendues, pas sur l'ensemble. */
        opacity: 1 - (index / Math.max(1, visible.length - 1)) * (1 - FAINTEST_BAR_OPACITY),
    }));
};
