<?php

use App\Contexts\PortfolioView\Infrastructure\MarketData;
use App\Contexts\PortfolioView\Infrastructure\PortfolioHoldings;
use App\Contexts\PortfolioView\Infrastructure\PortfolioTransactions;
use App\Contexts\PortfolioView\Ports\HoldingsPort;
use App\Contexts\PortfolioView\Ports\MarketDataPort;
use App\Contexts\PortfolioView\Ports\TransactionsPort;

it('binds each PortfolioView port to its adapter', function () {
    expect(app(MarketDataPort::class))->toBeInstanceOf(MarketData::class);
    expect(app(HoldingsPort::class))->toBeInstanceOf(PortfolioHoldings::class);
    expect(app(TransactionsPort::class))->toBeInstanceOf(PortfolioTransactions::class);
});
