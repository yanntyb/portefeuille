<?php

namespace App\Filament\Widgets\Securities;

use App\Infrastructure\Filament\Concerns\HasReactiveTableProperties;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class SecurityStatsOverview extends StatsOverviewWidget
{
    use HasReactiveTableProperties;

    protected ?string $pollingInterval = null;

    /** @var list<int>|null */
    public ?array $shownSecurityIds = null;

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

        $totalInvested = $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));
        $totalFees = $records->sum(fn ($record) => (float) ($record->total_fees ?? 0));

        $valuation = $records->sum(fn ($record) => $record->currentValuation());

        $totalRealizedGain = $records->sum(fn ($record) => (float) ($record->total_realized_gain ?? 0));

        $plusValue = $valuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        return [
            Stat::make('Valorisation', Number::currency($valuation, 'EUR')),
            Stat::make('Plus-value latente', Number::currency($plusValue, 'EUR'))
                ->description(Number::format($plusValuePercentage, 2).' %')
                ->color($plusValue >= 0 ? 'success' : 'danger'),
            Stat::make('Frais', Number::currency($totalFees, 'EUR'))
                ->description(Number::format($feesPercentage, 2).' %')
                ->color('danger'),
            Stat::make('Plus-value réalisée', Number::currency($totalRealizedGain, 'EUR'))
                ->color($totalRealizedGain >= 0 ? 'success' : 'danger'),
        ];
    }
}
