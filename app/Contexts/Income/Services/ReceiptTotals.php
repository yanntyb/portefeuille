<?php

namespace App\Contexts\Income\Services;

use Illuminate\Support\Carbon;

/** Ce qu'une suite de reçus dit : son total, sa dernière année, sa ventilation. */
class ReceiptTotals
{
    /**
     * @param  list<array{date: Carbon, amount: float, source: string}>  $receipts
     * @return array{total: float, last12Months: float, bySource: array<string, float>}
     */
    public function summarize(array $receipts, Carbon $since): array
    {
        $total = 0.0;
        $last12Months = 0.0;
        $bySource = [];

        foreach ($receipts as $receipt) {
            $total += $receipt['amount'];
            $bySource[$receipt['source']] = ($bySource[$receipt['source']] ?? 0.0) + $receipt['amount'];

            if ($receipt['date']->gte($since)) {
                $last12Months += $receipt['amount'];
            }
        }

        return [
            'total' => round($total, 2),
            'last12Months' => round($last12Months, 2),
            'bySource' => array_map(fn (float $amount): float => round($amount, 2), $bySource),
        ];
    }

    /**
     * @param  list<array{date: Carbon, amount: float, source: string}>  $receipts
     * @return array<int, array<string, float>> Années croissantes.
     */
    public function byYear(array $receipts): array
    {
        $byYear = [];

        foreach ($receipts as $receipt) {
            $year = (int) $receipt['date']->format('Y');
            $source = $receipt['source'];
            $byYear[$year][$source] = ($byYear[$year][$source] ?? 0.0) + $receipt['amount'];
        }

        ksort($byYear);

        return array_map(
            fn (array $bySource): array => array_map(fn (float $amount): float => round($amount, 2), $bySource),
            $byYear,
        );
    }
}
