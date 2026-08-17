<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Ports\PriceProviderPort;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class DatabaseAssetPriceAdapter implements PriceProviderPort
{
    public function __construct(
        private PriceRepositoryContract $repository,
    ) {}

    public function getCurrentPrice(int $assetId): ?float
    {
        $price = $this->repository->latestForAsset($assetId);

        return $price?->close ? (float) $price->close : null;
    }

    public function getPriceHistory(int $assetId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $prices = $this->repository->forAssetSince($assetId, $startDate ? Carbon::parse($startDate) : now()->subYear());

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

    public function supportsPrices(InstrumentType $type): bool
    {
        // Adapter de prix en base : tout instrument marché est supporté.
        return true;
    }
}
