<?php

namespace App\Domains\Asset\Services;

use App\Domains\Asset\Models\AssetPrice;

readonly class PricePersister
{
    private const BATCH_SIZE = 100;

    /**
     * Persist price data in batches, ignoring duplicates.
     *
     * @param  array<int, array{asset_id: int, date: string, open: string, high: string, low: string, close: string, volume: int, created_at: string, updated_at: string}>  $prices
     */
    public function persist(array $prices): void
    {
        foreach (array_chunk($prices, self::BATCH_SIZE) as $chunk) {
            AssetPrice::insertOrIgnore($chunk);
        }
    }
}
