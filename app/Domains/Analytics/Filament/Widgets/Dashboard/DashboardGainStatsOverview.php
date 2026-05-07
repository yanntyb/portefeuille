<?php

namespace App\Domains\Analytics\Filament\Widgets\Dashboard;

use App\Domains\Analytics\Services\VolatilityCalculator;
use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Infrastructure\Filament\Concerns\ComputesGainStats;
use App\Infrastructure\Filament\Concerns\HasStatWidgetListeners;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;

class DashboardGainStatsOverview extends Widget
{
    use ComputesGainStats;
    use HasStatWidgetListeners;

    protected string $view = 'filament.widgets.gain-stats-overview';

    protected function resolveGainSecurities(): Collection
    {
        $provider = app(DashboardDataProvider::class);
        $allSecurities = new Collection;

        foreach ($provider->wallets() as $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            if ($this->shownSecurityIds !== null) {
                $securities = $securities->whereIn('id', $this->shownSecurityIds);
            }

            $allSecurities = $allSecurities->merge($securities);
        }

        return $allSecurities;
    }

    protected function resolveVolatilityValue(): ?string
    {
        $provider = app(DashboardDataProvider::class);

        $totalValuation = 0;
        $walletVolatilities = [];

        foreach ($provider->wallets() as $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            if ($this->shownSecurityIds !== null) {
                $securities = $securities->whereIn('id', $this->shownSecurityIds);
            }

            $walletValuation = $securities->sum(fn ($security) => $security->currentValuation());
            $totalValuation += $walletValuation;

            $walletVolatility = app(VolatilityCalculator::class)->forWallet($wallet->id);
            $walletVolatilities[] = [
                'valuation' => $walletValuation,
                'volatility' => $walletVolatility,
            ];
        }

        if ($totalValuation === 0 || count($walletVolatilities) === 0) {
            return null;
        }

        if ($totalValuation <= 0) {
            return null;
        }

        $weightedVolatility = 0.0;
        foreach ($walletVolatilities as $item) {
            $weight = $item['valuation'] / $totalValuation;
            $weightedVolatility += $weight * $item['volatility'];
        }

        return Number::format($weightedVolatility, 2).' %';
    }
}
