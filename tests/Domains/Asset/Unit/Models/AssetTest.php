<?php

use App\Domains\Asset\Models\Stock;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;

it('stock loads prices relationship', function () {
    $security = Security::factory()->create();
    SecurityPrice::factory(5)->create(['security_id' => $security->id]);

    $asset = Stock::find($security->id);

    expect($asset->prices)->toHaveCount(5);
});

it('stock calculates current price from latest price record', function () {
    $security = Security::factory()->create();

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-05-06',
        'close' => 100.0,
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-05-08',
        'close' => 125.5,
    ]);

    $asset = Stock::find($security->id);
    $currentPrice = $asset->prices()->latest('date')->value('close');

    expect($currentPrice)->toBe('125.5000');
});

it('stock has correct asset type', function () {
    $security = Security::factory()->create();
    $stock = Stock::find($security->id);

    expect($stock->type->value)->toBe('stock');
});
