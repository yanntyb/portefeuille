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
    /**
     * Clé de rendu : l'actif seul ne suffit pas. Un titre tenu dans deux enveloppes fait deux
     * lignes de même `id`, que Vue confondrait et dont la seconde écraserait le poids de la
     * première.
     */
    rowKey: string;
    walletId: number;
    walletName: string;
    accountTypeLabel: string;
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

/** L'actif seul ne discrimine pas deux lignes d'une même position tenue dans deux enveloppes. */
const keyOf = (line: HoldingLine): string => `${line.assetId}-${line.walletId}`;

/**
 * Les positions du portefeuille, rangées par valeur. Les tendances arrivent différées : les lignes
 * sont servies sans elles, et les étincelles se remplissent ensuite sans que la liste ait été vide.
 *
 * `joinTrends` (`@/lib/catalog`) est un `lines.map(...)` pur : même ordre, même cardinal, aucun
 * filtrage. Le poids se rattache donc par position d'index plutôt que par une `Map` indexée sur
 * l'actif, qui confondrait deux lignes du même actif tenues dans deux enveloppes différentes.
 */
export const holdingRows = (
    holdings: HoldingLine[],
    trends: CatalogTrend[] | null | undefined,
): InstrumentRow[] => {
    const weights = holdingWeights(holdings);
    const joined = joinTrends(weights.map(asCatalogLine), trends);

    return joined
        .map((row: CatalogRow, index: number): InstrumentRow => {
            const weight = weights[index];

            return {
                ...row,
                gain: weight?.line.gain ?? null,
                gainPct: weight?.line.gainPct ?? null,
                share: weight?.share ?? null,
                barWidth: weight?.barWidth ?? null,
                rowKey: weight === undefined ? String(row.id) : keyOf(weight.line),
                walletId: weight?.line.walletId ?? 0,
                walletName: weight?.line.walletName ?? '',
                accountTypeLabel: weight?.line.accountTypeLabel ?? '',
            };
        })
        .sort(byMarketValue);
};

/**
 * Une prop différée est en attente tant qu'elle n'est pas arrivée et que le worker ne l'a pas
 * rescapée. Hors-ligne la clé est rescapée : elle n'arrivera jamais, l'attente n'a plus de sens.
 *
 * `null` compte comme absente au même titre que `undefined` : c'est la valeur que rend
 * `aheadOfNetwork` quand ni la prop réseau ni l'instantané ne portent la donnée.
 */
export function isDeferredPending(
    value: unknown,
    key: string,
    rescuedProps: string[] | undefined,
): boolean {
    return (value === undefined || value === null) && !(rescuedProps ?? []).includes(key);
}

/**
 * Le squelette des étincelles tient sur l'absence de valeur. Hors-ligne la prop n'arrivera
 * jamais : le worker rescape sa clé, et le squelette n'a plus à tourner.
 */
export function areTrendsPending(
    trends: CatalogTrend[] | null | undefined,
    rescuedProps: string[] | undefined,
): boolean {
    return isDeferredPending(trends, 'trends', rescuedProps);
}
