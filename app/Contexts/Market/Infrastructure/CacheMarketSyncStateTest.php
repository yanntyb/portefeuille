<?php

use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Datas\MarketSyncReportData;
use App\Contexts\Market\Datas\PriceSyncReportData;
use App\Contexts\Market\Enums\MarketSyncStatus;
use App\Contexts\Market\Infrastructure\CacheMarketSyncState;
use Illuminate\Support\Carbon;

function syncReport(): MarketSyncReportData
{
    return new MarketSyncReportData(
        prices: new PriceSyncReportData(synced: ['AAPL' => 12]),
        sectors: ['AAPL' => 11],
        dividends: new DividendSyncReportData(synced: ['AAPL' => 1]),
    );
}

it('reports an idle state before anything ever ran', function () {
    expect((new CacheMarketSyncState)->current()->status)->toBe(MarketSyncStatus::Idle);
});

it('takes the place once, and refuses the second taker', function () {
    $state = new CacheMarketSyncState;

    expect($state->begin())->toBeTrue()
        ->and($state->begin())->toBeFalse()
        ->and($state->current()->status)->toBe(MarketSyncStatus::Queued);
});

it('frees the place once the run has succeeded', function () {
    Carbon::setTestNow('2026-08-31 14:00:00');

    $state = new CacheMarketSyncState;
    $state->begin();
    $state->markRunning();

    expect($state->current()->status)->toBe(MarketSyncStatus::Running);

    Carbon::setTestNow('2026-08-31 14:02:00');
    $state->markSucceeded(syncReport());

    $current = $state->current();

    expect($current->status)->toBe(MarketSyncStatus::Succeeded)
        ->and($current->summary)->toBe('1 cours, 1 secteur, 1 dividende')
        /** L'horodatage de départ traverse toute la course, il n'est pas réécrit à l'arrivée. */
        ->and($current->startedAt)->toBe(Carbon::parse('2026-08-31 14:00:00')->timestamp)
        ->and($current->finishedAt)->toBe(Carbon::parse('2026-08-31 14:02:00')->timestamp)
        ->and($state->begin())->toBeTrue();
});

it('frees the place once the run has failed, and says why', function () {
    $state = new CacheMarketSyncState;
    $state->begin();
    $state->markRunning();
    $state->markFailed('le fournisseur est resté muet');

    $current = $state->current();

    expect($current->status)->toBe(MarketSyncStatus::Failed)
        ->and($current->error)->toBe('le fournisseur est resté muet')
        ->and($current->summary)->toBeNull()
        ->and($state->begin())->toBeTrue();
});

it('downgrades a run whose lock expired without a word', function () {
    Carbon::setTestNow('2026-08-31 14:00:00');

    $state = new CacheMarketSyncState;
    $state->begin();
    $state->markRunning();

    /** Le worker a été tué : `failed()` n'a jamais été appelé, seul le TTL du verrou tombe. */
    Carbon::setTestNow('2026-08-31 14:31:00');

    $current = $state->current();

    expect($current->status)->toBe(MarketSyncStatus::Failed)
        ->and($current->error)->toBe('Synchronisation interrompue.')
        ->and($state->begin())->toBeTrue();
});
