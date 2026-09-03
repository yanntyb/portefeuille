<?php

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Contracts\SectorRepositoryContract;
use App\Contexts\Market\Infrastructure\CacheMarketSyncState;
use App\Contexts\Market\Infrastructure\EloquentDividendRepository;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Infrastructure\EloquentSectorRepository;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Ports\DividendFeedPort;
use App\Contexts\Market\Ports\InstrumentProviderPort;
use App\Contexts\Market\Ports\MarketSyncStatePort;
use App\Contexts\Market\Ports\PriceFeedPort;
use App\Contexts\Market\Ports\SectorProviderPort;

it('binds each Market contract and port to its adapter', function () {
    expect(app(InstrumentRepositoryContract::class))->toBeInstanceOf(EloquentInstrumentRepository::class)
        ->and(app(PriceRepositoryContract::class))->toBeInstanceOf(EloquentPriceRepository::class)
        ->and(app(SectorRepositoryContract::class))->toBeInstanceOf(EloquentSectorRepository::class)
        ->and(app(InstrumentProviderPort::class))->toBeInstanceOf(YahooFinanceAdapter::class)
        ->and(app(SectorProviderPort::class))->toBeInstanceOf(YahooFinanceAdapter::class)
        ->and(app(PriceFeedPort::class))->toBeInstanceOf(YahooFinanceAdapter::class)
        ->and(app(DividendRepositoryContract::class))->toBeInstanceOf(EloquentDividendRepository::class)
        ->and(app(DividendFeedPort::class))->toBeInstanceOf(YahooFinanceAdapter::class)
        ->and(app(MarketSyncStatePort::class))->toBeInstanceOf(CacheMarketSyncState::class);
});
