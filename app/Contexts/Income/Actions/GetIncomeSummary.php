<?php

namespace App\Contexts\Income\Actions;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Datas\IncomeSummaryData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Services\ReceiptTotals;
use Illuminate\Support\Carbon;

class GetIncomeSummary
{
    public function __construct(private IncomeSourceRegistry $sources, private ReceiptTotals $totals) {}

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

        $summary = $this->totals->summarize(
            array_map(fn (IncomeReceiptData $receipt): array => [
                'date' => $receipt->date,
                'amount' => $receipt->amount,
                'source' => $receipt->source->value,
            ], $receipts),
            Carbon::now()->subYear()->startOfDay(),
        );

        return new IncomeSummaryData(
            totalReceived: $summary['total'],
            last12Months: $summary['last12Months'],
            estimatedAnnual: $estimatedAnnual,
            bySource: $summary['bySource'],
        );
    }
}
