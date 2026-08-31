<?php

use App\Contexts\Market\Actions\SyncMarketData;
use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Datas\MarketSyncReportData;
use App\Contexts\Market\Datas\PriceSyncReportData;
use App\Contexts\Market\Enums\MarketSyncStatus;
use App\Contexts\Market\Jobs\SyncMarketDataJob;
use App\Contexts\Market\Ports\MarketSyncStatePort;
use RuntimeException;

it('runs the sync and publishes its summary', function () {
    $this->mock(SyncMarketData::class, function ($mock) {
        $mock->shouldReceive('__invoke')->once()->andReturn(new MarketSyncReportData(
            prices: new PriceSyncReportData(synced: ['AAPL' => 12]),
            sectors: ['AAPL' => 11],
            dividends: new DividendSyncReportData,
        ));
    });

    $state = app(MarketSyncStatePort::class);
    $state->begin();

    app()->call([new SyncMarketDataJob, 'handle']);

    $current = $state->current();

    expect($current->status)->toBe(MarketSyncStatus::Succeeded)
        ->and($current->summary)->toBe('1 cours, 1 secteur, 0 dividende');
});

it('publishes the failure and frees the place when the run blew up', function () {
    $state = app(MarketSyncStatePort::class);
    $state->begin();
    $state->markRunning();

    (new SyncMarketDataJob)->failed(new RuntimeException('le fournisseur est resté muet'));

    $current = $state->current();

    expect($current->status)->toBe(MarketSyncStatus::Failed)
        ->and($current->error)->toBe('le fournisseur est resté muet')
        /** La place est rendue : le bouton redevient cliquable sans attendre le TTL du verrou. */
        ->and($state->begin())->toBeTrue();
});

it('tries only once, like the worker that runs it', function () {
    expect((new SyncMarketDataJob)->tries)->toBe(1);
});
