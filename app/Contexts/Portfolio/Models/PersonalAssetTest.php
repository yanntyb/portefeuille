<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\PersonalAssetType;
use App\Contexts\Portfolio\Models\PersonalAsset;

it('applique le type Savings par défaut à la création', function () {
    $asset = PersonalAsset::create(['name' => 'Livret A']);

    expect($asset->type)->toBe(PersonalAssetType::Savings);
});

it('cast le type en enum', function () {
    $asset = PersonalAsset::factory()->ofType(PersonalAssetType::RealEstate)->create();

    expect($asset->refresh()->type)->toBe(PersonalAssetType::RealEstate);
});

it('filtre les assets marché via le global scope', function () {
    PersonalAsset::factory()->create();
    Instrument::factory()->create();

    expect(PersonalAsset::query()->count())->toBe(1)
        ->and(PersonalAsset::query()->get()->pluck('type'))
        ->each->toBeInstanceOf(PersonalAssetType::class);
});
