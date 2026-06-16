<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\PersonalAsset;

it('applique le type Stock par défaut à la création', function () {
    $instrument = Instrument::create(['name' => 'Sans type']);

    expect($instrument->type)->toBe(InstrumentType::Stock);
});

it('cast le type en enum', function () {
    $instrument = Instrument::factory()->ofType(InstrumentType::Crypto)->create();

    expect($instrument->refresh()->type)->toBe(InstrumentType::Crypto);
});

it('filtre les assets non-marché via le global scope', function () {
    Instrument::factory()->create();
    PersonalAsset::factory()->create();

    expect(Instrument::query()->count())->toBe(1)
        ->and(Instrument::query()->get()->pluck('type'))
        ->each->toBeInstanceOf(InstrumentType::class);
});

it('a une relation prices', function () {
    $instrument = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $instrument->id]);

    expect($instrument->prices)->toHaveCount(1)
        ->and($instrument->prices->first())->toBeInstanceOf(Price::class);
});

it('a une relation sectors', function () {
    $instrument = Instrument::factory()->create();
    SectorAllocation::factory()->create(['asset_id' => $instrument->id]);

    expect($instrument->sectors)->toHaveCount(1)
        ->and($instrument->sectors->first())->toBeInstanceOf(SectorAllocation::class);
});
