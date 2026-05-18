<?php

use App\Domains\Asset\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Models\Assets\Stock;

it('creates stock from security', function () {
    $security = Stock::factory()
        ->withInfos(fn (StockAssetInfoFactory $f) => $f->state([
            'ticker' => 'AAPL',
            'isin' => 'US0378331005',
        ]))
        ->create(['name' => 'Apple Inc']);

    $stock = Stock::find($security->id);

    expect($stock)->not->toBeNull()
        ->and($stock->name)->toBe('Apple Inc')
        ->and($stock->infos->ticker)->toBe('AAPL')
        ->and($stock->infos->isin)->toBe('US0378331005');
});

