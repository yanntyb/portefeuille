<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\PortfolioView\Datas\SectorSliceData;

interface SectorBreakdownPort
{
    /** @return list<SectorSliceData> */
    public function breakdownFor(int $userId): array;
}
