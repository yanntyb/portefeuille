<?php

namespace App\Domains\Asset\Services;

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\ValueObjects\AssetPriceData;
use Illuminate\Support\Collection;

readonly class PricePersister
{
    private const BATCH_SIZE = 100;

    /**
     * Persist price data in batches, ignoring duplicates.
     *
     * @param  Collection<int, AssetPriceData>|array<int, AssetPriceData>  $prices
     */
    public function persist(Collection|array $prices): void
    {
        $collection = $prices instanceof Collection ? $prices : collect($prices);

        $collection
            ->map(fn (AssetPriceData $priceData) => $priceData->toArray())
            ->chunk(self::BATCH_SIZE)
            ->each(fn (Collection $chunk) => AssetPrice::insertOrIgnore($chunk->toArray()));
    }
}
