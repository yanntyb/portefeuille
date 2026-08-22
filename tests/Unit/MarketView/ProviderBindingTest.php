<?php

use App\Contexts\MarketView\Infrastructure\MarketData;
use App\Contexts\MarketView\Infrastructure\PortfolioHoldings;
use App\Contexts\MarketView\Infrastructure\PortfolioTransactions;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\MarketView\Ports\TransactionsPort;

it('binds each MarketView port to its adapter', function () {
    expect(app(MarketDataPort::class))->toBeInstanceOf(MarketData::class);
    expect(app(HoldingsPort::class))->toBeInstanceOf(PortfolioHoldings::class);
    expect(app(TransactionsPort::class))->toBeInstanceOf(PortfolioTransactions::class);
});
