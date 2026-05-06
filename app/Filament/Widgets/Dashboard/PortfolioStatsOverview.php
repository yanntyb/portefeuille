<?php

namespace App\Filament\Widgets\Dashboard;

use App\Services\DashboardDataProvider;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class PortfolioStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    #[On('prices-updated')]
    public function refreshStats(): void
    {
        // Re-renders the widget by calling getStats() again
    }

    protected function getStats(): array
    {
        $provider = app(DashboardDataProvider::class);

        $totalValuation = 0;
        $totalInvested = 0;
        $totalFees = 0;

        foreach ($provider->wallets() as $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            $totalValuation += $securities->sum(fn ($security) => $security->currentValuation());

            $totalInvested += $securities->sum(fn ($security) => (float) ($security->total_invested ?? 0));
            $totalFees += $securities->sum(fn ($security) => (float) ($security->total_fees ?? 0));
        }

        $plusValue = $totalValuation - $totalInvested;
        $plusValuePercentage = $totalInvested > 0 ? ($plusValue / $totalInvested) * 100 : 0;
        $feesPercentage = $totalInvested > 0 ? ($totalFees / $totalInvested) * 100 : 0;

        return [
            Stat::make('Valorisation', Number::currency($totalValuation, 'EUR')),
            Stat::make('Plus-value', Number::currency($plusValue, 'EUR'))
                ->description(Number::format($plusValuePercentage, 2).' %')
                ->color($plusValue >= 0 ? 'success' : 'danger'),
            Stat::make('Frais', Number::currency($totalFees, 'EUR'))
                ->description(Number::format($feesPercentage, 2).' %')
                ->color('danger'),
        ];
    }
}
