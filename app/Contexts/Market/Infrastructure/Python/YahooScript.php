<?php

namespace App\Contexts\Market\Infrastructure\Python;

enum YahooScript: string
{
    case Prices = 'fetch_prices.py';
    case PricesBulk = 'fetch_prices_bulk.py';
    case DividendsBulk = 'fetch_dividends_bulk.py';
    case Search = 'search_ticker.py';
    case Sectors = 'fetch_sectors.py';

    public function path(): string
    {
        return __DIR__.'/'.$this->value;
    }
}
