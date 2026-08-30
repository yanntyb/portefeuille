import { eur, frDate } from '@/lib/format';

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
 * Repères du milieu de page : paires libellé/valeur où le libellé s'efface et la valeur porte la
 * lecture. Investi, gain et cours n'en sont pas — les deux premiers suivent le grand chiffre de
 * l'en-tête, le dernier se lit sur la courbe. Sans
 * position, il ne reste que la date du dernier cours : un instrument seulement suivi n'a pas de
 * prix de revient.
 */
export const heroMeta = (instrument: Instrument): HeroMetaEntry[] => {
    const position = instrument.position;

    if (position === null) {
        return instrument.lastPriceDate === null
            ? []
            : [{ label: '', value: `au ${frDate(instrument.lastPriceDate)}` }];
    }

    return [{ label: 'PRU', value: eur(position.avgCost) }];
};
