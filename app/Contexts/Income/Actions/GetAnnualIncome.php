<?php

namespace App\Contexts\Income\Actions;

use App\Contexts\Income\Datas\AnnualIncomeData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;

class GetAnnualIncome
{
    public function __construct(private IncomeSourceRegistry $sources) {}

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
        /** @var array<int, array<string, float>> $bySourcePerYear */
        $bySourcePerYear = [];

        foreach ($this->sources->receiptsFor($userId, $only) as $receipt) {
            $year = (int) $receipt->date->format('Y');
            $key = $receipt->source->value;
            $bySourcePerYear[$year][$key] = ($bySourcePerYear[$year][$key] ?? 0.0) + $receipt->amount;
        }

        ksort($bySourcePerYear);

        $years = [];

        foreach ($bySourcePerYear as $year => $bySource) {
            $bySource = array_map(fn (float $amount): float => round($amount, 2), $bySource);

            $years[] = new AnnualIncomeData(
                year: $year,
                total: round(array_sum($bySource), 2),
                bySource: $bySource,
            );
        }

        return $years;
    }
}
