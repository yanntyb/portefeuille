<?php

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecuritySector;
use App\Models\Transaction;
use App\Services\YahooFinanceService;
use Mockery\MockInterface;

use function Pest\Laravel\artisan;
use function Pest\Laravel\mock;

it('fetches sectors for securities needing update', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    Security::factory()->create(); // without transactions

    mock(YahooFinanceService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchAndStoreSectors')
            ->once()
            ->andReturn(3);
    });

    artisan('securities:fetch-sectors')
        ->assertSuccessful();
});

it('skips securities with recently updated sectors', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    SecuritySector::factory()->create([
        'security_id' => $security->id,
        'updated_at' => now()->subDays(3),
    ]);

    mock(YahooFinanceService::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('fetchAndStoreSectors');
    });

    artisan('securities:fetch-sectors')
        ->assertSuccessful();
});

it('fetches sectors for a specific security', function () {
    $security = Security::factory()->create();

    mock(YahooFinanceService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchAndStoreSectors')
            ->once()
            ->andReturn(5);
    });

    artisan('securities:fetch-sectors', ['--security' => $security->id])
        ->assertSuccessful();
});

it('handles ticker resolution errors gracefully', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);

    mock(YahooFinanceService::class, function (MockInterface $mock) use ($security) {
        $mock->shouldReceive('fetchAndStoreSectors')
            ->once()
            ->andThrow(TickerResolutionException::noResultForIsin($security->isin));
    });

    artisan('securities:fetch-sectors')
        ->assertFailed();
});

it('fetches sectors when existing sectors are older than 7 days', function () {
    $security = Security::factory()->create();
    Transaction::factory()->pea()->create(['security_id' => $security->id]);
    SecuritySector::factory()->create([
        'security_id' => $security->id,
        'updated_at' => now()->subDays(8),
    ]);

    mock(YahooFinanceService::class, function (MockInterface $mock) {
        $mock->shouldReceive('fetchAndStoreSectors')
            ->once()
            ->andReturn(3);
    });

    artisan('securities:fetch-sectors')
        ->assertSuccessful();
});
