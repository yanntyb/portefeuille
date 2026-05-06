<?php

use App\Domains\Security\Models\Security;
use App\Domains\User\Models\User;
use App\Domains\Security\Services\YahooFinanceClient;
use App\Domains\Security\Services\YahooFinanceService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('does not call the API on second bulk fetch within the cache window', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    $mock = $this->mock(YahooFinanceClient::class);
    $mock->shouldReceive('fetchPricesBulk')
        ->once()
        ->andReturn([
            'CW8.PA' => [
                ['date' => '2026-03-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
            ],
        ]);

    $service = app(YahooFinanceService::class);
    $securities = Security::where('id', $security->id)->get();

    $firstResult = $service->fetchAndStorePricesBulk($securities);
    $secondResult = $service->fetchAndStorePricesBulk($securities);

    expect($firstResult)->toBe(1)
        ->and($secondResult)->toBe(0);
});

it('calls the API again after cache expires', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    $mock = $this->mock(YahooFinanceClient::class);
    $mock->shouldReceive('fetchPricesBulk')
        ->twice()
        ->andReturn([
            'AAPL' => [
                ['date' => '2026-03-19', 'open' => 200.0, 'high' => 210.0, 'low' => 195.0, 'close' => 205.0, 'volume' => 80000],
            ],
        ]);

    $service = app(YahooFinanceService::class);
    $securities = Security::where('id', $security->id)->get();

    $service->fetchAndStorePricesBulk($securities);

    Cache::forget('yahoo_prices_bulk_fetched:'.auth()->id());

    $secondResult = $service->fetchAndStorePricesBulk($securities);

    expect($secondResult)->toBe(1);
});

it('bypasses cache when force is true', function () {
    $security = Security::factory()->create(['ticker' => 'AAPL']);

    $mock = $this->mock(YahooFinanceClient::class);
    $mock->shouldReceive('fetchPricesBulk')
        ->twice()
        ->andReturn([
            'AAPL' => [
                ['date' => '2026-03-19', 'open' => 200.0, 'high' => 210.0, 'low' => 195.0, 'close' => 205.0, 'volume' => 80000],
            ],
        ]);

    $service = app(YahooFinanceService::class);
    $securities = Security::where('id', $security->id)->get();

    $service->fetchAndStorePricesBulk($securities);
    $secondResult = $service->fetchAndStorePricesBulk($securities, force: true);

    expect($secondResult)->toBe(1);
});

it('scopes cache per user so different users can fetch independently', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    $mock = $this->mock(YahooFinanceClient::class);
    $mock->shouldReceive('fetchPricesBulk')
        ->twice()
        ->andReturn([
            'CW8.PA' => [
                ['date' => '2026-03-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
            ],
        ]);

    $service = app(YahooFinanceService::class);
    $securities = Security::where('id', $security->id)->get();

    $service->fetchAndStorePricesBulk($securities);

    $this->actingAs(User::factory()->create());

    $secondResult = $service->fetchAndStorePricesBulk($securities);

    expect($secondResult)->toBe(1);
});
