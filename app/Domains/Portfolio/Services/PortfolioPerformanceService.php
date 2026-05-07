<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Analytics\Services\VolatilityCalculator;
use App\Domains\Portfolio\Contracts\TransactionRepositoryInterface;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Security\Contracts\SecurityPriceRepositoryInterface;
use App\Domains\Security\Contracts\SecurityRepositoryInterface;
use App\Domains\Security\Models\Security;
use App\Infrastructure\Support\MarketCalendar;
use Illuminate\Support\Number;

class PortfolioPerformanceService
{
    /** @var array<int, \Illuminate\Database\Eloquent\Collection> */
    private array $securitiesCache = [];

    public function __construct(
        private VolatilityCalculator $volatilityCalculator,
        private SecurityRepositoryInterface $securityRepository,
        private SecurityPriceRepositoryInterface $priceRepository,
        private TransactionRepositoryInterface $transactionRepository,
    ) {}

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Security>
     */
    private function getSecurities(Wallet $wallet): \Illuminate\Database\Eloquent\Collection
    {
        return $this->securitiesCache[$wallet->id] ??= $this->securityRepository->forWallet($wallet->id);
    }

    /**
     * @param  array<int>  $hiddenSecurityIds
     * @return array{shown_ids: list<int>, hidden_ids: list<int>, priceless_ids: list<int>}
     */
    public function computeSecurityVisibility(Wallet $wallet, array $hiddenSecurityIds): array
    {
        $allIds = $this->securityRepository->getIdsForWallet($wallet->id);

        $idsWithPrice = $this->priceRepository->getSecurityIdsWithRecentPrice(
            $allIds,
            MarketCalendar::lastTradingDate()->toDateString()
        );

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
        $records = $this->getSecurities($wallet);

        if ($shownSecurityIds) {
            $records = $records->whereIn('id', $shownSecurityIds);
        }

        return (float) $records->sum(fn ($record) => $record->currentValuation());
    }

    public function computeAnnualizedReturn(?Wallet $wallet): float
    {
        if ($wallet === null) {
            return 7.0;
        }

        $records = $this->getSecurities($wallet);

        $valuation = (float) $records->sum(fn ($record) => $record->currentValuation());
        $totalInvested = (float) $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));

        if ($totalInvested <= 0 || $valuation <= 0) {
            return 7.0;
        }

        $firstDate = $this->transactionRepository->getFirstBuyDateForWallet($wallet->id);

        if ($firstDate === null) {
            return 7.0;
        }

        $years = $firstDate->diffInDays(now()) / 365.25;

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

        return $this->volatilityCalculator->forWallet($wallet->id);
    }

    /**
     * @param  array<int>  $shownSecurityIds
     */
    public function getFormattedValuation(Wallet $wallet, array $shownSecurityIds): string
    {
        $records = $this->getSecurities($wallet);

        if ($shownSecurityIds) {
            $records = $records->whereIn('id', $shownSecurityIds);
        }

        $valuation = $records->sum(fn ($record) => $record->currentValuation());
        $totalInvested = $records->sum(fn ($record) => (float) ($record->total_invested ?? 0));
        $isPositive = $valuation >= $totalInvested;
        $colorClass = $isPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';

        return '<span class="'.$colorClass.'">'.Number::currency($valuation, 'EUR').'</span>';
    }
}
