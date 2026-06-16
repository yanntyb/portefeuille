<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\PersonalAssetType;
use App\Contexts\Portfolio\Models\PersonalAsset;

it('applies the Savings type by default on creation', function () {
    $asset = PersonalAsset::create(['name' => 'Livret A']);

    expect($asset->type)->toBe(PersonalAssetType::Savings);
});

it('casts the type to an enum', function () {
    $asset = PersonalAsset::factory()->ofType(PersonalAssetType::RealEstate)->create();

    expect($asset->refresh()->type)->toBe(PersonalAssetType::RealEstate);
});

it('filters out market assets via the global scope', function () {
    PersonalAsset::factory()->create();
    Instrument::factory()->create();

    expect(PersonalAsset::query()->count())->toBe(1)
        ->and(PersonalAsset::query()->get()->pluck('type'))
        ->each->toBeInstanceOf(PersonalAssetType::class);
});
