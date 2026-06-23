<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Enums\InstrumentType;

interface InstrumentProviderPort
{
    /**
     * Fetch asset metadata from external source by ticker symbol
     */
    public function findBySymbol(string $symbol, InstrumentType $type): ?InstrumentData;

    /**
     * Check if adapter supports this asset type
     */
    public function supports(InstrumentType $type): bool;
}
