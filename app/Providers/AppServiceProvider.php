<?php

namespace App\Providers;

use App\Contexts\InstrumentView\Infrastructure\MarketData;
use App\Contexts\InstrumentView\Infrastructure\PortfolioHoldings;
use App\Contexts\InstrumentView\Infrastructure\PortfolioTransactions;
use App\Contexts\InstrumentView\InstrumentViewProvider;
use App\Contexts\Market\Infrastructure\DatabaseAssetPriceAdapter;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\MarketProvider;
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
            priceProvider: DatabaseAssetPriceAdapter::class,
        );

        ValuationProvider::registers(
            app: $this->app,
            transactionHistory: PortfolioTransactionHistory::class,
            priceHistory: MarketPriceHistory::class,
            instrumentDirectory: MarketInstrumentDirectory::class,
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
