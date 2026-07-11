<?php

use App\Contexts\InstrumentView\Infrastructure\MarketData;
use App\Contexts\InstrumentView\Infrastructure\PortfolioHoldings;
use App\Contexts\InstrumentView\Infrastructure\PortfolioTransactions;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\InstrumentView\Ports\TransactionsPort;

it('binds each InstrumentView port to its adapter', function () {
    expect(app(MarketDataPort::class))->toBeInstanceOf(MarketData::class);
    expect(app(HoldingsPort::class))->toBeInstanceOf(PortfolioHoldings::class);
    expect(app(TransactionsPort::class))->toBeInstanceOf(PortfolioTransactions::class);
});
