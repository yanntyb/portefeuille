<?php

namespace App\Filament\Widgets\Securities;

use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class SecurityStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    /** @var class-string|null */
    public ?string $tablePageClass = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

    protected function getTablePage(): string
    {
        return $this->tablePageClass;
    }

    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
    }

    protected function getStats(): array
    {
        if ($this->tablePageClass === null) {
            return [];
        }

        $query = $this->getPageTableQuery();

        if ($this->shownSecurityIds !== null) {
            $query->whereIn('securities.id', $this->shownSecurityIds);
        }

        $records = $query->with('latestPrice')->get();

        $totalInvested = $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));
        $totalFees = $records->sum(fn ($record) => (float) ($record->total_fees ?? 0));

        $valuation = $records->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });

        $plusValue = $valuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        $plusValueLabel = Number::currency($plusValue, 'EUR').' ('.Number::format($plusValuePercentage, 2).' %)';
        $feesLabel = Number::currency($totalFees, 'EUR').' ('.Number::format($feesPercentage, 2).' %)';

        return [
            Stat::make('Valorisation', Number::currency($valuation, 'EUR')),
            Stat::make('Plus-value', $plusValueLabel)
                ->color($plusValue >= 0 ? 'success' : 'danger'),
            Stat::make('Frais', $feesLabel)
                ->color('danger'),
        ];
    }
}
