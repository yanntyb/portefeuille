<?php

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\Transaction;
use App\Services\YahooFinanceService;
use Mockery\MockInterface;

use function Pest\Laravel\artisan;
use function Pest\Laravel\mock;

it('fetches prices for all securities with transactions', function () {
    $securityWithTx = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $securityWithTx->id]);

    Security::factory()->create(); // without transactions

    mock(YahooFinanceService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchAndStorePrices')
            ->once()
            ->andReturn(10);
    });

    artisan('securities:fetch-prices')
        ->assertSuccessful();
});

it('fetches prices for a specific security', function () {
    $security = Security::factory()->create();

    mock(YahooFinanceService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchAndStorePrices')
            ->once()
            ->andReturn(5);
    });

    artisan('securities:fetch-prices', ['--security' => $security->id])
        ->assertSuccessful();
});

it('handles ticker resolution errors gracefully', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    mock(YahooFinanceService::class, function (MockInterface $mock) use ($security) {
        $mock->shouldReceive('fetchAndStorePrices')
            ->once()
            ->andThrow(TickerResolutionException::noResultForIsin($security->isin));
    });

    artisan('securities:fetch-prices')
        ->assertFailed();
});
