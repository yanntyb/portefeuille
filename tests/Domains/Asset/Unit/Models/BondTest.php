<?php

use App\Domains\Asset\Factories\AssetInfos\BondAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\BondAssetInfo;
use App\Domains\Asset\Models\Assets\Bond;

it('delegates isin and ticker to BondAssetInfo via details relation', function () {
    $bond = Bond::factory()
        ->withInfos(fn (BondAssetInfoFactory $f) => $f->state([
            'isin' => 'XS0111111111',
            'ticker' => 'BOND',
        ]))
        ->create(['name' => 'Corporate Bond']);

    $bond = Bond::find($bond->id);

    expect($bond->isin)->toBe('XS0111111111')
        ->and($bond->ticker)->toBe('BOND')
        ->and($bond->details)->toBeInstanceOf(BondAssetInfo::class);
});

it('ticker can be null for bonds', function () {
    $bond = Bond::factory()
        ->withInfos(fn (BondAssetInfoFactory $f) => $f->state([
            'isin' => 'XS0111111111',
            'ticker' => null,
        ]))
        ->create();

    $bond = Bond::find($bond->id);

    expect($bond->ticker)->toBeNull();
});

it('has correct asset type', function () {
    $bond = Bond::factory()->create();
    $bond = Bond::find($bond->id);

    expect($bond->type->value)->toBe('bond');
});
