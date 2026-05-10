<?php

use App\Domains\Asset\Models\AssetInfos\StockAssetInfo;
use App\Domains\Asset\Models\Assets\Stock;

it('creates stock from security', function () {
    $security = Stock::factory()->create([
        'name' => 'Apple Inc',
    ]);

    StockAssetInfo::query()->where('asset_id', $security->id)->update([
        'ticker' => 'AAPL',
        'isin' => 'US0378331005',
    ]);

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
