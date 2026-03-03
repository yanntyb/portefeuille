<?php

use App\Jobs\UpdateSecuritiesJob;
use App\Models\Security;
use App\Services\YahooFinanceService;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Laravel\mock;

it('fetches prices and sectors for given securities', function () {
    $securities = Security::factory()->count(2)->create();

    $service = mock(YahooFinanceService::class);
    $service->shouldReceive('fetchAndStorePricesBulk')
        ->once()
        ->withArgs(fn (Collection $col) => $col->pluck('id')->all() === $securities->pluck('id')->all())
        ->andReturn(10);
    $service->shouldReceive('fetchAndStoreSectors')
        ->twice()
        ->andReturn(3);

    (new UpdateSecuritiesJob($securities->pluck('id')->all()))->handle($service);
});

it('skips sectors gracefully on ticker resolution failure', function () {
    $security = Security::factory()->create();

    $service = mock(YahooFinanceService::class);
    $service->shouldReceive('fetchAndStorePricesBulk')
        ->once()
        ->andReturn(0);
    $service->shouldReceive('fetchAndStoreSectors')
        ->once()
        ->andThrow(new \App\Exceptions\TickerResolutionException('No ticker found'));

    (new UpdateSecuritiesJob([$security->id]))->handle($service);
});
