import { filterCatalog, joinTrends, relevanceRank, type CatalogLine, type CatalogRow, type CatalogTrend } from '@/lib/catalog';
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
        };
    });
};

/** Un bloc de la liste : les positions, le reste du catalogue, ou les résultats d'une recherche. */
export interface InstrumentSection {
    /** Titre du bloc, nul sur la liste plate d'une recherche : les résultats ne se rangent plus par détention. */
    label: string | null;
    rows: InstrumentRow[];
    /** Le catalogue est encore différé : le bloc s'annonce mais n'a que son squelette à montrer. */
    pending: boolean;
}

const byMarketValue = (left: InstrumentRow, right: InstrumentRow): number =>
    (right.marketValue ?? 0) - (left.marketValue ?? 0);

const byName = (left: InstrumentRow, right: InstrumentRow): number =>
    left.name.localeCompare(right.name, 'fr');

/** Un ticker tapé remonte avant un nom qui commence pareil, et le nom tranche les égalités. */
const byRelevance = (query: string) => (left: InstrumentRow, right: InstrumentRow): number =>
    relevanceRank(left, query) - relevanceRank(right, query) || byName(left, right);

/**
 * Découpe la liste en blocs. Sans recherche, deux blocs titrés : le portefeuille, puis le reste du
 * catalogue — les instruments non détenus se voient sans qu'on ait à taper. Dès la première frappe,
 * un seul bloc sans titre : la recherche compare, la détention n'ordonne plus rien.
 */
export const instrumentSections = (
    rows: InstrumentRow[],
    query: string,
    catalogPending = false,
): InstrumentSection[] => {
    if (query.trim() !== '') {
        const found = [...filterCatalog(rows, query)].sort(byRelevance(query));

        return found.length === 0 ? [] : [{ label: null, rows: found, pending: false }];
    }

    const held = rows.filter((row: InstrumentRow): boolean => row.held).sort(byMarketValue);
    const others = rows.filter((row: InstrumentRow): boolean => !row.held).sort(byName);

    return [
        ...(held.length > 0 ? [{ label: 'Mes positions', rows: held, pending: false }] : []),
        ...(others.length > 0 || catalogPending
            ? [{ label: 'Autres instruments', rows: others, pending: catalogPending }]
            : []),
    ];
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
 * Le squelette des tendances tient sur `trends === undefined`. Hors-ligne la prop n'arrivera
 * jamais : le worker rescape sa clé, et le squelette n'a plus à tourner.
 */
export function isCatalogLoading(
    trends: CatalogTrend[] | undefined,
    rescuedProps: string[] | undefined,
    reloading: boolean,
): boolean {
    return reloading || isDeferredPending(trends, 'trends', rescuedProps);
}
