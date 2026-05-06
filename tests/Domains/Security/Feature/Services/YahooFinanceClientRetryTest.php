<?php

use App\Domains\Security\Services\YahooFinanceClient;
use Illuminate\Support\Facades\Process;

it('returns empty array when python script fails for search', function () {
    Process::fake([
        '*search_ticker*' => Process::result(output: '', errorOutput: 'error', exitCode: 1),
    ]);

    $client = new YahooFinanceClient;

    expect($client->search('AAPL'))->toBe([]);
});

it('returns empty array when python script fails for fetchPrices', function () {
    Process::fake([
        '*fetch_prices.py*' => Process::result(output: '', errorOutput: 'error', exitCode: 1),
    ]);

    $client = new YahooFinanceClient;

    expect($client->fetchPrices('AAPL', '2024-02-19', '2024-02-20'))->toBe([]);
});

it('returns empty array when python script fails for fetchPricesBulk', function () {
    Process::fake([
        '*fetch_prices_bulk*' => Process::result(output: '', errorOutput: 'error', exitCode: 1),
    ]);

    $client = new YahooFinanceClient;

    expect($client->fetchPricesBulk([['ticker' => 'AAPL', 'start_date' => '2024-01-01', 'end_date' => '2024-01-02']]))->toBe([]);
});

it('returns empty array when python script fails for fetchSectors', function () {
    Process::fake([
        '*fetch_sectors*' => Process::result(output: '', errorOutput: 'error', exitCode: 1),
    ]);

    $client = new YahooFinanceClient;

    expect($client->fetchSectors('AAPL'))->toBe([]);
});

it('parses successful search results from python', function () {
    $output = json_encode([
        'status' => 'ok',
        'data' => [
            ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'exchange' => 'NASDAQ', 'type' => 'Equity'],
        ],
    ]);

    Process::fake([
        '*search_ticker*' => Process::result(output: $output),
    ]);

    $client = new YahooFinanceClient;
    $results = $client->search('AAPL');

    expect($results)->toHaveCount(1)
        ->and($results[0]['symbol'])->toBe('AAPL');
});

it('parses successful fetchPrices results from python', function () {
    $output = json_encode([
        'status' => 'ok',
        'data' => [
            ['date' => '2024-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
        ],
    ]);

    Process::fake([
        '*fetch_prices.py*' => Process::result(output: $output),
    ]);

    $client = new YahooFinanceClient;
    $results = $client->fetchPrices('AAPL', '2024-02-19', '2024-02-20');

    expect($results)->toHaveCount(1)
        ->and((float) $results[0]['close'])->toBe(103.0);
});

it('parses successful fetchSectors results from python', function () {
    $output = json_encode([
        'status' => 'ok',
        'data' => ['technology' => 1.0],
    ]);

    Process::fake([
        '*fetch_sectors*' => Process::result(output: $output),
    ]);

    $client = new YahooFinanceClient;
    $results = $client->fetchSectors('AAPL');

    expect($results)->toEqual(['technology' => 1.0]);
});
