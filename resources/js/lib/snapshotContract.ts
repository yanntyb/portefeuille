import type { CatalogTrend } from './catalog';
import type { AssetDividendHistory } from './income';
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

/** Une page liste d'exposition. */
export interface AssetClassListSnapshot {
    overview: PortfolioOverview;
    trends: CatalogTrend[];
    evolutionSeries: EvolutionSeries;
}

/** La page analyse d'une exposition. `sectorBreakdown` n'est servi que par celles qui ont des secteurs. */
export interface AssetClassAnalysisSnapshot {
    performances: Performance[];
    sectorBreakdown?: SectorSlice[];
}

/** La fiche servie par `/asset/{id}`. `dividends` manque aux expositions qui ne distribuent rien. */
export interface AssetPageSnapshot {
    instrument: Instrument;
    performances: Performance[];
    priceHistory: PriceHistory;
    valuation: ValuationSeries;
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
    /** Indexé par la valeur de `AssetClass` : `equity`, `bond`, `commodity`, `crypto`. */
    classes: Record<string, AssetClassListSnapshot>;
    /** Même indexation que `classes` : une entrée par exposition, pour sa page analyse. */
    analyses: Record<string, AssetClassAnalysisSnapshot>;
    assets: Record<string, AssetPageSnapshot>;
    properties: { list: PropertiesListSnapshot; byId: Record<string, PropertyPageSnapshot> };
}
