<?php

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Services\YahooFinanceService;
use Scheb\YahooFinanceApi\ApiClient;
use Scheb\YahooFinanceApi\Results\HistoricalData;
use Scheb\YahooFinanceApi\Results\SearchResult;

use function Pest\Laravel\mock;

it('resolves a ticker from an ISIN', function () {
    $apiClient = mock(ApiClient::class);
    $apiClient->shouldReceive('search')
        ->with('FR0011871110')
        ->once()
        ->andReturn([
            new SearchResult('CW8.PA', 'Amundi MSCI World', 'PAR', 'ETF', 'Paris', 'ETF'),
        ]);

    $service = new YahooFinanceService($apiClient);

    expect($service->resolveTickerFromIsin('FR0011871110'))->toBe('CW8.PA');
});

it('throws TickerResolutionException when no result', function () {
    $apiClient = mock(ApiClient::class);
    $apiClient->shouldReceive('search')
        ->with('XX0000000000')
        ->once()
        ->andReturn([]);

    $service = new YahooFinanceService($apiClient);

    $service->resolveTickerFromIsin('XX0000000000');
})->throws(TickerResolutionException::class);

it('fetches and stores prices for a security', function () {
    $security = Security::factory()->create(['isin' => 'FR0011871110', 'ticker' => 'CW8.PA']);

    $apiClient = mock(ApiClient::class);
    $apiClient->shouldReceive('getHistoricalQuoteData')
        ->once()
        ->andReturn([
            new HistoricalData(new DateTime('2026-02-19'), 100.0, 105.0, 99.0, 103.0, 103.0, 50000),
            new HistoricalData(new DateTime('2026-02-20'), 103.0, 107.0, 102.0, 106.0, 106.0, 60000),
        ]);

    $service = new YahooFinanceService($apiClient);
    $count = $service->fetchAndStorePrices($security);

    expect($count)->toBe(2);
    expect(SecurityPrice::where('security_id', $security->id)->count())->toBe(2);

    $this->assertDatabaseHas('security_prices', [
        'security_id' => $security->id,
        'date' => '2026-02-19',
        'close' => 103.0,
    ]);
});

it('resolves the ticker if not set and saves it', function () {
    $security = Security::factory()->create(['isin' => 'FR0011871110', 'ticker' => null]);

    $apiClient = mock(ApiClient::class);
    $apiClient->shouldReceive('search')
        ->with('FR0011871110')
        ->once()
        ->andReturn([
            new SearchResult('CW8.PA', 'Amundi MSCI World', 'PAR', 'ETF', 'Paris', 'ETF'),
        ]);
    $apiClient->shouldReceive('getHistoricalQuoteData')
        ->once()
        ->andReturn([]);

    $service = new YahooFinanceService($apiClient);
    $service->fetchAndStorePrices($security);

    expect($security->fresh()->ticker)->toBe('CW8.PA');
});

it('fetches incrementally from the last stored date', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2026-02-18',
        'close' => 100.0,
    ]);

    $apiClient = mock(ApiClient::class);
    $apiClient->shouldReceive('getHistoricalQuoteData')
        ->withArgs(function (string $symbol, string $interval, DateTimeInterface $start) {
            return $start->format('Y-m-d') === '2026-02-19';
        })
        ->once()
        ->andReturn([
            new HistoricalData(new DateTime('2026-02-19'), 100.0, 105.0, 99.0, 103.0, 103.0, 50000),
        ]);

    $service = new YahooFinanceService($apiClient);
    $count = $service->fetchAndStorePrices($security);

    expect($count)->toBe(1);
    expect(SecurityPrice::where('security_id', $security->id)->count())->toBe(2);
});
