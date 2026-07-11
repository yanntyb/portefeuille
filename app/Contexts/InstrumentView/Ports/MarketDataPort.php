<?php

namespace App\Contexts\InstrumentView\Ports;

use App\Contexts\InstrumentView\Datas\InstrumentMetaData;
use App\Contexts\InstrumentView\Datas\PriceHistoryData;
use Illuminate\Support\Carbon;

interface MarketDataPort
{
    /** @return list<\App\Contexts\InstrumentView\Datas\InstrumentSummaryData> */
    public function listInstruments(): array;

    public function findInstrument(int $id): ?InstrumentMetaData;

    public function latestPrice(int $id): ?float;

    public function priceHistory(int $id, Carbon $since): PriceHistoryData;

    /** @return list<\App\Contexts\InstrumentView\Datas\SectorWeightData> */
    public function sectors(int $id): array;
}
