<?php

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\DTOs\PriceHistoryDTO;
use App\Domains\AssetView\Ports\AssetPriceViewPort;

it('returns price history as DTOs within date range', function () {
    $stock = Stock::factory()->create();

    foreach (range(0, 2) as $i) {
        AssetPrice::factory()->create([
            'asset_id' => $stock->id,
            'date' => now()->subDays(5 + $i)->format('Y-m-d'),
        ]);
    }

    $adapter = app(AssetPriceViewPort::class);
    $result = $adapter->getPriceHistory($stock->id, now()->subYear(), now());

    expect($result)->toHaveCount(3)
        ->and($result->first())->toBeInstanceOf(PriceHistoryDTO::class)
        ->and($result->first()->date)->toBeString();
});

it('returns latest price as DTO', function () {
    $stock = Stock::factory()->create();

    $latest = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'date' => '2026-05-22',
        'close' => 99.50,
    ]);

    $adapter = app(AssetPriceViewPort::class);
    $dto = $adapter->getLatestPrice($stock->id);

    expect($dto)->not->toBeNull()
        ->and($dto)->toBeInstanceOf(PriceHistoryDTO::class)
        ->and($dto->close)->toBe(99.50)
        ->and($dto->date)->toBe('2026-05-22');
});

it('returns null when no latest price exists', function () {
    $stock = Stock::factory()->create();

    $adapter = app(AssetPriceViewPort::class);
    $dto = $adapter->getLatestPrice($stock->id);

    expect($dto)->toBeNull();
});
