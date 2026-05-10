<?php

use App\Domains\Asset\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\StockAssetInfo;
use App\Domains\Asset\Models\Assets\Stock;

it('creates stock from security', function () {
    $security = Stock::factory()
        ->withInfos(fn(StockAssetInfoFactory $f) => $f->state([
            'ticker' => 'AAPL',
            'isin' => 'US0378331005',
        ]))
        ->create(['name' => 'Apple Inc']);

    $stock = Stock::find($security->id);

    expect($stock)->not->toBeNull()
        ->and($stock->name)->toBe('Apple Inc')
        ->and($stock->ticker)->toBe('AAPL')
        ->and($stock->isin)->toBe('US0378331005');
});

it('has correct asset type', function () {
    $security = Stock::factory()->create();
    $stock = Stock::find($security->id);

    expect($stock->type->value)->toBe('stock');
});

it('uses security prices for valuation', function () {
    $security = Stock::factory()->create();
    $stock = Stock::find($security->id);

    expect($stock->prices)->not->toBeNull();
});
