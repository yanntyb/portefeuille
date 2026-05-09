<?php

namespace App\Domains\Asset\Services;

use App\Domains\Asset\ValueObjects\PriceData;
use Illuminate\Support\Collection;

readonly class PriceDataTransformer
{
    /**
     * Transform raw price data from adapter to normalized value objects.
     *
     * @param  Collection<int, array{date: string, close: float, open?: float, high?: float, low?: float, volume?: int}>  $priceHistory
     * @return Collection<int, PriceData>
     */
    public function transform(Collection $priceHistory): Collection
    {
        return $priceHistory
            ->map(fn (array $data) => PriceData::fromArray($data));
    }
}
