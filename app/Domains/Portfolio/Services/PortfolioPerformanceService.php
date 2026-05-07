<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Analytics\Services\VolatilityCalculator;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Infrastructure\Support\MarketCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class PortfolioPerformanceService
{
    public function __construct(private VolatilityCalculator $volatilityCalculator) {}

    /**
     * @param  array<int>  $hiddenSecurityIds
     * @return array{shown_ids: list<int>, hidden_ids: list<int>, priceless_ids: list<int>}
     */
    public function computeSecurityVisibility(Wallet $wallet, array $hiddenSecurityIds): array
    {
        $allIds = Security::query()
            ->forWallet($wallet)
            ->pluck('securities.id')
            ->all();

        $idsWithPrice = SecurityPrice::query()
            ->whereIn('security_id', $allIds)
            ->where('date', '>=', MarketCalendar::lastTradingDate()->toDateString())
            ->pluck('security_id')
            ->unique()
            ->all();

        $pricelessIds = array_diff($allIds, $idsWithPrice);

        // For priced securities: shown by default, hidden if in hiddenSecurityIds
        // For priceless securities: hidden by default, shown if in hiddenSecurityIds (toggled)
        $shownPriced = array_diff($idsWithPrice, $hiddenSecurityIds);
        $shownPriceless = array_intersect($pricelessIds, $hiddenSecurityIds);
        $shownIds = array_merge($shownPriced, $shownPriceless);

        return [
            'shown_ids' => array_values($shownIds),
            'hidden_ids' => array_values(array_intersect($hiddenSecurityIds, $allIds)),
            'priceless_ids' => array_values($pricelessIds),
        ];
    }

    /**
     * @param  array<int>  $shownSecurityIds
     */
    public function getTotalValuation(Wallet $wallet, array $shownSecurityIds): float
    {
        $query = Security::query()->forWallet($wallet);

        if ($shownSecurityIds) {
            $query->whereIn('securities.id', $shownSecurityIds);
        }

        return (float) $query->with('latestPrice')->get()->sum(fn ($record) => $record->currentValuation());
    }

    public function computeAnnualizedReturn(?Wallet $wallet): float
    {
        if ($wallet === null) {
            return 7.0;
        }

        $records = Security::query()->forWallet($wallet)->with('latestPrice')->get();

        $valuation = (float) $records->sum(fn ($record) => $record->currentValuation());
        $totalInvested = (float) $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));

        if ($totalInvested <= 0 || $valuation <= 0) {
            return 7.0;
        }

        $firstDate = $wallet->transactions()
            ->where('type', 'buy')
            ->min('date');

        if ($firstDate === null) {
            return 7.0;
        }

        $years = Carbon::parse($firstDate)->diffInDays(now()) / 365.25;

        if ($years < 0.5) {
            return 7.0;
        }

        $cagr = (($valuation / $totalInvested) ** (1 / $years) - 1) * 100;

        return round((float) max(0, min(50, $cagr)), 2);
    }

    public function computePortfolioVolatility(?Wallet $wallet): float
    {
        if ($wallet === null) {
            return 15.0;
        }

        return $this->volatilityCalculator->forWallet($wallet);
    }

    /**
     * @param  array<int>  $shownSecurityIds
     */
    public function getFormattedValuation(Wallet $wallet, array $shownSecurityIds): string
    {
        $query = Security::query()->forWallet($wallet);

        if ($shownSecurityIds) {
            $query->whereIn('securities.id', $shownSecurityIds);
        }

        $records = $query->with('latestPrice')->get();

        $valuation = $records->sum(fn ($record) => $record->currentValuation());
        $totalInvested = $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));
        $isPositive = $valuation >= $totalInvested;
        $colorClass = $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';

        return '<span class="'.$colorClass.'">'.Number::currency($valuation, 'EUR').'</span>';
    }
}
