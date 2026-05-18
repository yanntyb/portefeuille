<?php

namespace App\Domains\Asset\Ports;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\ValueObjects\SectorAllocation;

interface AssetSectorProviderPort
{
    /**
     * Get sector allocations for asset by ticker symbol.
     * Stocks: one entry at weight 1.0. ETFs: multiple entries.
     *
     * @return array<int, SectorAllocation>
     */
    public function getSectorAllocations(string $symbol, AssetType $type): array;

    /**
     * Check if adapter supports this asset type
     */
    public function supports(AssetType $type): bool;
}
