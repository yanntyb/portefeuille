<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Domains\Security\Models\Security;
use App\Infrastructure\Filament\Concerns\ComputesPerformanceStats;
use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class PerformanceStatsOverview extends Widget
{
    use ComputesPerformanceStats;
    use HasReactiveTableProperties;

    protected string $view = 'filament.widgets.performance-stats-overview';

    protected function resolvePerformanceSecurities(): Collection
    {
        if ($this->tablePageClass === null) {
            return Security::query()->where('id', null)->get();
        }

        $query = $this->getPageTableQuery();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        return $query->with('latestPrice')->get();
    }
}
