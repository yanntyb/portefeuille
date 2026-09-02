<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSeriesData;

/**
 * La valeur d'une enveloppe dans le temps. Une enveloppe à la fois : le patrimoine entier se lit
 * depuis le tableau de bord, pas d'ici.
 *
 * `Wealth` ne connaît pas `Valuation` : ce port cache `BuildExposureSeries` et son filtre par
 * enveloppe, comme `AccountsPort` cache `GetAccountBreakdown`.
 */
interface ValuationPort
{
    public function seriesForWallet(int $userId, int $walletId): ClassSeriesData;
}
