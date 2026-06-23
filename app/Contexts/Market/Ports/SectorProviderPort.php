<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\InstrumentType;

interface SectorProviderPort
{
    /**
     * Get sector allocations for asset by ticker symbol.
     * Stocks: one entry at weight 1.0. ETFs: multiple entries.
     *
     * @return array<int, SectorAllocationData>
     */
    public function getSectorAllocations(string $symbol, InstrumentType $type): array;

    /**
     * Check if adapter supports this asset type
     */
    public function supports(InstrumentType $type): bool;
}
