<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\MarketView\Datas\HoldingSnapshotData;

interface HoldingsPort
{
    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array;
}
