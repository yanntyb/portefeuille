<?php

use App\Domains\Asset\Factories\AssetInfos\RealEstateAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\RealEstateAssetInfo;
use App\Domains\Asset\Models\Assets\RealEstate;

it('delegates isin to RealEstateAssetInfo via infos relation', function () {
    $property = RealEstate::factory()
        ->withInfos(fn (RealEstateAssetInfoFactory $f) => $f->state([
            'isin' => 'FR1111111111',
        ]))
        ->create(['name' => 'Apartment in Paris']);

    $property = RealEstate::find($property->id);

    expect($property->infos->isin)->toBe('FR1111111111')
        ->and($property->infos)->toBeInstanceOf(RealEstateAssetInfo::class);
});
