<?php

use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\SectorAllocation;

it('casts the sector to an enum and the weight to a decimal', function () {
    $instrument = Instrument::factory()->create();

    $allocation = SectorAllocation::factory()->create([
        'asset_id' => $instrument->id,
        'sector' => Sector::Technology,
        'weight' => 0.5,
    ]);

    $allocation->refresh();

    expect($allocation->sector)->toBe(Sector::Technology)
        ->and($allocation->weight)->toBe('0.500000');
});

it('belongs to an instrument', function () {
    $instrument = Instrument::factory()->create();
    $allocation = SectorAllocation::factory()->create(['asset_id' => $instrument->id]);

    expect($allocation->instrument)->toBeInstanceOf(Instrument::class)
        ->and($allocation->instrument->id)->toBe($instrument->id);
});
