<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Support\Facades\DB;

it('applies the Stock type by default on creation', function () {
    $instrument = Instrument::create(['name' => 'Sans type']);

    expect($instrument->type)->toBe(InstrumentType::Stock);
});

it('casts the type to an enum', function () {
    $instrument = Instrument::factory()->ofType(InstrumentType::Crypto)->create();

    expect($instrument->refresh()->type)->toBe(InstrumentType::Crypto);
});

it('filters out non-market assets via the global scope', function () {
    Instrument::factory()->create();
    DB::table('assets')->insert([
        'name' => 'Appartement témoin',
        'type' => 'real_estate',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Instrument::query()->count())->toBe(1)
        ->and(Instrument::query()->get()->pluck('type'))
        ->each->toBeInstanceOf(InstrumentType::class);
});

it('has a prices relation', function () {
    $instrument = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $instrument->id]);

    expect($instrument->prices)->toHaveCount(1)
        ->and($instrument->prices->first())->toBeInstanceOf(Price::class);
});

it('has a sectors relation', function () {
    $instrument = Instrument::factory()->create();
    SectorAllocation::factory()->create(['asset_id' => $instrument->id]);

    expect($instrument->sectors)->toHaveCount(1)
        ->and($instrument->sectors->first())->toBeInstanceOf(SectorAllocation::class);
});

it('fills the exposure from the wrapper when none is given', function () {
    $etf = Instrument::factory()->create(['type' => InstrumentType::ETF]);
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);

    expect($etf->asset_class)->toBe(AssetClass::Equity)
        ->and($gold->asset_class)->toBe(AssetClass::Commodity);
});

it('never overwrites an exposure that was given explicitly', function () {
    $bondEtf = Instrument::factory()->create([
        'type' => InstrumentType::ETF,
        'asset_class' => AssetClass::Bond,
    ]);

    expect($bondEtf->fresh()->asset_class)->toBe(AssetClass::Bond);
});
