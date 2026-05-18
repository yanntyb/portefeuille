<?php

use App\Domains\Asset\Factories\AssetInfos\ETFAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\ETFAssetInfo;
use App\Domains\Asset\Models\Assets\ETF;

it('delegates isin and ticker to ETFAssetInfo via details relation', function () {
    $etf = ETF::factory()
        ->withInfos(fn (ETFAssetInfoFactory $f) => $f->state([
            'isin' => 'FR0010296061',
            'ticker' => 'EWLD.PA',
        ]))
        ->create(['name' => 'iShares MSCI World']);

    $etf = ETF::find($etf->id);

    expect($etf->ticker)->toBe('EWLD.PA')
        ->and($etf->isin)->toBe('FR0010296061')
        ->and($etf->details)->toBeInstanceOf(ETFAssetInfo::class);
});

it('has correct asset type', function () {
    $etf = ETF::factory()->create();
    $etf = ETF::find($etf->id);

    expect($etf->type->value)->toBe('etf');
});

it('is instance of Asset', function () {
    $etf = ETF::factory()->create();
    $etf = ETF::find($etf->id);

    expect($etf)->toBeInstanceOf(ETF::class);
});
