<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSnapshotData;

interface HoldingsPort
{
    /** Valeur et prix de revient du portefeuille de titres. */
    public function snapshotFor(int $userId): ClassSnapshotData;
}
