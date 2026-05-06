<?php

namespace App\Filament\Widgets\Dashboard;

use App\Domains\Portfolio\Services\DashboardDataProvider;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;
use Livewire\Attributes\On;

class DashboardValuationWidget extends Widget
{
    protected string $view = 'filament.widgets.valuation-stats-overview';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    #[On('prices-updated')]
    public function refreshStats(): void
    {
        // Re-renders the widget with fresh data
    }

    /**
     * @return array{valuation: string, color: string}
     */
    public function getValuationData(): array
    {
        $provider = app(DashboardDataProvider::class);

        $totalValuation = 0;
        $totalInvested = 0;

        foreach ($provider->wallets() as $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            $totalValuation += $securities->sum(fn ($security) => $security->currentValuation());

            $totalInvested += $securities->sum(fn ($security) => (float) ($security->total_invested ?? 0));
        }

        return [
            'valuation' => Number::currency($totalValuation, 'EUR'),
            'color' => $totalValuation >= $totalInvested ? 'success' : 'danger',
        ];
    }
}
