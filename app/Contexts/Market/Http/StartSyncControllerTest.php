<?php

use App\Contexts\Market\Enums\MarketSyncStatus;
use App\Contexts\Market\Jobs\SyncMarketDataJob;
use App\Contexts\Market\Ports\MarketSyncStatePort;
use Illuminate\Support\Facades\Bus;

it('queues one sync and answers without a body', function () {
    Bus::fake();

    $this->post('/synchronisation')->assertNoContent();

    Bus::assertDispatchedTimes(SyncMarketDataJob::class, 1);

    expect(app(MarketSyncStatePort::class)->current()->status)->toBe(MarketSyncStatus::Queued);
});

it('queues nothing more while a sync is already running', function () {
    Bus::fake();

    $this->post('/synchronisation')->assertNoContent();
    $this->post('/synchronisation')->assertNoContent();

    /** Deux clics, un seul job : le verrou tient, et le second appel n'est pas une erreur. */
    Bus::assertDispatchedTimes(SyncMarketDataJob::class, 1);
});
