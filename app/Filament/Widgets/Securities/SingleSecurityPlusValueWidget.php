<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\ComputesSingleSecurityStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class SingleSecurityPlusValueWidget extends StatsOverviewWidget
{
    use ComputesSingleSecurityStats;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = ['default' => 2];

    public ?Model $record = null;

    public ?string $accountType = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $stats = $this->computeStats();

        return [
            Stat::make('Plus-value latente', Number::currency($stats['plusValue'], 'EUR'))
                ->description(Number::format($stats['plusValuePercentage'], 2).' %')
                ->color($stats['plusValue'] >= 0 ? 'success' : 'danger'),
            Stat::make('Plus-value réalisée', Number::currency($stats['totalRealizedGain'], 'EUR'))
                ->color($stats['totalRealizedGain'] >= 0 ? 'success' : 'danger'),
        ];
    }
}
