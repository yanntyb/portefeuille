<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Enums\InstrumentType;

interface PriceFeedPort
{
    /**
     * Check if the feed covers this instrument type.
     */
    public function supportsPriceFeed(InstrumentType $type): bool;

    /**
     * Fetch daily prices for several tickers at once.
     *
     * A ticker with no bar over its window is absent from the result: the feed
     * cannot tell a closed market from a dead ticker.
     *
     * @param  array<int, PriceRequestData>  $requests
     * @return array<string, array<int, PriceData>> prices keyed by ticker
     *
     * @throws PriceFeedException when the whole fetch fails
     */
    public function fetchPrices(array $requests): array;
}
