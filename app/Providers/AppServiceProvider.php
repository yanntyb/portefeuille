<?php

namespace App\Providers;

use App\Contracts\Rebalancing;
use App\Contracts\VolatilityCalculating;
use App\Domains\Portfolio\Contracts\PortfolioPerformanceCalculating;
use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Domains\Portfolio\Services\PortfolioPerformanceCalculator;
use App\Domains\Portfolio\Services\SingleSecurityStatsProvider;
use App\Domains\Security\Commands\FetchSecurityPricesCommand;
use App\Domains\Security\Commands\FetchSecuritySectorsCommand;
use App\Domains\Security\Contracts\PriceRefreshing;
use App\Domains\Security\Services\PriceRefreshService;
use App\Services\RebalancingCalculator;
use App\Services\RebalancingCalculatorOrchestrator;
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

        $this->commands([
            FetchSecurityPricesCommand::class,
            FetchSecuritySectorsCommand::class,
        ]);
    }
}
