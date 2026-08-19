<?php

namespace App\Contexts\Income\Ports;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;

/**
 * Une origine de revenu. Le port ne dit pas si les reçus sont calculés ou lus en base : le
 * dividende les calcule depuis les positions, un loyer les lira.
 */
interface IncomeSourcePort
{
    public function source(): IncomeSource;

    /** @return list<IncomeReceiptData> */
    public function receiptsFor(int $userId): array;

    /**
     * Revenu que cette origine devrait produire sur les douze prochains mois.
     *
     * Extrapolation, jamais une promesse : chaque source décide de la sienne, un dividende depuis
     * ses détachements récents, un loyer depuis son bail.
     */
    public function projectedAnnualFor(int $userId): float;
}
