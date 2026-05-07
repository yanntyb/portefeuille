<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Infrastructure\Filament\Concerns\ComputesPerformanceStats;
use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use App\Infrastructure\Filament\Concerns\HasStatWidgetListeners;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class PerformanceStatsOverview extends Widget
{
    use ComputesPerformanceStats;
    use HasReactiveTableProperties;
    use HasStatWidgetListeners;

    protected string $view = 'filament.widgets.performance-stats-overview';

    protected function resolvePerformanceSecurities(): Collection
    {
        return $this->getFilteredSecurities();
    }
}
