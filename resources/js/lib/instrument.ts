import { eur, frDate, signedEur } from '@/lib/format';

export interface InstrumentPosition {
    quantity: number;
    avgCost: number | null;
    marketValue: number | null;
    gain: number | null;
    gainPct: number | null;
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

export interface TransactionYear {
    year: string;
    /** Flux investi de l'année : les achats en positif, les ventes en négatif. */
    net: number;
    lines: TransactionLine[];
}

/** Regroupe les transactions par année, la plus récente en tête, l'ordre reçu conservé dans chaque groupe. */
export const transactionYears = (lines: TransactionLine[]): TransactionYear[] => {
    const groups = new Map<string, TransactionLine[]>();

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
 * Pied de l'en-tête : paires libellé/valeur où le libellé s'efface et la valeur porte la lecture.
 * Sans position, il ne reste que la date du dernier cours — un instrument seulement suivi n'a ni
 * prix de revient ni montant investi.
 */
export const heroMeta = (instrument: Instrument): HeroMetaEntry[] => {
    const position = instrument.position;

    if (position === null) {
        return instrument.lastPriceDate === null
            ? []
            : [{ label: '', value: `au ${frDate(instrument.lastPriceDate)}` }];
    }

    return [
        { label: 'Investi', value: eur(investedOf(position)) },
        { label: 'Gain', value: signedEur(position.gain), gain: position.gain },
        { label: 'Cours', value: eur(instrument.lastPrice) },
        { label: 'PRU', value: eur(position.avgCost) },
    ];
};
