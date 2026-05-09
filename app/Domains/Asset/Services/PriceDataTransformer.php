<?php

namespace App\Domains\Asset\Services;

use App\Domains\Asset\ValueObjects\AssetPriceData;
use App\Domains\Asset\ValueObjects\PriceData;
use Illuminate\Support\Collection;

readonly class PriceDataTransformer
{
    /**
     * Transform raw price data from adapter to persist-ready format.
     *
     * @param  Collection<int, array{date: string, close: float, open?: float, high?: float, low?: float, volume?: int}>  $priceHistory
     * @return array<int, array{asset_id: int, date: string, open: string, high: string, low: string, close: string, volume: int, created_at: string, updated_at: string}>
     */
    public function transform(int $assetId, Collection $priceHistory): array
    {
        return $priceHistory
            ->map(fn (array $data) => AssetPriceData::fromPriceData(
                $assetId,
                PriceData::fromArray($data)
            )->toArray())
            ->toArray();
    }
}
