<?php

namespace App\Filament\Widgets\Dashboard;

use App\Enums\AccountType;
use App\Services\DashboardDataProvider;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class DashboardGainStatsOverview extends Widget
{
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
        $provider = app(DashboardDataProvider::class);
        $accountTypes = [AccountType::Pea, AccountType::Cto];

        $totalValuation = 0;
        $totalInvested = 0;
        $totalFees = 0;
        $totalRealizedGain = 0;

        foreach ($accountTypes as $accountType) {
            $securities = $provider->securitiesForAccount($accountType);

            if ($this->shownSecurityIds !== null) {
                $securities = $securities->whereIn('id', $this->shownSecurityIds);
            }

            $totalValuation += $securities->sum(function ($security) {
                $close = $security->latestPrice?->close;

                if ($close === null || $security->total_quantity === null) {
                    return 0;
                }

                return (float) $security->total_quantity * (float) $close;
            });

            $totalInvested += $securities->sum(fn ($security) => (float) ($security->total_invested ?? 0));
            $totalFees += $securities->sum(fn ($security) => (float) ($security->total_fees ?? 0));
            $totalRealizedGain += $securities->sum(fn ($security) => (float) ($security->total_realized_gain ?? 0));
        }

        $plusValue = $totalValuation - $totalInvested;
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
