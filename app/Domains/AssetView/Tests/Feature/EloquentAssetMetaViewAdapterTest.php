<?php

use App\Domains\Asset\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Models\Assets\Savings;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\Ports\AssetMetaViewPort;
use App\Domains\AssetView\ValueObjects\AssetMetaDTO;

it('returns asset metadata as DTO', function () {
    $stock = Stock::factory()
        ->withInfos(fn (StockAssetInfoFactory $f) => $f->state([
            'ticker' => 'AAPL',
            'isin' => 'US0378331005',
        ]))
        ->create(['name' => 'Apple Inc']);

    $adapter = app(AssetMetaViewPort::class);
    $dto = $adapter->getMeta($stock->id);

    expect($dto)->not->toBeNull()
        ->and($dto)->toBeInstanceOf(AssetMetaDTO::class)
        ->and($dto->id)->toBe($stock->id)
        ->and($dto->name)->toBe('Apple Inc')
        ->and($dto->ticker)->toBe('AAPL')
        ->and($dto->isin)->toBe('US0378331005');
});

it('returns null for non-existent asset', function () {
    $adapter = app(AssetMetaViewPort::class);
    $dto = $adapter->getMeta(9999);

    expect($dto)->toBeNull();
});

it('handles asset without infos', function () {
    $savings = Savings::factory()->create(['name' => 'Savings Account']);

    $adapter = app(AssetMetaViewPort::class);
    $dto = $adapter->getMeta($savings->id);

    expect($dto)->not->toBeNull()
        ->and($dto->name)->toBe('Savings Account')
        ->and($dto->ticker)->toBeNull()
        ->and($dto->isin)->toBeNull();
});

it('returns all assets as DTOs', function () {
    Stock::factory()->count(2)->create();
    Savings::factory()->count(1)->create();

    $adapter = app(AssetMetaViewPort::class);
    $result = $adapter->getAllAssets();

    expect($result)->toHaveCount(3)
        ->and($result->first())->toBeInstanceOf(AssetMetaDTO::class);
});

it('returns empty collection when no assets exist', function () {
    $adapter = app(AssetMetaViewPort::class);
    $result = $adapter->getAllAssets();

    expect($result)->toHaveCount(0);
});
