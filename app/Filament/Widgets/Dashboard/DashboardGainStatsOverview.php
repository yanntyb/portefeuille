<?php

namespace App\Filament\Widgets\Dashboard;

use App\Services\DashboardDataProvider;
use App\Services\VolatilityCalculator;
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
     *     volatilite: ?string,
     * }
     */
    public function getGainData(): array
    {
        $provider = app(DashboardDataProvider::class);

        $totalValuation = 0;
        $totalInvested = 0;
        $totalFees = 0;
        $totalRealizedGain = 0;
        $walletVolatilities = [];

        foreach ($provider->wallets() as $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            if ($this->shownSecurityIds !== null) {
                $securities = $securities->whereIn('id', $this->shownSecurityIds);
            }

            $walletValuation = $securities->sum(fn ($security) => $security->currentValuation());

            $totalValuation += $walletValuation;
            $totalInvested += $securities->sum(fn ($security) => (float) ($security->total_invested ?? 0));
            $totalFees += $securities->sum(fn ($security) => (float) ($security->total_fees ?? 0));
            $totalRealizedGain += $securities->sum(fn ($security) => (float) ($security->total_realized_gain ?? 0));

            $walletVolatility = app(VolatilityCalculator::class)->forWallet($wallet);
            $walletVolatilities[] = [
                'valuation' => $walletValuation,
                'volatility' => $walletVolatility,
            ];
        }

        $plusValue = $totalValuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        $volatilite = null;
        if ($totalValuation > 0 && count($walletVolatilities) > 0) {
            $weightedVolatility = 0.0;
            foreach ($walletVolatilities as $item) {
                $weight = $item['valuation'] / $totalValuation;
                $weightedVolatility += $weight * $item['volatility'];
            }
            $volatilite = Number::format($weightedVolatility, 2).' %';
        }

        return [
            'plusValue' => Number::currency($plusValue, 'EUR'),
            'plusValuePercentage' => Number::format($plusValuePercentage, 2).' %',
            'plusValuePositive' => $plusValue >= 0,
            'realizedGain' => Number::currency($totalRealizedGain, 'EUR'),
            'realizedGainPositive' => $totalRealizedGain >= 0,
            'fees' => Number::currency($totalFees, 'EUR'),
            'feesPercentage' => Number::format($feesPercentage, 2).' %',
            'volatilite' => $volatilite,
        ];
    }
}
