export interface CatalogLine {
    id: number;
    name: string;
    ticker: string | null;
    isin: string | null;
    type: string;
    typeLabel: string;
    lastPrice: number | null;
    held: boolean;
    quantity: number | null;
    marketValue: number | null;
}

export interface CatalogTrend {
    assetId: number;
    changePct: number | null;
    points: number[];
}

/** A catalogue line joined with the trend of the selected period. */
export interface CatalogRow extends CatalogLine {
    changePct: number | null;
    points: number[];
}

export type RangeKey = '1M' | '6M' | '1Y' | 'max';

export const rangeOptions: { key: RangeKey; label: string }[] = [
    { key: '1M', label: '1M' },
    { key: '6M', label: '6M' },
    { key: '1Y', label: '1A' },
    { key: 'max', label: 'Max' },
];

export const isRangeKey = (value: string | undefined): value is RangeKey =>
    rangeOptions.some((option) => option.key === value);

export const joinTrends = (lines: CatalogLine[], trends: CatalogTrend[] | undefined): CatalogRow[] => {
    const byAsset = new Map((trends ?? []).map((trend) => [trend.assetId, trend]));

    return lines.map((line) => ({
        ...line,
        changePct: byAsset.get(line.id)?.changePct ?? null,
        points: byAsset.get(line.id)?.points ?? [],
    }));
};
