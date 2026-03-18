<?php

use App\Services\YahooFinance\YahooFinanceConnector;
use App\Services\YahooFinanceClient;
use Illuminate\Support\Facades\Cache;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Cache::put('yahoo_finance_auth', [
        'crumb' => 'fake-crumb',
        'cookies' => [],
    ], now()->addMinutes(20));
});

it('retries on 429 and succeeds on subsequent attempt', function () {
    $mockClient = new MockClient([
        MockResponse::make(body: '', status: 429),
        MockResponse::make(body: ['quotes' => [
            ['symbol' => 'AAPL', 'longname' => 'Apple Inc.', 'exchDisp' => 'NASDAQ', 'typeDisp' => 'Equity'],
        ]]),
    ]);

    $connector = new YahooFinanceConnector;
    $connector->withMockClient($mockClient);
    $client = new YahooFinanceClient($connector);

    $results = $client->search('AAPL');

    expect($results)->toHaveCount(1)
        ->and($results[0]['symbol'])->toBe('AAPL');
});

it('returns empty array on persistent 429 errors', function () {
    $mockClient = new MockClient([
        MockResponse::make(body: '', status: 429),
        MockResponse::make(body: '', status: 429),
        MockResponse::make(body: '', status: 429),
    ]);

    $connector = new YahooFinanceConnector;
    $connector->withMockClient($mockClient);
    $client = new YahooFinanceClient($connector);

    $results = $client->search('AAPL');

    expect($results)->toBe([]);
});

it('retries on 401 and succeeds when auth is refreshed', function () {
    $mockClient = new MockClient([
        MockResponse::make(body: '', status: 401),
        MockResponse::make(body: ['chart' => ['result' => [['timestamp' => [1708300800], 'indicators' => ['quote' => [['open' => [100.0], 'high' => [105.0], 'low' => [99.0], 'close' => [103.0], 'volume' => [50000]]]]]]]]),
    ]);

    $connector = new YahooFinanceConnector;
    $connector->withMockClient($mockClient);
    $client = new YahooFinanceClient($connector);

    $results = $client->fetchPrices('AAPL', '2024-02-19', '2024-02-20');

    expect($results)->toHaveCount(1)
        ->and($results[0]['close'])->toBe(103.0);
});

it('retries on 403 and succeeds when auth is refreshed', function () {
    $mockClient = new MockClient([
        MockResponse::make(body: '', status: 403),
        MockResponse::make(body: ['quoteSummary' => ['result' => [['assetProfile' => ['sector' => 'Technology']]]]]),
    ]);

    $connector = new YahooFinanceConnector;
    $connector->withMockClient($mockClient);
    $client = new YahooFinanceClient($connector);

    $results = $client->fetchSectors('AAPL');

    expect($results)->toBe(['technology' => 1.0]);
});
