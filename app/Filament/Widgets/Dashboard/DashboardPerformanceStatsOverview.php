<?php

namespace App\Filament\Widgets\Dashboard;

use App\Services\DashboardDataProvider;
use App\Services\PortfolioPerformanceCalculator;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class DashboardPerformanceStatsOverview extends Widget
{
    protected string $view = 'filament.widgets.performance-stats-overview';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    #[On('prices-updated')]
    public function refreshStats(): void
    {
        // Triggers re-render with fresh data
    }

    /**
     * @return list<array{label: string, value: string, color: string}>
     */
    public function getPerformanceData(): array
    {
        $securities = app(DashboardDataProvider::class)->allSecurities();
        $calculator = app(PortfolioPerformanceCalculator::class);

        return PortfolioPerformanceCalculator::formatReturnsAsStats(
            $calculator->computeReturns($securities),
        );
    }
}
