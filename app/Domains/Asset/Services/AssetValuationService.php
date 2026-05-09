<?php

namespace App\Domains\Asset\Services;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Models\Asset;

readonly class AssetValuationService
{
    public function __construct(
        private AssetPriceRepositoryInterface $priceRepository,
    ) {}

    public function computeCurrentValuation(Asset $asset): float
    {
        if ($asset->total_quantity === null) {
            return 0.0;
        }

        $latestPrice = $asset->latestPrice;

        if ($latestPrice === null) {
            return 0.0;
        }

        return (float) $asset->total_quantity * (float) $latestPrice->close;
    }
}
