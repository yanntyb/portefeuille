import { eur, frDate } from '@/lib/format';

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
        { label: 'Titres', value: position.quantity.toLocaleString('fr-FR') },
        { label: 'PRU', value: eur(position.avgCost) },
        { label: 'Investi', value: eur(investedOf(position)) },
        { label: 'Cours', value: eur(instrument.lastPrice) },
    ];
};
