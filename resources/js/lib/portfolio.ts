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
    hasMore: boolean;
}
