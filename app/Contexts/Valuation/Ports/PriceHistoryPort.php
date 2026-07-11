<?php

namespace App\Contexts\Valuation\Ports;

use App\Contexts\Valuation\Datas\PriceRecordData;
use Illuminate\Support\Carbon;

interface PriceHistoryPort
{
    /**
     * @param  list<int>  $assetIds
     * @return list<PriceRecordData>
     */
    public function forAssetsSince(array $assetIds, Carbon $since): array;
}
