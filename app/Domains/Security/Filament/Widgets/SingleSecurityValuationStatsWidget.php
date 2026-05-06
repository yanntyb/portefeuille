<?php

namespace App\Domains\Security\Filament\Widgets;

use App\Infrastructure\Filament\Concerns\ComputesSingleSecurityStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SingleSecurityValuationStatsWidget extends StatsOverviewWidget
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
            Stat::make('Valorisation', Number::currency($stats['valuation'], 'EUR')),
        ];
    }
}
