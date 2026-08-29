<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\MarketView\Datas\SectorSliceData;

interface SectorBreakdownPort
{
    /** @return list<SectorSliceData> */
    public function breakdownFor(int $userId): array;
}
