<?php

namespace App\Domains\AssetView\Infrastructure\Adapters;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\AssetView\DTOs\PriceHistoryDTO;
use App\Domains\AssetView\Ports\AssetPriceViewPort;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EloquentAssetPriceViewAdapter implements AssetPriceViewPort
{
    public function __construct(
        private readonly AssetPriceRepositoryInterface $prices,
    ) {}

    public function getPriceHistory(int $assetId, Carbon $from, Carbon $to): Collection
    {
        return $this->prices->forAssetSince($assetId, $from)
            ->filter(fn ($price) => $price->date <= $to)
            ->map(PriceHistoryDTO::fromModel(...));
    }

    public function getLatestPrice(int $assetId): ?PriceHistoryDTO
    {
        $price = $this->prices->latestForAsset($assetId);

        return $price ? PriceHistoryDTO::fromModel($price) : null;
    }
}
