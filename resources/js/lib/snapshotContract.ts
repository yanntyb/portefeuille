import type { CatalogTrend } from './catalog';
import type { AnnualIncome, AssetDividendHistory, IncomeSummary } from './income';
import type { Instrument, PriceHistory, ValuationSeries } from './instrument';
import type { Performance } from './performance';
import type { EvolutionSeries, PortfolioOverview } from './portfolio';
import type {
    AmortizationLine,
    PropertyDetail,
    PropertyProfitability,
    RealEstateIncome,
    RealEstateOverview,
    RealEstateSeries,
} from './realEstate';
import type { SectorSlice } from './sector';
import type { WealthIncome, WealthOverview, WealthSeries } from './wealth';

/**
 * Miroir exact de ce que sert `GET /instantane`. Chaque bloc reprend les noms de props de la page
 * correspondante : la fusion côté page est alors une simple alternative entre deux valeurs de même
 * type, sans traduction possible à faire diverger.
 */
export interface DashboardSnapshot {
    overview: WealthOverview;
    series: WealthSeries;
    income: WealthIncome;
}

export interface InstrumentsListSnapshot {
    overview: PortfolioOverview;
    trends: CatalogTrend[];
    performances: Performance[];
    evolutionSeries: EvolutionSeries;
    sectorBreakdown: SectorSlice[];
    income: IncomeSummary;
    annualIncome: AnnualIncome[];
}

export interface CryptoListSnapshot {
    overview: PortfolioOverview;
    trends: CatalogTrend[];
    performances: Performance[];
    evolutionSeries: EvolutionSeries;
}

export interface InstrumentPageSnapshot {
    instrument: Instrument;
    performances: Performance[];
    priceHistory: PriceHistory;
    valuation: ValuationSeries;
    /** Absent des fiches crypto : leur page n'affiche pas de dividendes. */
    dividends?: AssetDividendHistory;
}

export interface PropertiesListSnapshot {
    realEstate: RealEstateOverview;
    series: RealEstateSeries;
    profitability: PropertyProfitability[];
    income: RealEstateIncome;
}

export interface PropertyPageSnapshot {
    property: PropertyDetail;
    amortization: AmortizationLine[];
}

export interface Snapshot {
    /** Empreinte du contenu : le client saute l'écriture IndexedDB quand elle n'a pas changé. */
    version: string;
    /** Horodatage Unix **en secondes**, produit par le serveur. */
    generatedAt: number;
    dashboard: DashboardSnapshot;
    instruments: { list: InstrumentsListSnapshot; byId: Record<string, InstrumentPageSnapshot> };
    crypto: { list: CryptoListSnapshot; byId: Record<string, InstrumentPageSnapshot> };
    properties: { list: PropertiesListSnapshot; byId: Record<string, PropertyPageSnapshot> };
}
