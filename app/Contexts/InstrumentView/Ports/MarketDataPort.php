<?php

namespace App\Contexts\InstrumentView\Ports;

use App\Contexts\InstrumentView\Datas\InstrumentMetaData;
use App\Contexts\InstrumentView\Datas\InstrumentSummaryData;
use App\Contexts\InstrumentView\Datas\PriceHistoryData;
use App\Contexts\InstrumentView\Datas\SectorWeightData;
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
