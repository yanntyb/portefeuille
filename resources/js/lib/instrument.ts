import { eur, frDate, frQuantity } from '@/lib/format';

export interface InstrumentPosition {
    assetId: number;
    quantity: number;
    avgCost: number | null;
    marketValue: number | null;
    gain: number | null;
    gainPct: number | null;
    /** Gain déjà encaissé sur l'actif, nul faute de vente. */
    realizedGain: number;
}

/** The cost basis of a position, which the backend only exposes through its average cost. */
export const investedOf = (position: InstrumentPosition): number | null =>
    position.avgCost === null ? null : position.avgCost * position.quantity;

/** Les cinq natures d'opération, jumelles de `Portfolio\Enums\TransactionType` côté serveur. */
export type TransactionKind = 'buy' | 'sell' | 'deposit' | 'withdrawal' | 'dividend';

export interface TransactionLine {
    id: number;
    /**
     * L'enveloppe de détention, pour que l'édition d'une ligne la réécrive là où elle est : la même
     * quantité du même actif peut être tenue dans deux comptes.
     */
    walletId: number;
    date: string;
    /**
     * L'actif de la ligne, nul sur un versement ou un retrait qui n'en ont pas. Porté par toutes
     * les pages depuis que le serveur sert un seul journal : hors de sa fiche, une quantité ne dit
     * pas de quoi elle est la quantité.
     */
    assetId: number | null;
    assetName: string | null;
    isSell: boolean;
    typeLabel: string;
    /** Distingue les cinq natures d'opération ; `isSell` reste pour ne trancher qu'achat/vente. */
    type: TransactionKind;
    quantity: number;
    unitPrice: number;
    fees: number;
    total: number;
    /**
     * Une ligne déduite par le système plutôt que saisie — un versement qui finance un achat non
     * couvert, réécrit à chaque correction. Le journal le dit et n'offre aucune correction dessus :
     * elle serait de toute façon reconstruite à la prochaine reprojection.
     */
    auto: boolean;
}

/**
 * De quelle opération il s'agit, en une phrase — ce que le volet de confirmation d'une suppression
 * récapitule.
 *
 * Une ligne sans quantité se nomme par son type et son montant : un versement n'a pas d'actif, et
 * un dividende en a un mais aucune quantité, si bien que « Dividende de 0 Air Liquide » s'affichait
 * là où « Dividende 34,90 € » dit le fait. La quantité, quand elle existe, est formatée comme le
 * reste de l'écran — « de 2.5 » y traînait une écriture anglaise.
 */
export const transactionLabelOf = (line: TransactionLine): string =>
    line.assetName === null || line.quantity === 0
        ? `${line.typeLabel} ${eur(line.total)} du ${frDate(line.date)}`
        : `${line.typeLabel} de ${frQuantity(line.quantity)} ${line.assetName} du ${frDate(line.date)}`;

/**
 * Le sens de trésorerie d'une ligne, miroir de `Portfolio\Services\TransactionFlow::cashDelta()` :
 * un achat et un retrait sortent de l'enveloppe, une vente, un versement et un dividende y entrent.
 */
export const cashSignOf = (type: TransactionKind): 1 | -1 => (type === 'buy' || type === 'withdrawal' ? -1 : 1);

/**
 * Le groupe est paramétré par sa ligne : le tableau de bord y passe des lignes qui nomment leur
 * actif, la fiche des lignes nues, et le regroupement reste le même.
 */
export interface TransactionYear<Line extends TransactionLine = TransactionLine> {
    year: string;
    /**
     * Flux de trésorerie de l'année : achat et retrait en négatif, vente, versement et dividende en
     * positif. C'est le seul sens qui reste juste une fois les espèces présentes — un achat financé
     * par un virement du même montant y solde à zéro, alors qu'un « flux investi » qui compterait
     * les deux en positif doublerait le mouvement.
     */
    net: number;
    lines: Line[];
}

/** Regroupe les transactions par année, la plus récente en tête, l'ordre reçu conservé dans chaque groupe. */
export const transactionYears = <Line extends TransactionLine>(lines: Line[]): TransactionYear<Line>[] => {
    const groups = new Map<string, Line[]>();

    for (const line of lines) {
        const year = line.date.slice(0, 4);
        groups.set(year, [...(groups.get(year) ?? []), line]);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => right.localeCompare(left))
        .map(([year, yearLines]) => ({
            year,
            net: yearLines.reduce((net, line) => net + cashSignOf(line.type) * line.total, 0),
            lines: yearLines,
        }));
};

export interface SectorWeight {
    label: string;
    /** Share of the instrument, between 0 and 1. */
    weight: number;
}

export interface Instrument {
    id: number;
    name: string;
    ticker: string | null;
    isin: string | null;
    type: string;
    typeLabel: string;
    assetClass: string;
    assetClassLabel: string;
    assetClassHref: string;
    lastPrice: number | null;
    lastPriceDate: string | null;
    position: InstrumentPosition | null;
    transactions: TransactionLine[];
    sectors: SectorWeight[];
}

/** Jumelle d'`InstrumentAnalysisData` : tout est nullable, un instrument sans cours n'a que son PRU. */
export interface InstrumentAnalysis {
    price: number | null;
    pru: number | null;
    pruGapPct: number | null;
    high52w: number | null;
    high52wGapPct: number | null;
    /** Pourcentage positif, comme le rend le serveur : le signe est posé à l'affichage. */
    maxDrawdown: number | null;
    portfolioWeightPct: number | null;
}

export interface PriceHistory {
    labels: string[];
    close: number[];
}

export interface ValuationSeries {
    labels: string[];
    valuations: number[];
    invested: number[];
    prices: number[];
}

export interface HeroMetaEntry {
    label: string;
    value: string;
    /** Montant signé quand la valeur se colore comme un gain ; absent sur une stat neutre. */
    gain?: number | null;
}

/** Un instrument détenu vaut sa valeur de marché ; sinon il ne vaut que son dernier cours. */
export const heroValueOf = (instrument: Instrument): number | null =>
    instrument.position?.marketValue ?? instrument.lastPrice;

/**
 * Repères du milieu de page. Une position n'en pose plus aucun : prix de revient, tendance et
 * risque se lisent dans la section Analyse, qui les explique. Il ne reste ici que le cas d'un
 * instrument seulement suivi — la date de son dernier cours, qu'aucune autre section ne porte.
 */
export const heroMeta = (instrument: Instrument): HeroMetaEntry[] => {
    if (instrument.position !== null) {
        return [];
    }

    return instrument.lastPriceDate === null
        ? []
        : [{ label: '', value: `au ${frDate(instrument.lastPriceDate)}` }];
};
