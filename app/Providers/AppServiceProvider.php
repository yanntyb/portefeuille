<?php

namespace App\Providers;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Infrastructure\Eloquent\EloquentAssetPriceRepository;
use App\Domains\Asset\Infrastructure\Eloquent\EloquentAssetRepository;
use App\Domains\Asset\Ports\AssetPriceProviderPort;
use App\Domains\Asset\Services\AssetPriceAggregator;
use App\Domains\Asset\Services\PriceSyncService;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Repository interfaces → Eloquent implementations
        $this->app->bind(AssetRepositoryInterface::class, EloquentAssetRepository::class);
        $this->app->bind(AssetPriceRepositoryInterface::class, EloquentAssetPriceRepository::class);

        // Ports → Adapters
        $this->app->bind(AssetPriceProviderPort::class, \App\Domains\Asset\Infrastructure\Adapters\DatabaseAssetPriceAdapter::class);
        $this->app->bind(\App\Domains\AssetView\Ports\AssetPriceViewPort::class, \App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetPriceViewAdapter::class);
        $this->app->bind(\App\Domains\AssetView\Ports\AssetSectorViewPort::class, \App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetSectorViewAdapter::class);
        $this->app->bind(\App\Domains\AssetView\Ports\AssetMetaViewPort::class, \App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetMetaViewAdapter::class);

        // Asset domain services
        $this->app->singleton(AssetPriceAggregator::class);
        $this->app->scoped(\App\Domains\Asset\Services\AssetValuationService::class);
        $this->app->scoped(PriceSyncService::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
    }
}
