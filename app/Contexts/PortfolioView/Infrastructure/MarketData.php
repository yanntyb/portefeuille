<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\PortfolioView\Datas\InstrumentMetaData;
use App\Contexts\PortfolioView\Datas\InstrumentSummaryData;
use App\Contexts\PortfolioView\Datas\PriceHistoryData;
use App\Contexts\PortfolioView\Datas\SectorWeightData;
use App\Contexts\PortfolioView\Ports\MarketDataPort;
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

    /** @return list<InstrumentSummaryData> */
    public function instrumentsOfClass(AssetClass $class): array
    {
        return Instrument::query()
            ->where('asset_class', $class->value)
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

    /**
     * @param  list<int>  $assetIds
     * @return array<int, float>
     */
    public function latestPricesFor(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return $this->prices->latestClosesForAssets($assetIds);
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
            assetClass: $instrument->asset_class,
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

    /**
     * @param  array<int>  $assetIds
     * @return array<int, list<float>>
     */
    public function closeSeriesSince(array $assetIds, Carbon $since): array
    {
        return $this->prices->closesForAssetsSince($assetIds, $since);
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

    /**
     * @param  list<int>  $assetIds
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $assetIds, array $classes): array
    {
        if ($assetIds === [] || $classes === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->whereIn('asset_class', array_map(fn (AssetClass $class): string => $class->value, $classes))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
