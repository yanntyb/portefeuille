<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Price;
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
        return $this->prices->forAssets($assetIds, $since)
            ->map(fn (Price $price) => new PriceRecordData(
                assetId: (int) $price->asset_id,
                date: $price->date->format('Y-m-d'),
                close: (float) $price->close,
            ))
            ->values()
            ->all();
    }
}
