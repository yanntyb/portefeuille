<?php

namespace App\Domains\Analytics\Filament\Widgets\Dashboard;

use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Infrastructure\Filament\Concerns\ComputesPerformanceStats;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class DashboardPerformanceStatsOverview extends Widget
{
    use ComputesPerformanceStats;

    protected string $view = 'filament.widgets.performance-stats-overview';

    protected function resolvePerformanceSecurities(): Collection
    {
        $securities = app(DashboardDataProvider::class)->allSecurities();

        if ($this->shownSecurityIds !== null) {
            $securities = $securities->whereIn('id', $this->shownSecurityIds);
        }

        return $securities;
    }
}
