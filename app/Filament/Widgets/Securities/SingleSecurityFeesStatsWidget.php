<?php

namespace App\Filament\Widgets\Securities;

use App\Infrastructure\Filament\Concerns\ComputesSingleSecurityStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SingleSecurityFeesStatsWidget extends StatsOverviewWidget
{
    use ComputesSingleSecurityStats;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = ['default' => 1];

    public ?Model $record = null;

    public ?string $accountType = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $stats = $this->computeStats();

        return [
            Stat::make('Frais', Number::currency($stats['totalFees'], 'EUR'))
                ->description(Number::format($stats['feesPercentage'], 2).' %')
                ->color('danger'),
        ];
    }
}
