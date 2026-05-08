<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Stock;
use App\Domains\Security\Models\SecurityPrice;

it('returns 0 valuation with no latestPrice', function (): void {
    $stock = Stock::factory()->make();

    $valuation = $stock->currentValuation();

    expect($valuation)->toBe(0.0);
});

it('returns quantity * close when latestPrice loaded', function (): void {
    $stock = Stock::factory()->create();
    $stock->setAttribute('total_quantity', 100);

    $latestPrice = 
SecurityPrice::factory()->make(['close' => 50.0]);
    $stock->setRelation('latestPrice', $latestPrice);

    $valuation = $stock->currentValuation();

    expect($valuation)->toBe(5000.0);
});

it('casts type to AssetType enum', function (): void {
    $stock = Stock::factory()->create(['type' => AssetType::Stock]);

    expect($stock->type)->toBeInstanceOf(AssetType::class)
        ->and($stock->type)->toBe(AssetType::Stock);
});
