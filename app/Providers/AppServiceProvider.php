<?php

namespace App\Providers;

use App\Domains\Analytics\Contracts\Rebalancing;
use App\Domains\Analytics\Contracts\VolatilityCalculating;
use App\Domains\Analytics\Services\RebalancingCalculator;
use App\Domains\Analytics\Services\RebalancingCalculatorOrchestrator;
use App\Domains\Analytics\Services\VolatilityCalculator;
use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Infrastructure\Eloquent\EloquentAssetPriceRepository;
use App\Domains\Asset\Infrastructure\Eloquent\EloquentAssetRepository;
use App\Domains\Portfolio\Contracts\PortfolioPerformanceCalculating;
use App\Domains\Portfolio\Contracts\TransactionRepositoryInterface;
use App\Domains\Portfolio\Infrastructure\Eloquent\EloquentTransactionRepository;
use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Domains\Portfolio\Services\PortfolioPerformanceCalculator;
use App\Domains\Portfolio\Services\SingleSecurityStatsProvider;
use App\Infrastructure\Services\UserId;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // User context service (singleton, overridable in tests)
        $this->app->singleton(UserId::class);

        // Request-scoped data providers (cached within single request)
        $this->app->scoped(DashboardDataProvider::class);
        $this->app->scoped(SingleSecurityStatsProvider::class);

        // Repository interfaces → Eloquent implementations
        $this->app->bind(AssetRepositoryInterface::class, EloquentAssetRepository::class);
        $this->app->bind(AssetPriceRepositoryInterface::class, EloquentAssetPriceRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, EloquentTransactionRepository::class);

        // Ports → Adapters
        $this->app->bind(\App\Domains\Asset\Ports\AssetPriceProviderPort::class, \App\Domains\Asset\Infrastructure\Adapters\DatabaseAssetPriceAdapter::class);

        // Asset domain services
        $this->app->scoped(\App\Domains\Asset\Services\AssetValuationService::class);

        // Service interfaces → implementations (scoped for per-request safety)
        $this->app->scoped(VolatilityCalculating::class, VolatilityCalculator::class);
        $this->app->scoped(PortfolioPerformanceCalculating::class, PortfolioPerformanceCalculator::class);
        $this->app->scoped(Rebalancing::class, RebalancingCalculator::class);

        // Orchestrators
        $this->app->scoped(RebalancingCalculatorOrchestrator::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('fr');
    }
}
