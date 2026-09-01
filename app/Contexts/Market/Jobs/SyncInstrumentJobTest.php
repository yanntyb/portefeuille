<?php

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Actions\SyncAssetPrices;
use App\Contexts\Market\Actions\SyncAssetSectors;
use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Datas\PriceSyncReportData;
use App\Contexts\Market\Jobs\SyncInstrumentJob;
use Illuminate\Support\Carbon;

it('synchronise cinq ans du seul instrument créé', function () {
    Carbon::setTestNow('2026-09-01');

    $calls = [];

    $prices = Mockery::mock(SyncAssetPrices::class);
    $prices->shouldReceive('__invoke')
        ->once()
        ->with(42, '2021-09-01')
        ->andReturnUsing(function () use (&$calls): PriceSyncReportData {
            $calls[] = 'prices';

            return new PriceSyncReportData;
        });

    $sectors = Mockery::mock(SyncAssetSectors::class);
    $sectors->shouldReceive('__invoke')
        ->once()
        ->with(42)
        ->andReturnUsing(function () use (&$calls): array {
            $calls[] = 'sectors';

            return [];
        });

    $dividends = Mockery::mock(SyncAssetDividends::class);
    $dividends->shouldReceive('__invoke')
        ->once()
        ->with(42, '2021-09-01')
        ->andReturnUsing(function () use (&$calls): DividendSyncReportData {
            $calls[] = 'dividends';

            return new DividendSyncReportData;
        });

    (new SyncInstrumentJob(42))->handle($prices, $sectors, $dividends);

    /** Les cours d'abord, comme dans `SyncMarketData` : c'est eux qui font vivre la fiche. */
    expect($calls)->toBe(['prices', 'sectors', 'dividends']);
});
