<?php

namespace App\Providers;

use App\Contexts\InstrumentView\Infrastructure\MarketData;
use App\Contexts\InstrumentView\Infrastructure\PortfolioHoldings;
use App\Contexts\InstrumentView\Infrastructure\PortfolioTransactions;
use App\Contexts\InstrumentView\InstrumentViewProvider;
use App\Contexts\Market\Infrastructure\DatabaseAssetPriceAdapter;
use App\Contexts\Market\Infrastructure\EloquentDividendRepository;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Infrastructure\EloquentSectorRepository;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\MarketProvider;
use App\Contexts\Valuation\Infrastructure\LaravelSeriesCache;
use App\Contexts\Valuation\Infrastructure\MarketInstrumentDirectory;
use App\Contexts\Valuation\Infrastructure\MarketPriceHistory;
use App\Contexts\Valuation\Infrastructure\PortfolioTransactionHistory;
use App\Contexts\Valuation\ValuationProvider;
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
            priceProvider: DatabaseAssetPriceAdapter::class,
            priceFeed: YahooFinanceAdapter::class,
            sectorProvider: YahooFinanceAdapter::class,
            dividendRepository: EloquentDividendRepository::class,
            dividendFeed: YahooFinanceAdapter::class,
        );

        ValuationProvider::registers(
            app: $this->app,
            transactionHistory: PortfolioTransactionHistory::class,
            priceHistory: MarketPriceHistory::class,
            instrumentDirectory: MarketInstrumentDirectory::class,
            seriesCache: LaravelSeriesCache::class,
        );

        InstrumentViewProvider::registers(
            app: $this->app,
            marketData: MarketData::class,
            holdings: PortfolioHoldings::class,
            transactions: PortfolioTransactions::class,
        );
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
    }
}
