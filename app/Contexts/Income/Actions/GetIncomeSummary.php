<?php

namespace App\Contexts\Income\Actions;

use App\Contexts\Income\Datas\IncomeSummaryData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use Illuminate\Support\Carbon;

class GetIncomeSummary
{
    public function __construct(private IncomeSourceRegistry $sources) {}

    /**
     * Revenu perçu par l'utilisateur. Sans `$only`, toutes origines confondues.
     *
     * Le total ne se mêle jamais au gain latent : les cours stockés sont déjà ajustés des
     * dividendes, et les additionner compterait deux fois une partie du même rendement.
     */
    public function __invoke(int $userId, ?IncomeSource $only = null): IncomeSummaryData
    {
        $receipts = $this->sources->receiptsFor($userId, $only);
        $estimatedAnnual = $this->sources->projectedAnnualFor($userId, $only);

        if ($receipts === [] && $estimatedAnnual <= 0.0) {
            return IncomeSummaryData::empty();
        }

        $since = Carbon::now()->subYear()->startOfDay();
        $total = 0.0;
        $last12Months = 0.0;
        $bySource = [];

        foreach ($receipts as $receipt) {
            $total += $receipt->amount;
            $key = $receipt->source->value;
            $bySource[$key] = ($bySource[$key] ?? 0.0) + $receipt->amount;

            if ($receipt->date->gte($since)) {
                $last12Months += $receipt->amount;
            }
        }

        return new IncomeSummaryData(
            totalReceived: round($total, 2),
            last12Months: round($last12Months, 2),
            estimatedAnnual: $estimatedAnnual,
            bySource: array_map(fn (float $amount): float => round($amount, 2), $bySource),
        );
    }
}
