<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Infrastructure\Adapters\YahooFinanceAdapter;
use App\Domains\Asset\Ports\AssetProviderPort;
use App\Domains\Asset\Ports\AssetSectorProviderPort;

it('implements AssetProviderPort', function () {
    expect(YahooFinanceAdapter::class)
        ->toImplement(AssetProviderPort::class);
});

it('implements AssetSectorProviderPort', function () {
    expect(YahooFinanceAdapter::class)
        ->toImplement(AssetSectorProviderPort::class);
});

it('supports stock and etf only', function () {
    $adapter = app(YahooFinanceAdapter::class);

    expect($adapter->supports(AssetType::Stock))->toBeTrue()
        ->and($adapter->supports(AssetType::ETF))->toBeTrue()
        ->and($adapter->supports(AssetType::Crypto))->toBeFalse();
});
