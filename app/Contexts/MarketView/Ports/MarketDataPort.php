<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\MarketView\Datas\InstrumentMetaData;
use App\Contexts\MarketView\Datas\InstrumentSummaryData;
use App\Contexts\MarketView\Datas\PriceHistoryData;
use App\Contexts\MarketView\Datas\SectorWeightData;
use Illuminate\Support\Carbon;

interface MarketDataPort
{
    /** @return list<InstrumentSummaryData> */
    public function listInstruments(): array;

    public function findInstrument(int $id): ?InstrumentMetaData;

    public function latestPrice(int $id): ?float;

    public function priceHistory(int $id, Carbon $since): PriceHistoryData;

    /**
     * Closing prices of several instruments since a date, ordered by date, keyed by instrument.
     * Instruments without any price in the window are absent from the map.
     *
     * @param  array<int>  $assetIds
     * @return array<int, list<float>>
     */
    public function closeSeriesSince(array $assetIds, Carbon $since): array;

    /** @return list<SectorWeightData> */
    public function sectors(int $id): array;
}
