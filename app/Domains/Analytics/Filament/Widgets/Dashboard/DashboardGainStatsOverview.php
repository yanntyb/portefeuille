<?php

namespace App\Domains\Analytics\Filament\Widgets\Dashboard;

use App\Domains\Analytics\Services\VolatilityCalculator;
use App\Domains\Portfolio\Services\DashboardDataProvider;
use App\Domains\Security\Models\Security;
use App\Infrastructure\Filament\Concerns\ComputesGainStats;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;

class DashboardGainStatsOverview extends Widget
{
    use ComputesGainStats;

    protected string $view = 'filament.widgets.gain-stats-overview';

    protected function resolveGainSecurities(): Collection
    {
        $provider = app(DashboardDataProvider::class);
        $securityIds = [];

        foreach ($provider->wallets() as $wallet) {
            $securities = $provider->securitiesForWallet($wallet);

            if ($this->shownSecurityIds !== null) {
                $securities = $securities->whereIn('id', $this->shownSecurityIds);
            }

            $securityIds = array_merge($securityIds, $securities->pluck('id')->all());
        }

        if (empty($securityIds)) {
            return Security::query()->where('id', null)->get();
        }

        return Security::query()->whereIn('id', $securityIds)->get();
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

            $walletVolatility = app(VolatilityCalculator::class)->forWallet($wallet);
            $walletVolatilities[] = [
                'valuation' => $walletValuation,
                'volatility' => $walletVolatility,
            ];
        }

        if ($totalValuation === 0 || count($walletVolatilities) === 0) {
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
