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

/** Les accents ne doivent pas empêcher de retrouver « Société Générale » en tapant « societe ». */
const normalize = (value: string): string =>
    value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

/** La recherche porte sur le nom, le ticker et l'ISIN, les trois façons de nommer un instrument. */
export const filterCatalog = <Row extends CatalogRow>(rows: Row[], query: string): Row[] => {
    const needle = normalize(query.trim());

    if (needle === '') {
        return rows;
    }

    return rows.filter((row) =>
        [row.name, row.ticker, row.isin].some(
            (field) => field !== null && normalize(field).includes(needle),
        ),
    );
};

/**
 * Rang de pertinence d'une ligne pour une recherche : le plus petit passe devant. Un ticker tapé
 * en entier doit remonter avant un nom qui porte les mêmes lettres en son milieu.
 */
export const relevanceRank = (row: Pick<CatalogLine, 'name' | 'ticker'>, query: string): number => {
    const needle = normalize(query.trim());

    if (needle === '') {
        return 2;
    }

    if (row.ticker !== null && normalize(row.ticker).startsWith(needle)) {
        return 0;
    }

    return normalize(row.name).startsWith(needle) ? 1 : 2;
};

export const joinTrends = (lines: CatalogLine[], trends: CatalogTrend[] | undefined): CatalogRow[] => {
    const byAsset = new Map((trends ?? []).map((trend) => [trend.assetId, trend]));

    return lines.map((line) => ({
        ...line,
        changePct: byAsset.get(line.id)?.changePct ?? null,
        points: byAsset.get(line.id)?.points ?? [],
    }));
};

/**
 * L'en-tête du catalogue : combien d'instruments, et combien sont détenus. Se recompte sur la
 * liste filtrée, la recherche devant répondre « 2 instruments » et non « 2 sur 3 ».
 */
export const catalogCount = (rows: CatalogRow[]): string => {
    const held = rows.filter((row: CatalogRow): boolean => row.held).length;
    const instruments = `${rows.length} instrument${rows.length > 1 ? 's' : ''}`;

    return held === 0 ? instruments : `${instruments} · ${held} détenu${held > 1 ? 's' : ''}`;
};
