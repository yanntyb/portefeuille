<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;

class MarketInstrumentDirectory implements InstrumentDirectoryPort
{
    /**
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $classes): array
    {
        if ($classes === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('asset_class', array_map(fn (AssetClass $class): string => $class->value, $classes))
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
