<?php

namespace App\Domains\Asset\Ports;

use App\Domains\Asset\Enums\AssetType;
use Illuminate\Support\Collection;

interface AssetPriceProviderPort
{
    /**
     * Get current price for asset
     */
    public function getCurrentPrice(int $assetId): ?float;

    /**
     * Get historical prices for asset
     *
     * @return Collection<int, array{date: string, close: float, open?: float, high?: float, low?: float, volume?: int}>
     */
    public function getPriceHistory(int $assetId, string $startDate = null, string $endDate = null): Collection;

    /**
     * Check if adapter supports asset type
     */
    public function supports(AssetType $type): bool;
}
