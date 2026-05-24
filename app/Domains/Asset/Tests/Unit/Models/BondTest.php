<?php

use App\Domains\Asset\Factories\AssetInfos\BondAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\BondAssetInfo;
use App\Domains\Asset\Models\Assets\Bond;

it('delegates isin and ticker to BondAssetInfo via infos relation', function () {
    $bond = Bond::factory()
        ->withInfos(fn (BondAssetInfoFactory $f) => $f->state([
            'isin' => 'XS0111111111',
            'ticker' => 'BOND',
        ]))
        ->create(['name' => 'Corporate Bond']);

    $bond = Bond::find($bond->id);

    expect($bond->infos->isin)->toBe('XS0111111111')
        ->and($bond->infos->ticker)->toBe('BOND')
        ->and($bond->infos)->toBeInstanceOf(BondAssetInfo::class);
});

it('ticker can be null for bonds', function () {
    $bond = Bond::factory()
        ->withInfos(fn (BondAssetInfoFactory $f) => $f->state([
            'isin' => 'XS0111111111',
            'ticker' => null,
        ]))
        ->create();

    $bond = Bond::find($bond->id);

    expect($bond->infos->ticker)->toBeNull();
});
