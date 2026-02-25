<?php

namespace App\Services;

use App\Enums\PerformancePeriod;
use App\Models\SecurityPrice;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class PortfolioPerformanceCalculator
{
    /**
     * @param  Collection<int, \App\Models\Security>  $securities  Securities with total_quantity and latestPrice loaded
     * @return array<string, float|null> Keyed by PerformancePeriod value
     */
    public function computeReturns(Collection $securities): array
    {
        $securityIds = $securities->pluck('id')->all();

        $transactions = Transaction::query()
            ->whereIn('security_id', $securityIds)
            ->orderBy('date')
            ->get();

        $results = [];

        foreach (PerformancePeriod::cases() as $period) {
            $results[$period->value] = $this->computeReturnForPeriod($securities, $transactions, $period);
        }

        return $results;
    }

    /**
     * @return array<string, float|null> Keyed by PerformancePeriod value
     */
    public function computeReturnsForSecurity(\App\Models\Security $security, ?string $accountType = null): array
    {
        $transactionsQuery = Transaction::query()
            ->where('security_id', $security->id)
            ->orderBy('date');

        if ($accountType) {
            $transactionsQuery->where('account_type', $accountType);
        }

        $transactions = $transactionsQuery->get();

        $totalQuantity = (float) $transactions->sum('quantity');

        $security->loadMissing('latestPrice');
        $close = $security->latestPrice?->close;
        $currentValuation = ($close !== null) ? $totalQuantity * (float) $close : 0;

        $results = [];

        foreach (PerformancePeriod::cases() as $period) {
            $results[$period->value] = $this->computeReturnForSingleSecurity(
                $security,
                $transactions,
                $period,
                $currentValuation,
            );
        }

        return $results;
    }

    private function computeReturnForSingleSecurity(
        \App\Models\Security $security,
        Collection $transactions,
        PerformancePeriod $period,
        float $currentValuation,
    ): ?float {
        $startDate = $period->startDate()->format('Y-m-d');
        $cumulativeQuantities = $this->buildCumulativeQuantities($transactions);

        $quantity = $this->getQuantityAtDate($cumulativeQuantities, $security->id, $startDate);

        if ($quantity <= 0) {
            return null;
        }

        $price = SecurityPrice::query()
            ->where('security_id', $security->id)
            ->whereDate('date', '<=', $startDate)
            ->orderByDesc('date')
            ->first();

        if ($price === null) {
            return null;
        }

        $startValuation = $quantity * (float) $price->close;
        $netFlows = $this->computeNetFlows($transactions, $startDate);
        $denominator = $startValuation + $netFlows;

        if ($denominator <= 0) {
            return null;
        }

        return round(($currentValuation - $startValuation - $netFlows) / $denominator * 100, 2);
    }

    /**
     * @param  Collection<int, \App\Models\Security>  $securities
     * @param  Collection<int, Transaction>  $transactions
     */
    private function computeReturnForPeriod(Collection $securities, Collection $transactions, PerformancePeriod $period): ?float
    {
        $startDate = $period->startDate()->format('Y-m-d');
        $securityIds = $securities->pluck('id')->all();

        $startValuation = $this->computeValuationAtDate($securities, $transactions, $startDate, $securityIds);

        if ($startValuation === null) {
            return null;
        }

        $endValuation = $this->computeCurrentValuation($securities);

        $netFlows = $this->computeNetFlows($transactions, $startDate);

        $denominator = $startValuation + $netFlows;

        if ($denominator <= 0) {
            return null;
        }

        return round(($endValuation - $startValuation - $netFlows) / $denominator * 100, 2);
    }

    /**
     * @param  Collection<int, \App\Models\Security>  $securities
     * @param  Collection<int, Transaction>  $transactions
     * @param  list<int>  $securityIds
     */
    private function computeValuationAtDate(Collection $securities, Collection $transactions, string $date, array $securityIds): ?float
    {
        $cumulativeQuantities = $this->buildCumulativeQuantities($transactions);

        $prices = SecurityPrice::query()
            ->whereIn('security_id', $securityIds)
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->get()
            ->groupBy('security_id');

        $valuation = 0;
        $hasAnyPosition = false;

        foreach ($securities as $security) {
            $quantity = $this->getQuantityAtDate($cumulativeQuantities, $security->id, $date);

            if ($quantity <= 0) {
                continue;
            }

            $price = $prices->get($security->id)?->first();

            if ($price === null) {
                continue;
            }

            $hasAnyPosition = true;
            $valuation += $quantity * (float) $price->close;
        }

        return $hasAnyPosition ? $valuation : null;
    }

    /**
     * @param  Collection<int, \App\Models\Security>  $securities
     */
    private function computeCurrentValuation(Collection $securities): float
    {
        return $securities->sum(function ($security) {
            $close = $security->latestPrice?->close;

            if ($close === null || $security->total_quantity === null) {
                return 0;
            }

            return (float) $security->total_quantity * (float) $close;
        });
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function computeNetFlows(Collection $transactions, string $startDate): float
    {
        return $transactions
            ->filter(fn (Transaction $t) => $t->date->format('Y-m-d') > $startDate)
            ->sum(fn (Transaction $t) => (float) $t->quantity * (float) $t->unit_price + (float) $t->fees);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, list<array{date: string, quantity: float}>>
     */
    private function buildCumulativeQuantities(Collection $transactions): array
    {
        $quantities = [];

        foreach ($transactions as $transaction) {
            $date = $transaction->date->format('Y-m-d');
            $securityId = $transaction->security_id;

            if (! isset($quantities[$securityId])) {
                $quantities[$securityId] = [];
            }

            $previous = end($quantities[$securityId]) ?: ['quantity' => 0];
            $quantities[$securityId][] = [
                'date' => $date,
                'quantity' => $previous['quantity'] + (float) $transaction->quantity,
            ];
        }

        return $quantities;
    }

    /**
     * @param  array<int, list<array{date: string, quantity: float}>>  $cumulativeQuantities
     */
    private function getQuantityAtDate(array $cumulativeQuantities, int $securityId, string $date): float
    {
        if (! isset($cumulativeQuantities[$securityId])) {
            return 0;
        }

        $quantity = 0;
        foreach ($cumulativeQuantities[$securityId] as $entry) {
            if ($entry['date'] > $date) {
                break;
            }
            $quantity = $entry['quantity'];
        }

        return $quantity;
    }
}
