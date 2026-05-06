<?php

namespace App\Providers;

use App\Contracts\PortfolioPerformanceCalculating;
use App\Domains\Security\Contracts\PriceRefreshing;
use App\Contracts\Rebalancing;
use App\Contracts\VolatilityCalculating;
use App\Services\DashboardDataProvider;
use App\Services\PortfolioPerformanceCalculator;
use App\Domains\Security\Services\PriceRefreshService;
use App\Services\RebalancingCalculator;
use App\Services\RebalancingCalculatorOrchestrator;
use App\Services\SingleSecurityStatsProvider;
use App\Services\VolatilityCalculator;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped data providers (cached within single request)
        $this->app->scoped(DashboardDataProvider::class);
        $this->app->scoped(SingleSecurityStatsProvider::class);

        // Service interfaces → implementations (scoped for per-request safety)
        $this->app->scoped(VolatilityCalculating::class, VolatilityCalculator::class);
        $this->app->scoped(PriceRefreshing::class, PriceRefreshService::class);
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
