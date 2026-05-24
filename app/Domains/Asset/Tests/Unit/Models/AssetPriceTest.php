<?php

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Assets\Stock;

it('uses the asset_prices table', function (): void {
    expect((new AssetPrice)->getTable())->toBe('asset_prices');
});

it('uses asset_id as foreign key', function (): void {
    expect(in_array('asset_id', (new AssetPrice)->getFillable()))->toBeTrue();
});

it('belongs to an asset', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create(['asset_id' => $stock->id]);

    expect($price->asset)->not->toBeNull()
        ->and($price->asset->id)->toBe($stock->id);
});

it('casts date to Carbon instance', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'date' => '2026-05-08',
    ]);

    expect($price->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($price->date->format('Y-m-d'))->toBe('2026-05-08');
});

it('casts OHLC fields as decimals', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'open' => 100.1234,
        'high' => 105.5678,
        'low' => 98.9012,
        'close' => 103.4567,
    ]);

    $price->refresh();

    expect($price->open)->toBe('100.1234')
        ->and($price->high)->toBe('105.5678')
        ->and($price->low)->toBe('98.9012')
        ->and($price->close)->toBe('103.4567');
});

it('casts volume as integer', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'volume' => 75000,
    ]);

    expect($price->refresh()->volume)->toBe(75000);
});
