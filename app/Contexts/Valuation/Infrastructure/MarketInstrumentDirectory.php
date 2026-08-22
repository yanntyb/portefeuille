<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;

class MarketInstrumentDirectory implements InstrumentDirectoryPort
{
    /**
     * @param  list<InstrumentType>  $types
     * @return list<int>
     */
    public function idsOfTypes(array $types): array
    {
        if ($types === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('type', array_map(fn (InstrumentType $type): string => $type->value, $types))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

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
