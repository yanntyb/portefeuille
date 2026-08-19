import { joinTrends, type CatalogLine, type CatalogRow, type CatalogTrend } from '@/lib/catalog';
import { holdingWeights, type HoldingLine, type HoldingWeight } from '@/lib/portfolio';

/** Une ligne de la liste : une position du portefeuille, jointe à la tendance de la période. */
export interface InstrumentRow extends CatalogRow {
    /** Gain latent de la position. */
    gain: number | null;
    gainPct: number | null;
    /** Part du portefeuille en pourcentage. */
    share: number | null;
    /** Largeur CSS de la barre de poids. */
    barWidth: string | null;
}

/** Une position ramenée à la forme d'une ligne de catalogue, que les tendances savent joindre. */
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

const byMarketValue = (left: InstrumentRow, right: InstrumentRow): number =>
    (right.marketValue ?? 0) - (left.marketValue ?? 0);

/**
 * Les positions du portefeuille, rangées par valeur. Les tendances arrivent différées : les lignes
 * sont servies sans elles, et les étincelles se remplissent ensuite sans que la liste ait été vide.
 */
export const holdingRows = (
    holdings: HoldingLine[],
    trends: CatalogTrend[] | undefined,
): InstrumentRow[] => {
    const weights = holdingWeights(holdings);
    const weightByAsset = new Map<number, HoldingWeight>(
        weights.map((weight: HoldingWeight): [number, HoldingWeight] => [weight.line.assetId, weight]),
    );

    return joinTrends(weights.map(asCatalogLine), trends)
        .map((row: CatalogRow): InstrumentRow => {
            const weight = weightByAsset.get(row.id);

            return {
                ...row,
                gain: weight?.line.gain ?? null,
                gainPct: weight?.line.gainPct ?? null,
                share: weight?.share ?? null,
                barWidth: weight?.barWidth ?? null,
            };
        })
        .sort(byMarketValue);
};

/**
 * Une prop différée est en attente tant qu'elle n'est pas arrivée et que le worker ne l'a pas
 * rescapée. Hors-ligne la clé est rescapée : elle n'arrivera jamais, l'attente n'a plus de sens.
 */
export function isDeferredPending(
    value: unknown,
    key: string,
    rescuedProps: string[] | undefined,
): boolean {
    return value === undefined && !(rescuedProps ?? []).includes(key);
}

/**
 * Le squelette des étincelles tient sur `trends === undefined`. Hors-ligne la prop n'arrivera
 * jamais : le worker rescape sa clé, et le squelette n'a plus à tourner.
 */
export function areTrendsPending(
    trends: CatalogTrend[] | undefined,
    rescuedProps: string[] | undefined,
): boolean {
    return isDeferredPending(trends, 'trends', rescuedProps);
}
