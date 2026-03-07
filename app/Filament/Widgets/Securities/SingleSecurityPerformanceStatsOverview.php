<?php

namespace App\Filament\Widgets\Securities;

use App\Services\PortfolioPerformanceCalculator;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class SingleSecurityPerformanceStatsOverview extends Widget
{
    protected string $view = 'filament.widgets.performance-stats-overview';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public ?string $accountType = null;

    /**
     * @return list<array{label: string, value: string, color: string}>
     */
    public function getPerformanceData(): array
    {
        if (! $this->record) {
            return [];
        }

        $calculator = app(PortfolioPerformanceCalculator::class);

        return PortfolioPerformanceCalculator::formatReturnsAsStats(
            $calculator->computeReturnsForSecurity($this->record, $this->accountType),
        );
    }
}
