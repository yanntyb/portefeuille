<?php

namespace App\Contexts\InstrumentView\Infrastructure;

use App\Contexts\InstrumentView\Datas\InstrumentMetaData;
use App\Contexts\InstrumentView\Datas\InstrumentSummaryData;
use App\Contexts\InstrumentView\Datas\PriceHistoryData;
use App\Contexts\InstrumentView\Datas\SectorWeightData;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Support\Carbon;

class MarketData implements MarketDataPort
{
    public function __construct(private PriceRepositoryContract $prices) {}

    /** @return list<InstrumentSummaryData> */
    public function listInstruments(): array
    {
        return Instrument::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Instrument $instrument) => new InstrumentSummaryData(
                id: $instrument->id,
                name: (string) $instrument->name,
                ticker: $instrument->ticker,
                isin: $instrument->isin,
                type: $instrument->type,
            ))
            ->values()
            ->all();
    }

    public function findInstrument(int $id): ?InstrumentMetaData
    {
        $instrument = Instrument::query()->find($id);

        if ($instrument === null) {
            return null;
        }

        $latest = $this->prices->latestForAsset($id);

        return new InstrumentMetaData(
            id: $instrument->id,
            name: (string) $instrument->name,
            ticker: $instrument->ticker,
            isin: $instrument->isin,
            type: $instrument->type,
            lastPrice: $latest !== null ? (float) $latest->close : null,
            lastPriceDate: $latest !== null ? $latest->date->format('Y-m-d') : null,
        );
    }

    public function latestPrice(int $id): ?float
    {
        $latest = $this->prices->latestForAsset($id);

        return $latest !== null ? (float) $latest->close : null;
    }

    public function priceHistory(int $id, Carbon $since): PriceHistoryData
    {
        $prices = $this->prices->forAssetSince($id, $since);

        return new PriceHistoryData(
            labels: $prices->map(fn (Price $price) => $price->date->format('Y-m-d'))->values()->all(),
            close: $prices->map(fn (Price $price) => (float) $price->close)->values()->all(),
        );
    }

    /** @return list<SectorWeightData> */
    public function sectors(int $id): array
    {
        return SectorAllocation::query()
            ->where('asset_id', $id)
            ->orderByDesc('weight')
            ->get()
            ->map(fn (SectorAllocation $allocation) => new SectorWeightData(
                label: $allocation->sector->getLabel(),
                weight: (float) $allocation->weight,
            ))
            ->values()
            ->all();
    }
}
