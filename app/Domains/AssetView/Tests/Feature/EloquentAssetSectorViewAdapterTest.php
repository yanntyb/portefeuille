<?php

use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\Asset\Models\AssetSector;
use App\Domains\AssetView\Ports\AssetSectorViewPort;
use App\Domains\AssetView\ValueObjects\SectorWeightDTO;

it('returns sector weights as DTOs', function () {
    $stock = Stock::factory()->create();

    AssetSector::factory()->count(3)->create([
        'asset_id' => $stock->id,
    ]);

    $adapter = app(AssetSectorViewPort::class);
    $result = $adapter->getSectorWeights($stock->id);

    expect($result)->toHaveCount(3)
        ->and($result->first())->toBeInstanceOf(SectorWeightDTO::class)
        ->and($result->first()->sector)->toBeInstanceOf(Sector::class)
        ->and($result->first()->weight)->toBeFloat();
});

it('returns empty collection when no sectors exist', function () {
    $stock = Stock::factory()->create();

    $adapter = app(AssetSectorViewPort::class);
    $result = $adapter->getSectorWeights($stock->id);

    expect($result)->toHaveCount(0);
});

it('returns correct sector enum and weight values', function () {
    $stock = Stock::factory()->create();

    AssetSector::factory()->create([
        'asset_id' => $stock->id,
        'sector' => Sector::Technology,
        'weight' => 0.35,
    ]);

    $adapter = app(AssetSectorViewPort::class);
    $result = $adapter->getSectorWeights($stock->id);

    expect($result->first()->sector)->toBe(Sector::Technology)
        ->and($result->first()->weight)->toBe(0.35);
});
