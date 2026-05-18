<?php

use App\Domains\Asset\Factories\AssetInfos\CryptoAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\CryptoAssetInfo;
use App\Domains\Asset\Models\Assets\Crypto;

it('delegates ticker to CryptoAssetInfo via details relation', function () {
    $crypto = Crypto::factory()
        ->withInfos(fn (CryptoAssetInfoFactory $f) => $f->state([
            'ticker' => 'BTC',
        ]))
        ->create(['name' => 'Bitcoin']);

    $crypto = Crypto::find($crypto->id);

    expect($crypto->ticker)->toBe('BTC')
        ->and($crypto->details)->toBeInstanceOf(CryptoAssetInfo::class);
});

it('isin returns null for crypto', function () {
    $crypto = Crypto::factory()
        ->withInfos(fn (CryptoAssetInfoFactory $f) => $f->state([
            'ticker' => 'BTC',
        ]))
        ->create();

    $crypto = Crypto::find($crypto->id);

    expect($crypto->isin)->toBeNull();
});

it('has correct asset type', function () {
    $crypto = Crypto::factory()->create();
    $crypto = Crypto::find($crypto->id);

    expect($crypto->type->value)->toBe('crypto');
});
