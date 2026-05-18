<?php

use App\Domains\Asset\Factories\AssetInfos\RealEstateAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\RealEstateAssetInfo;
use App\Domains\Asset\Models\Assets\RealEstate;

it('delegates isin to RealEstateAssetInfo via details relation', function () {
    $property = RealEstate::factory()
        ->withInfos(fn (RealEstateAssetInfoFactory $f) => $f->state([
            'isin' => 'FR1111111111',
        ]))
        ->create(['name' => 'Apartment in Paris']);

    $property = RealEstate::find($property->id);

    expect($property->isin)->toBe('FR1111111111')
        ->and($property->details)->toBeInstanceOf(RealEstateAssetInfo::class);
});

it('ticker returns null for real estate', function () {
    $property = RealEstate::factory()->create();
    $property = RealEstate::find($property->id);

    expect($property->ticker)->toBeNull();
});

it('has correct asset type', function () {
    $property = RealEstate::factory()->create();
    $property = RealEstate::find($property->id);

    expect($property->type->value)->toBe('real_estate');
});
