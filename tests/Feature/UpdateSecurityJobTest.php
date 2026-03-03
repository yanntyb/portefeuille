<?php

use App\Jobs\UpdateSecurityJob;
use App\Models\Security;
use App\Services\YahooFinanceService;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\mock;

it('updates security and fetches prices and sectors', function () {
    $security = Security::factory()->create(['ticker' => 'OLD.PA', 'name' => 'Old Name']);
    $cacheKey = UpdateSecurityJob::cacheKeyFor($security->id);
    Cache::put($cacheKey, true);

    $service = mock(YahooFinanceService::class);
    $service->shouldReceive('fetchAndStorePrices')->once()->andReturn(10);
    $service->shouldReceive('fetchAndStoreSectors')->once()->andReturn(3);

    (new UpdateSecurityJob($security->id, 'NEW.PA', 'New Name'))->handle($service);

    $security->refresh();
    expect($security->ticker)->toBe('NEW.PA');
    expect($security->name)->toBe('New Name');
    expect(Cache::has($cacheKey))->toBeFalse();
});

it('clears cache even on failure', function () {
    $security = Security::factory()->create();
    $cacheKey = UpdateSecurityJob::cacheKeyFor($security->id);
    Cache::put($cacheKey, true);

    $service = mock(YahooFinanceService::class);
    $service->shouldReceive('fetchAndStorePrices')->andThrow(new RuntimeException('API down'));

    try {
        (new UpdateSecurityJob($security->id, 'X.PA', 'X'))->handle($service);
    } catch (RuntimeException) {
        // expected
    }

    expect(Cache::has($cacheKey))->toBeFalse();
});
