<?php

namespace App\Services;

use App\Data\CumulativeData;
use App\Data\DailyValuations;
use App\Infrastructure\Data\TimeSeriesPoint;
use App\Enums\TransactionType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TransactionAggregator
{
    public function buildCumulatives(Collection $transactions): CumulativeData
    {
        $quantities = [];
        $invested = [];
        $fees = [];
        $totalInvested = 0;
        $totalFees = 0;
        $buyQuantityBySecurityId = [];

        foreach ($transactions as $transaction) {
            $date = $transaction->date->format('Y-m-d');
            $securityId = $transaction->security_id;
            $isSell = $transaction->type === TransactionType::Sell;

            if (! isset($quantities[$securityId])) {
                $quantities[$securityId] = [];
            }

            $previous = end($quantities[$securityId]) ?: null;
            $previousValue = $previous?->value ?? 0;
            $delta = $isSell ? -(float) $transaction->quantity : (float) $transaction->quantity;
            $quantities[$securityId][] = new TimeSeriesPoint($date, $previousValue + $delta);

            if ($isSell) {
                $buyQty = $buyQuantityBySecurityId[$securityId]['quantity'] ?? 0;
                $buyCost = $buyQuantityBySecurityId[$securityId]['cost'] ?? 0;
                $pru = $buyQty > 0 ? $buyCost / $buyQty : 0;
                $totalInvested -= (float) $transaction->quantity * $pru - (float) $transaction->fees;
            } else {
                $buyQuantityBySecurityId[$securityId]['quantity'] = ($buyQuantityBySecurityId[$securityId]['quantity'] ?? 0) + (float) $transaction->quantity;
                $buyQuantityBySecurityId[$securityId]['cost'] = ($buyQuantityBySecurityId[$securityId]['cost'] ?? 0) + (float) $transaction->quantity * (float) $transaction->unit_price;
                $totalInvested += (float) $transaction->quantity * (float) $transaction->unit_price + (float) $transaction->fees;
            }

            $invested[] = new TimeSeriesPoint($date, $totalInvested);

            $totalFees += (float) $transaction->fees;
            $fees[] = new TimeSeriesPoint($date, $totalFees);
        }

        return new CumulativeData($quantities, $invested, $fees);
    }

    /**
     * @param  array<int, list<TimeSeriesPoint>>  $cumulativeQuantities
     */
    public function getQuantityAtDate(array $cumulativeQuantities, int $securityId, string $date, bool $excludeDate = false): float
    {
        if (! isset($cumulativeQuantities[$securityId])) {
            return 0;
        }

        $quantity = 0;
        foreach ($cumulativeQuantities[$securityId] as $entry) {
            if ($excludeDate ? $entry->date >= $date : $entry->date > $date) {
                break;
            }
            $quantity = $entry->value;
        }

        return $quantity;
    }

    /**
     * @param  list<TimeSeriesPoint>  $cumulativeSeries
     */
    public function getValueAtDate(array $cumulativeSeries, string $date): float
    {
        $value = 0;
        foreach ($cumulativeSeries as $entry) {
            if ($entry->date > $date) {
                break;
            }
            $value = $entry->value;
        }

        return $value;
    }

    public function computeDailyValuations(
        Collection $prices,
        CumulativeData $cumulative,
        array $securityIds,
    ): DailyValuations {
        $days = $prices
            ->map(fn ($price) => Carbon::parse($price->date)->format('Y-m-d'))
            ->unique()
            ->sort()
            ->values();

        $pricesByDayAndSecurity = $prices->groupBy(
            fn ($p) => Carbon::parse($p->date)->format('Y-m-d'),
        )->map(fn (Collection $group) => $group->keyBy('security_id'));

        $labels = [];
        $valuations = [];
        $invested = [];
        $fees = [];
        $lastCloseBySecurityId = [];

        foreach ($days as $day) {
            $valuation = 0;
            $pricesForDay = $pricesByDayAndSecurity->get($day, collect());

            foreach ($securityIds as $securityId) {
                $price = $pricesForDay->get($securityId);
                if ($price) {
                    $lastCloseBySecurityId[$securityId] = (float) $price->close;
                }

                $close = $lastCloseBySecurityId[$securityId] ?? null;
                if ($close === null) {
                    continue;
                }

                $quantity = $this->getQuantityAtDate($cumulative->quantities, $securityId, $day);
                $valuation += $quantity * $close;
            }

            $labels[] = $day;
            $valuations[] = round($valuation, 2);
            $invested[] = round($this->getValueAtDate($cumulative->invested, $day), 2);
            $fees[] = round($this->getValueAtDate($cumulative->fees, $day), 2);
        }

        return new DailyValuations($labels, $valuations, $invested, $fees);
    }
}
