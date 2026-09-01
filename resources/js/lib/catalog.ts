import { fold } from '@/lib/search';

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

export const joinTrends = (lines: CatalogLine[], trends: CatalogTrend[] | null | undefined): CatalogRow[] => {
    const byAsset = new Map((trends ?? []).map((trend) => [trend.assetId, trend]));

    return lines.map((line) => ({
        ...line,
        changePct: byAsset.get(line.id)?.changePct ?? null,
        points: byAsset.get(line.id)?.points ?? [],
    }));
};

/**
 * Les lignes que la frappe retient : le nom, le ticker et l'ISIN sont trois façons de nommer le
 * même titre, et le catalogue ne saurait dire par laquelle on le cherche. Terme vide, tout passe.
 */
export const filterCatalog = (lines: CatalogLine[], term: string): CatalogLine[] => {
    const needle = fold(term.trim());

    if (needle === '') {
        return lines;
    }

    return lines.filter((line) =>
        [line.name, line.ticker, line.isin].some(
            (field) => field !== null && fold(field).includes(needle),
        ),
    );
};
