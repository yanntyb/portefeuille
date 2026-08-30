export interface Concentration {
    top1: number | null;
    top3: number | null;
    top5: number | null;
    hhi: number | null;
}

export interface Contribution {
    assetId: number;
    assetName: string;
    contribution: number | null;
    weight: number | null;
}

export interface Analysis {
    concentration: Concentration;
    contributions: Contribution[];
}

export interface Drawdown {
    maxDepth: number | null;
    peakLabel: string | null;
    troughLabel: string | null;
    currentDepth: number | null;
}
