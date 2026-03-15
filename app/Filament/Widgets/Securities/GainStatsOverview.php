<?php

namespace App\Filament\Widgets\Securities;

use App\Filament\Widgets\Securities\Concerns\HasReactiveTableProperties;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class GainStatsOverview extends Widget
{
    use HasReactiveTableProperties;

    protected string $view = 'filament.widgets.gain-stats-overview';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

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

    /**
     * @return array{
     *     plusValue: string,
     *     plusValuePercentage: string,
     *     plusValuePositive: bool,
     *     realizedGain: string,
     *     realizedGainPositive: bool,
     *     fees: string,
     *     feesPercentage: string,
     * }
     */
    public function getGainData(): array
    {
        if ($this->tablePageClass === null) {
            return [
                'plusValue' => Number::currency(0, 'EUR'),
                'plusValuePercentage' => '0 %',
                'plusValuePositive' => true,
                'realizedGain' => Number::currency(0, 'EUR'),
                'realizedGainPositive' => true,
                'fees' => Number::currency(0, 'EUR'),
                'feesPercentage' => '0 %',
            ];
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

        $totalRealizedGain = $records->sum(fn ($record) => (float) ($record->total_realized_gain ?? 0));

        $plusValue = $valuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        return [
            'plusValue' => Number::currency($plusValue, 'EUR'),
            'plusValuePercentage' => Number::format($plusValuePercentage, 2).' %',
            'plusValuePositive' => $plusValue >= 0,
            'realizedGain' => Number::currency($totalRealizedGain, 'EUR'),
            'realizedGainPositive' => $totalRealizedGain >= 0,
            'fees' => Number::currency($totalFees, 'EUR'),
            'feesPercentage' => Number::format($feesPercentage, 2).' %',
        ];
    }
}
