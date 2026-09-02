<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\PortfolioView\Datas\HoldingSnapshotData;

interface HoldingsPort
{
    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array;
}
