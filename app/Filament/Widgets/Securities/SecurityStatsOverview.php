<?php

namespace App\Filament\Widgets\Securities;

use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class SecurityStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    /** @var class-string */
    public string $tablePageClass;

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
    }

    protected function getStats(): array
    {
        $records = $this->getPageTableQuery()->with('latestPrice')->get();

        $totalInvested = $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));

        $valuation = $records->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });

        $plusValue = $valuation - $totalInvested;

        return [
            Stat::make('Valorisation', Number::currency($valuation, 'EUR')),
            Stat::make('Plus-value', Number::currency($plusValue, 'EUR'))
                ->color($plusValue >= 0 ? 'success' : 'danger'),
        ];
    }
}
