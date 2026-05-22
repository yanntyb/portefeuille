<?php

use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\Asset\Models\AssetSector;
use App\Domains\AssetView\DTOs\SectorWeightDTO;

it('maps sector and weight from AssetSector model', function (): void {
    $stock = Stock::factory()->create();
    $sector = AssetSector::factory()->create([
        'asset_id' => $stock->id,
        'sector' => Sector::Technology->value,
        'weight' => '0.450000',
    ]);

    $dto = SectorWeightDTO::fromModel($sector);

    expect($dto->sector)->toBe(Sector::Technology)
        ->and($dto->weight)->toBe(0.45);
});
