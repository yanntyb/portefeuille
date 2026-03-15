<?php

namespace App\Filament\Widgets\Securities;

use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class ValuationStatOverview extends StatsOverviewWidget
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

    #[On('prices-updated')]
    public function refreshStats(): void
    {
        // Triggers re-render with fresh data
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

        $valuation = $records->sum(function ($record) {
            $close = $record->latestPrice?->close;

            if ($close === null || $record->total_quantity === null) {
                return 0;
            }

            return (float) $record->total_quantity * (float) $close;
        });

        return [
            Stat::make('Valorisation', Number::currency($valuation, 'EUR')),
        ];
    }
}
