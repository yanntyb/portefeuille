<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;

/**
 * Le compte espèces de l'utilisateur, toutes enveloppes confondues. `Wealth` ne connaît pas
 * `Portfolio` : ce port cache `GetCashMovements` et `CashLedger`, qui restent internes à ce
 * contexte, comme `AccountsPort` cache `GetAccountBreakdown`.
 */
interface CashPort
{
    public function snapshotFor(int $userId): ClassSnapshotData;

    public function seriesFor(int $userId): ClassSeriesData;
}
