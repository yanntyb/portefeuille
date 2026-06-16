<?php

namespace App\Providers;

use App\Contexts\Market\Infrastructure\DatabaseAssetPriceAdapter;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\MarketProvider;
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
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
    }
}
