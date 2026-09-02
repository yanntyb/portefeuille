<?php

namespace App\Contexts\PortfolioView\Ports;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\PortfolioView\Datas\SectorSliceData;

interface SectorBreakdownPort
{
    /**
     * Les secteurs que traverse un périmètre, les parts étant celles de ce sous-ensemble.
     *
     * @return list<SectorSliceData>
     */
    public function breakdownFor(int $userId, HoldingScope $scope): array;
}
