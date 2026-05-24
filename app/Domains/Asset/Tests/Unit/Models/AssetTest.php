<?php

use App\Domains\Asset\Factories\AssetPriceFactory;
use App\Domains\Asset\Models\Assets\Stock;

it('stock loads prices relationship', function () {
    $security = Stock::factory()
        ->withPrices(fn (AssetPriceFactory $f) => $f->count(5))
        ->create();

    $asset = Stock::find($security->id);

    expect($asset->prices)->toHaveCount(5);
});

it('stock calculates current price from latest price record', function () {
    $asset = Stock::factory()
        ->withPrices(fn (AssetPriceFactory $f) => $f->state([
            'date' => '2026-05-06',
            'close' => 100.0,
        ]))
        ->withPrices(fn (AssetPriceFactory $f) => $f->state([
            'date' => '2026-05-08',
            'close' => 125.5,
        ]))
        ->create();

    $currentPrice = $asset->prices()->latest('date')->value('close');

    expect($currentPrice)->toBe('125.5000');
});
