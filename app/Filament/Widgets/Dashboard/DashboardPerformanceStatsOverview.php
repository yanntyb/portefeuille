<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\PerformancePeriod;
use App\Services\DashboardDataProvider;
use App\Services\PortfolioPerformanceCalculator;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
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

        $returns = app(PortfolioPerformanceCalculator::class)->computeReturns($securities);

        $stats = [];

        foreach (PerformancePeriod::cases() as $period) {
            $value = $returns[$period->value];

            if ($value === null) {
                $stats[] = [
                    'label' => $period->getLabel(),
                    'value' => '—',
                    'color' => 'gray',
                ];

                continue;
            }

            $formatted = ($value >= 0 ? '+' : '').Number::format($value, 2).' %';

            $stats[] = [
                'label' => $period->getLabel(),
                'value' => $formatted,
                'color' => $value >= 0 ? 'success' : 'danger',
            ];
        }

        return $stats;
    }
}
