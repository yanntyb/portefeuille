<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use Illuminate\Support\Carbon;

class MarketPriceHistory implements PriceHistoryPort
{
    public function __construct(private PriceRepositoryContract $prices) {}

    /**
     * @param  list<int>  $assetIds
     * @return list<PriceRecordData>
     */
    public function forAssetsSince(array $assetIds, Carbon $since): array
    {
        return array_map(
            fn (array $row): PriceRecordData => new PriceRecordData(
                assetId: $row['assetId'],
                date: $row['date'],
                close: $row['close'],
            ),
            $this->prices->dailyClosesForAssetsSince($assetIds, $since),
        );
    }
}
