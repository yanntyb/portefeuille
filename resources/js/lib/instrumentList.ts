import { filterCatalog, joinTrends, type CatalogLine, type CatalogRow, type CatalogTrend } from '@/lib/catalog';
import { holdingWeights, type HoldingLine, type HoldingWeight } from '@/lib/portfolio';

/** Une ligne de la liste unique : un instrument du catalogue, enrichi de la position quand il y en a une. */
export interface InstrumentRow extends CatalogRow {
    /** Gain latent de la position, nul sur un instrument non détenu. */
    gain: number | null;
    gainPct: number | null;
    /** Part du portefeuille en pourcentage, nulle sur un instrument non détenu. */
    share: number | null;
    /** Largeur CSS de la barre de poids, nulle sur un instrument non détenu. */
    barWidth: string | null;
    opacity: number | null;
}

/** Une position que le catalogue ne connaît pas encore, ramenée à la forme d'une ligne de catalogue. */
const asCatalogLine = (weight: HoldingWeight): CatalogLine => ({
    id: weight.line.assetId,
    name: weight.line.assetName,
    ticker: weight.line.ticker,
    isin: null,
    type: weight.line.type,
    typeLabel: weight.line.typeLabel,
    lastPrice: weight.line.lastPrice,
    held: true,
    quantity: weight.line.quantity,
    marketValue: weight.line.marketValue,
});

/**
 * Joint le catalogue, les positions et les tendances de la période. Le catalogue peut encore être
 * différé : les positions suffisent alors à peupler la liste, et les lignes manquantes arrivent
 * ensuite sans que la liste ait été vide entre-temps.
 */
export const mergeInstrumentRows = (
    holdings: HoldingLine[],
    catalog: CatalogLine[] | undefined,
    trends: CatalogTrend[] | undefined,
): InstrumentRow[] => {
    const weights = holdingWeights(holdings);
    const weightByAsset = new Map<number, HoldingWeight>(
        weights.map((weight: HoldingWeight): [number, HoldingWeight] => [weight.line.assetId, weight]),
    );

    const known = catalog ?? [];
    const listed = new Set<number>(known.map((line: CatalogLine): number => line.id));
    const unlisted = weights
        .filter((weight: HoldingWeight): boolean => !listed.has(weight.line.assetId))
        .map(asCatalogLine);

    return joinTrends([...known, ...unlisted], trends).map((row: CatalogRow): InstrumentRow => {
        const weight = weightByAsset.get(row.id);

        return {
            ...row,
            /** Les positions du tableau de bord font foi sur ce qui est détenu, pas le drapeau du catalogue. */
            held: weight !== undefined,
            marketValue: weight?.line.marketValue ?? row.marketValue,
            gain: weight?.line.gain ?? null,
            gainPct: weight?.line.gainPct ?? null,
            share: weight?.share ?? null,
            barWidth: weight?.barWidth ?? null,
            opacity: weight?.opacity ?? null,
        };
    });
};

/** Les positions d'abord, par valeur décroissante ; le reste du catalogue ensuite, par nom. */
const compareRows = (left: InstrumentRow, right: InstrumentRow): number => {
    if (left.held !== right.held) {
        return left.held ? -1 : 1;
    }

    if (left.held) {
        return (right.marketValue ?? 0) - (left.marketValue ?? 0);
    }

    return left.name.localeCompare(right.name, 'fr');
};

/** Sans recherche la liste montre le portefeuille ; dès la première frappe, tout le catalogue. */
export const visibleInstrumentRows = (rows: InstrumentRow[], query: string): InstrumentRow[] => {
    const visible = query.trim() === ''
        ? rows.filter((row: InstrumentRow): boolean => row.held)
        : filterCatalog(rows, query);

    return [...visible].sort(compareRows);
};
