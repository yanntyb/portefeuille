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
     * Check if the provider exposes metadata for this instrument type.
     */
    public function supportsInstruments(InstrumentType $type): bool;
}
