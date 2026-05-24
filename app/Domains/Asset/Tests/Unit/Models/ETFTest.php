<?php

use App\Domains\Asset\Factories\AssetInfos\ETFAssetInfoFactory;
use App\Domains\Asset\Models\AssetInfos\ETFAssetInfo;
use App\Domains\Asset\Models\Assets\ETF;

it('delegates isin and ticker to ETFAssetInfo via infos relation', function () {
    $etf = ETF::factory()
        ->withInfos(fn (ETFAssetInfoFactory $f) => $f->state([
            'isin' => 'FR0010296061',
            'ticker' => 'EWLD.PA',
        ]))
        ->create(['name' => 'iShares MSCI World']);

    $etf = ETF::find($etf->id);

    expect($etf->infos->ticker)->toBe('EWLD.PA')
        ->and($etf->infos->isin)->toBe('FR0010296061')
        ->and($etf->infos)->toBeInstanceOf(ETFAssetInfo::class);
});
