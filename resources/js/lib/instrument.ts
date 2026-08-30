import { frDate } from '@/lib/format';

export interface InstrumentPosition {
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

export interface TransactionLine {
    date: string;
    isSell: boolean;
    typeLabel: string;
    quantity: number;
    unitPrice: number;
    fees: number;
    total: number;
}

/**
 * Le groupe est paramétré par sa ligne : le tableau de bord y passe des lignes qui nomment leur
 * actif, la fiche des lignes nues, et le regroupement reste le même.
 */
export interface TransactionYear<Line extends TransactionLine = TransactionLine> {
    year: string;
    /** Flux investi de l'année : les achats en positif, les ventes en négatif. */
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
            net: yearLines.reduce((net, line) => net + (line.isSell ? -line.total : line.total), 0),
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
