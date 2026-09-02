<?php

namespace App\Providers;

use App\Contexts\Income\IncomeProvider;
use App\Contexts\Income\Sources\Dividend\DividendIncomeSource;
use App\Contexts\Income\Sources\Dividend\Infrastructure\MarketDividendHistory;
use App\Contexts\Income\Sources\Dividend\Infrastructure\PortfolioPositionHistory;
use App\Contexts\Income\Sources\Rent\Infrastructure\RealEstateRentSchedule;
use App\Contexts\Income\Sources\Rent\RentIncomeSource;
use App\Contexts\Market\Infrastructure\CacheMarketSyncState;
use App\Contexts\Market\Infrastructure\DatabaseAssetPriceAdapter;
use App\Contexts\Market\Infrastructure\EloquentDividendRepository;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Infrastructure\EloquentSectorRepository;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\MarketProvider;
use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Actions\GetRealizedGains;
use App\Contexts\PortfolioView\Infrastructure\BasketAnalysis;
use App\Contexts\PortfolioView\Infrastructure\IncomeTotals;
use App\Contexts\PortfolioView\Infrastructure\InstrumentAnalysis;
use App\Contexts\PortfolioView\Infrastructure\MarketData;
use App\Contexts\PortfolioView\Infrastructure\PortfolioAccounts as PortfolioViewAccounts;
use App\Contexts\PortfolioView\Infrastructure\PortfolioHoldings;
use App\Contexts\PortfolioView\Infrastructure\PortfolioSectors;
use App\Contexts\PortfolioView\Infrastructure\PortfolioTotals;
use App\Contexts\PortfolioView\Infrastructure\PortfolioTransactions;
use App\Contexts\PortfolioView\Infrastructure\ValuationHistory;
use App\Contexts\PortfolioView\PortfolioViewProvider;
use App\Contexts\RealEstate\Infrastructure\LaravelRealEstateCache;
use App\Contexts\RealEstate\RealEstateProvider;
use App\Contexts\Valuation\Infrastructure\LaravelSeriesCache;
use App\Contexts\Valuation\Infrastructure\MarketInstrumentDirectory;
use App\Contexts\Valuation\Infrastructure\MarketPriceHistory;
use App\Contexts\Valuation\Infrastructure\PortfolioTransactionHistory;
use App\Contexts\Valuation\ValuationProvider;
use App\Contexts\Wealth\Infrastructure\CashClass;
use App\Contexts\Wealth\Infrastructure\PortfolioAccounts;
use App\Contexts\Wealth\Infrastructure\PortfolioInvestedCapital;
use App\Contexts\Wealth\Infrastructure\PortfolioLedger;
use App\Contexts\Wealth\Infrastructure\RealEstateClass;
use App\Contexts\Wealth\WealthProvider;
use App\Shared\Python\ProcessPythonRunner;
use App\Shared\Python\PythonProvider;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        PythonProvider::registers(
            app: $this->app,
            pythonRunner: ProcessPythonRunner::class,
        );

        MarketProvider::registers(
            app: $this->app,
            instrumentRepository: EloquentInstrumentRepository::class,
            priceRepository: EloquentPriceRepository::class,
            sectorRepository: EloquentSectorRepository::class,
            instrumentProvider: YahooFinanceAdapter::class,
            priceProvider: DatabaseAssetPriceAdapter::class,
            priceFeed: YahooFinanceAdapter::class,
            sectorProvider: YahooFinanceAdapter::class,
            dividendRepository: EloquentDividendRepository::class,
            dividendFeed: YahooFinanceAdapter::class,
            syncState: CacheMarketSyncState::class,
        );

        ValuationProvider::registers(
            app: $this->app,
            transactionHistory: PortfolioTransactionHistory::class,
            priceHistory: MarketPriceHistory::class,
            instrumentDirectory: MarketInstrumentDirectory::class,
            seriesCache: LaravelSeriesCache::class,
        );

        RealEstateProvider::registers(
            app: $this->app,
            cache: LaravelRealEstateCache::class,
        );

        PortfolioViewProvider::registers(
            app: $this->app,
            marketData: MarketData::class,
            holdings: PortfolioHoldings::class,
            transactions: PortfolioTransactions::class,
            sectorBreakdown: PortfolioSectors::class,
            income: IncomeTotals::class,
            portfolioOverview: PortfolioTotals::class,
            valuation: ValuationHistory::class,
            instrumentAnalysis: InstrumentAnalysis::class,
            basketAnalysis: BasketAnalysis::class,
            accounts: PortfolioViewAccounts::class,
        );

        IncomeProvider::registers(
            app: $this->app,
            sources: [DividendIncomeSource::class, RentIncomeSource::class],
            dividendHistory: MarketDividendHistory::class,
            positionHistory: PortfolioPositionHistory::class,
            rentSchedule: RealEstateRentSchedule::class,
        );

        /** Une lecture du portefeuille par requête : les classes d'actif la partagent. */
        $this->app->scoped(GetPortfolioOverview::class);

        /**
         * Une lecture des positions par requête : `PortfolioView` et `Income` l'appellent chacun une
         * fois par position détenue en construisant l'instantané, sur le même principe que
         * `GetPortfolioOverview`.
         */
        $this->app->scoped(GetPortfolioPositions::class);

        /** Une lecture des ventes par requête : le gain réalisé se lit aux quatre expositions. */
        $this->app->scoped(GetRealizedGains::class);

        /**
         * Une lecture des mouvements d'espèces par requête : `RecomputeCashDeposits` la relit à
         * chaque transaction touchée, sur le même principe que `GetPortfolioOverview`.
         */
        $this->app->scoped(GetCashMovements::class);

        /**
         * Une répartition des apports nets par requête : le reliquat qu'une exposition libère en
         * vendant se replace dans une autre, donc chaque classe a besoin de la photo globale et
         * la referait sinon cinq fois par tableau de bord.
         */
        $this->app->scoped(PortfolioInvestedCapital::class);

        /** L'ordre décide de celui des lignes du tableau de bord et des bandes de son graphe. */
        WealthProvider::registers(
            app: $this->app,
            extra: [RealEstateClass::class, CashClass::class],
            transactions: PortfolioLedger::class,
            accounts: PortfolioAccounts::class,
        );
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
    }
}
