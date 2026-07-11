<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;

class MarketInstrumentDirectory implements InstrumentDirectoryPort
{
    /**
     * @param  list<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->whereNotNull('name')
            ->pluck('name', 'id')
            ->all();
    }
}
