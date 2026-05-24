<?php

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\ValueObjects\PriceHistoryDTO;

it('maps all fields from AssetPrice model', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'date' => '2026-01-15',
        'close' => 123.45,
        'open' => 120.00,
        'high' => 125.00,
        'low' => 119.00,
        'volume' => 50000,
    ]);

    $dto = PriceHistoryDTO::fromModel($price);

    expect($dto->date)->toBe('2026-01-15')
        ->and($dto->close)->toBe(123.45)
        ->and($dto->open)->toBe(120.0)
        ->and($dto->high)->toBe(125.0)
        ->and($dto->low)->toBe(119.0)
        ->and($dto->volume)->toBe(50000);
});

it('handles nullable open/high/low fields', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'open' => null,
        'high' => null,
        'low' => null,
    ]);

    $dto = PriceHistoryDTO::fromModel($price);

    expect($dto->open)->toBeNull()
        ->and($dto->high)->toBeNull()
        ->and($dto->low)->toBeNull();
});
