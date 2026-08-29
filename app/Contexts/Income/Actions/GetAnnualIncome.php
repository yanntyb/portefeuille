<?php

namespace App\Contexts\Income\Actions;

use App\Contexts\Income\Datas\AnnualIncomeData;
use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Services\ReceiptTotals;

class GetAnnualIncome
{
    public function __construct(private IncomeSourceRegistry $sources, private ReceiptTotals $totals) {}

    /**
     * Revenu par année civile, de la plus ancienne à la plus récente. Sans `$only`, toutes
     * origines confondues.
     *
     * La ventilation par source est produite dès maintenant, alors qu'une seule origine existe :
     * elle ne coûte rien et évite de refaire l'affichage quand une deuxième arrive.
     *
     * @return list<AnnualIncomeData>
     */
    public function __invoke(int $userId, ?IncomeSource $only = null): array
    {
        $byYear = $this->totals->byYear(
            array_map(fn (IncomeReceiptData $receipt): array => [
                'date' => $receipt->date,
                'amount' => $receipt->amount,
                'source' => $receipt->source->value,
            ], $this->sources->receiptsFor($userId, $only)),
        );

        return array_map(fn (int $year): AnnualIncomeData => new AnnualIncomeData(
            year: $year,
            total: round(array_sum($byYear[$year]), 2),
            bySource: $byYear[$year],
        ), array_keys($byYear));
    }
}
