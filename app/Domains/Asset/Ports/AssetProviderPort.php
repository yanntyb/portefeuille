<?php

namespace App\Domains\Asset\Ports;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\ValueObjects\AssetData;

interface AssetProviderPort
{
    /**
     * Fetch asset metadata from external source by ticker symbol
     */
    public function findBySymbol(string $symbol, AssetType $type): ?AssetData;

    /**
     * Check if adapter supports this asset type
     */
    public function supports(AssetType $type): bool;
}
