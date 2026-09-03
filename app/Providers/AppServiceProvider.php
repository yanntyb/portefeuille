<?php

namespace App\Providers;

use App\Contexts\Income\IncomeProvider;
use App\Contexts\Income\Sources\Dividend\DividendIncomeSource;
use App\Contexts\Income\Sources\Dividend\Infrastructure\MarketDividendHistory;
use App\Contexts\Income\Sources\Dividend\Infrastructure\PortfolioPositionHistory;
use App\Contexts\Income\Sources\Rent\Infrastructure\RealEstateRentSchedule;
use App\Contexts\Income\Sources\Rent\RentIncomeSource;
use App\Contexts\Market\Infrastructure\CacheMarketSyncState;
use App\Contexts\Market\Infrastructure\EloquentDividendRepository;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Infrastructure\EloquentSectorRepository;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\MarketProvider;
use App\Contexts\Portfolio\PortfolioProvider;
use App\Contexts\RealEstate\Infrastructure\LaravelRealEstateCache;
use App\Contexts\RealEstate\RealEstateProvider;
use App\Contexts\Valuation\Infrastructure\LaravelSeriesCache;
use App\Contexts\Valuation\Infrastructure\MarketInstrumentDirectory;
use App\Contexts\Valuation\Infrastructure\MarketPriceHistory;
use App\Contexts\Valuation\Infrastructure\PortfolioTransactionHistory;
use App\Contexts\Valuation\ValuationProvider;
use App\Contexts\Wealth\Infrastructure\CashClass;
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

        PortfolioProvider::registers(app: $this->app);

        RealEstateProvider::registers(
            app: $this->app,
            cache: LaravelRealEstateCache::class,
        );

        IncomeProvider::registers(
            app: $this->app,
            sources: [DividendIncomeSource::class, RentIncomeSource::class],
            dividendHistory: MarketDividendHistory::class,
            positionHistory: PortfolioPositionHistory::class,
            rentSchedule: RealEstateRentSchedule::class,
        );

        /** L'ordre décide de celui des lignes du tableau de bord et des bandes de son graphe. */
        WealthProvider::registers(
            app: $this->app,
            extra: [RealEstateClass::class, CashClass::class],
        );
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
    }
}
