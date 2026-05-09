<?php

namespace App\Domains\Asset\Infrastructure\Adapters;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Ports\AssetPriceProviderPort;
use Illuminate\Support\Collection;

readonly class DatabaseAssetPriceAdapter implements AssetPriceProviderPort
{
    public function __construct(
        private AssetPriceRepositoryInterface $repository,
    ) {}

    public function getCurrentPrice(int $assetId): ?float
    {
        $price = $this->repository->findLatestForAsset($assetId);

        return $price?->close ? (float) $price->close : null;
    }

    public function getPriceHistory(int $assetId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $prices = $this->repository->forAssetSince($assetId, $startDate ? \Carbon\Carbon::parse($startDate) : now()->subYear());

        if ($endDate !== null) {
            $prices = $prices->filter(fn ($p) => $p->date->format('Y-m-d') <= $endDate);
        }

        return $prices->map(fn ($price) => [
            'date' => $price->date->format('Y-m-d'),
            'open' => (float) $price->open,
            'high' => (float) $price->high,
            'low' => (float) $price->low,
            'close' => (float) $price->close,
            'volume' => (int) $price->volume,
        ]);
    }

    public function supports(AssetType $type): bool
    {
        return in_array($type, [AssetType::Stock, AssetType::ETF, AssetType::Bond, AssetType::Crypto, AssetType::RealEstate, AssetType::Savings]);
    }
}
