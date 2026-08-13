<?php

use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Contracts\SectorRepositoryContract;
use App\Contexts\Market\Infrastructure\DatabaseAssetPriceAdapter;
use App\Contexts\Market\Infrastructure\EloquentInstrumentRepository;
use App\Contexts\Market\Infrastructure\EloquentPriceRepository;
use App\Contexts\Market\Infrastructure\EloquentSectorRepository;
use App\Contexts\Market\Infrastructure\YahooFinanceAdapter;
use App\Contexts\Market\Ports\PriceProviderPort;
use App\Contexts\Market\Ports\SectorProviderPort;

it('binds each Market contract and port to its adapter', function () {
    expect(app(InstrumentRepositoryContract::class))->toBeInstanceOf(EloquentInstrumentRepository::class)
        ->and(app(PriceRepositoryContract::class))->toBeInstanceOf(EloquentPriceRepository::class)
        ->and(app(SectorRepositoryContract::class))->toBeInstanceOf(EloquentSectorRepository::class)
        ->and(app(PriceProviderPort::class))->toBeInstanceOf(DatabaseAssetPriceAdapter::class)
        ->and(app(SectorProviderPort::class))->toBeInstanceOf(YahooFinanceAdapter::class);
});
