<?php

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Stock;

it('stock loads prices relationship', function () {
    $security = Stock::factory()->create();
    AssetPrice::factory(5)->create(['asset_id' => $security->id]);

    $asset = Stock::find($security->id);

    expect($asset->prices)->toHaveCount(5);
});

it('stock calculates current price from latest price record', function () {
    $security = Stock::factory()->create();

    AssetPrice::factory()->create([
        'asset_id' => $security->id,
        'date' => '2026-05-06',
        'close' => 100.0,
    ]);

    AssetPrice::factory()->create([
        'asset_id' => $security->id,
        'date' => '2026-05-08',
        'close' => 125.5,
    ]);

    $asset = Stock::find($security->id);
    $currentPrice = $asset->prices()->latest('date')->value('close');

    expect($currentPrice)->toBe('125.5000');
});

it('stock has correct asset type', function () {
    $security = Stock::factory()->create();
    $stock = Stock::find($security->id);

    expect($stock->type->value)->toBe('stock');
});
