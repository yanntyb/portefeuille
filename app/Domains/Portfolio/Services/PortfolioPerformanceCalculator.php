<?php

namespace App\Domains\Portfolio\Services;

use App\Domains\Analytics\Enums\PerformancePeriod;
use App\Domains\Portfolio\Contracts\PortfolioPerformanceCalculating;
use App\Domains\Portfolio\Data\PortfolioContext;
use App\Domains\Portfolio\Enums\TransactionType;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Contracts\SecurityPriceRepositoryInterface;
use App\Domains\Security\Models\Security;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

class PortfolioPerformanceCalculator implements PortfolioPerformanceCalculating
{
    public function __construct(
        private TransactionAggregator $aggregator,
        private SecurityPriceRepositoryInterface $priceRepository,
    ) {}

    /**
     * @param  Collection<int, Security>  $securities  Securities with total_quantity and latestPrice loaded
     * @return array<string, float|null> Keyed by PerformancePeriod value
     */
    public function computeReturns(Collection $securities): array
    {
        $securityIds = $securities->pluck('id')->all();

        $transactions = Transaction::query()
            ->whereIn('asset_id', $securityIds)
            ->orderBy('date')
            ->get();

        $context = new PortfolioContext(
            securities: $securities,
            transactions: $transactions,
            priceMap: $this->loadPriceMap($securityIds),
            cumulativeQuantities: $this->aggregator->buildCumulatives($transactions)->quantities,
        );

        $endValuation = $this->computeCurrentValuation($securities);

        return $this->computeAllPeriodReturns($context, $endValuation);
    }

    /**
     * @return array<string, float|null> Keyed by PerformancePeriod value
     */
    public function computeReturnsForSecurity(Security $security, ?int $walletId = null): array
    {
        $transactionsQuery = Transaction::query()
            ->where('asset_id', $security->id)
            ->orderBy('date');

        if ($walletId) {
            $transactionsQuery->where('wallet_id', $walletId);
        }

        $transactions = $transactionsQuery->get();

        $totalQuantity = (float) $transactions
            ->sum(fn (Transaction $t) => $t->type === TransactionType::Sell ? -(float) $t->quantity : (float) $t->quantity);

        $security->loadMissing('latestPrice');
        $close = $security->latestPrice?->close;
        $endValuation = ($close !== null) ? $totalQuantity * (float) $close : 0;

        $context = new PortfolioContext(
            securities: collect([$security]),
            transactions: $transactions,
            priceMap: $this->loadPriceMap([$security->id]),
            cumulativeQuantities: $this->aggregator->buildCumulatives($transactions)->quantities,
        );

        return $this->computeAllPeriodReturns($context, $endValuation);
    }

    /**
     * @return array<string, float|null> Keyed by PerformancePeriod value
     */
    private function computeAllPeriodReturns(PortfolioContext $context, float $endValuation): array
    {
        $results = [];

        foreach (PerformancePeriod::cases() as $period) {
            $results[$period->value] = $this->computeReturnForPeriod($context, $period, $endValuation);
        }

        return $results;
    }

    private function computeReturnForPeriod(PortfolioContext $context, PerformancePeriod $period, float $endValuation): ?float
    {
        $startDate = $period->startDate()->format('Y-m-d');

        $startValuation = $this->computeValuation($context, $startDate);

        if ($startValuation <= 0) {
            return null;
        }

        $flowDates = $context->transactions
            ->filter(fn (Transaction $t) => $t->date->format('Y-m-d') > $startDate)
            ->groupBy(fn (Transaction $t) => $t->date->format('Y-m-d'));

        $getValuation = fn (string $date, bool $excludeDate): float => $this->computeValuation($context, $date, $excludeDate);

        return $this->chainSubPeriodReturns($startValuation, $endValuation, $flowDates, $getValuation);
    }

    /**
     * @param  Collection<string, Collection<int, Transaction>>  $flowDates  Transactions grouped by date
     * @param  callable(string, bool): float  $getValuation  Returns valuation at date (second param: excludeDate)
     */
    private function chainSubPeriodReturns(float $startValuation, float $endValuation, Collection $flowDates, callable $getValuation): float
    {
        $product = 1.0;
        $subPeriodStartValuation = $startValuation;

        foreach ($flowDates as $date => $dayTransactions) {
            $valuationBeforeFlows = $getValuation($date, true);

            if ($subPeriodStartValuation > 0) {
                $product *= $valuationBeforeFlows / $subPeriodStartValuation;
            }

            $subPeriodStartValuation = $getValuation($date, false);
        }

        if ($subPeriodStartValuation > 0) {
            $product *= $endValuation / $subPeriodStartValuation;
        }

        return round(($product - 1) * 100, 2);
    }

    private function computeValuation(PortfolioContext $context, string $date, bool $excludeDate = false): float
    {
        $valuation = 0;

        foreach ($context->securities as $security) {
            $quantity = $this->aggregator->getQuantityAtDate($context->cumulativeQuantities, $security->id, $date, $excludeDate);

            if ($quantity <= 0) {
                continue;
            }

            $price = $this->getClosestPrice($context->priceMap, $security->id, $date);

            if ($price === null) {
                continue;
            }

            $valuation += $quantity * $price;
        }

        return $valuation;
    }

    /**
     * @param  array<string, float|null>  $returns  Keyed by PerformancePeriod value
     * @return list<array{label: string, value: string, color: string}>
     */
    public static function formatReturnsAsStats(array $returns): array
    {
        $stats = [];

        foreach (PerformancePeriod::cases() as $period) {
            $value = $returns[$period->value];

            if ($value === null) {
                $stats[] = [
                    'label' => $period->getLabel(),
                    'value' => '—',
                    'color' => 'gray',
                ];

                continue;
            }

            $formatted = ($value >= 0 ? '+' : '').Number::format($value, 2).' %';

            $stats[] = [
                'label' => $period->getLabel(),
                'value' => $formatted,
                'color' => $value >= 0 ? 'success' : 'danger',
            ];
        }

        return $stats;
    }

    /**
     * @param  list<int>  $securityIds
     * @return array<int, list<array{date: string, close: float}>>
     */
    private function loadPriceMap(array $securityIds): array
    {
        $prices = $this->priceRepository->getForSecurities($securityIds);

        $map = [];

        foreach ($prices as $price) {
            $map[$price->security_id][] = [
                'date' => $price->date->format('Y-m-d'),
                'close' => (float) $price->close,
            ];
        }

        return $map;
    }

    /**
     * @param  array<int, list<array{date: string, close: float}>>  $priceMap
     */
    private function getClosestPrice(array $priceMap, int $securityId, string $date): ?float
    {
        if (! isset($priceMap[$securityId])) {
            return null;
        }

        $closestClose = null;

        foreach ($priceMap[$securityId] as $entry) {
            if ($entry['date'] > $date) {
                break;
            }
            $closestClose = $entry['close'];
        }

        return $closestClose;
    }

    /**
     * @param  Collection<int, Security>  $securities
     */
    private function computeCurrentValuation(Collection $securities): float
    {
        return $securities->sum(fn ($security) => $security->currentValuation());
    }
}
