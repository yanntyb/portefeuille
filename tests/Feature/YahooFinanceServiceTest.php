<?php

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Services\YahooFinanceService;
use Illuminate\Support\Facades\Process;

it('resolves a ticker from an ISIN', function () {
    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;

    expect($service->resolveTickerFromIsin('FR0011871110'))->toBe('CW8.PA');
});

it('resolves a ticker using name as fallback', function () {
    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => '0P00000FMT.F', 'name' => 'CM-AM Dynamique International C', 'exchange' => 'Frankfurt', 'type' => 'Fund'],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;

    expect($service->resolveTickerFromIsin('FR0007005181', 'CM-AM Dynamique International'))->toBe('0P00000FMT.F');

    Process::assertRan(function ($process) {
        $input = json_decode($process->input, true);

        return $input['query'] === 'FR0007005181'
            && $input['fallback_query'] === 'CM-AM Dynamique International';
    });
});

it('throws TickerResolutionException when no result', function () {
    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [],
        ])),
    ]);

    $service = new YahooFinanceService;

    $service->resolveTickerFromIsin('XX0000000000');
})->throws(TickerResolutionException::class);

it('returns search results from searchTicker', function () {
    $expectedData = [
        ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
        ['symbol' => 'CW8.DE', 'name' => 'Amundi MSCI World', 'exchange' => 'Frankfurt', 'type' => 'ETF'],
    ];

    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => $expectedData,
        ])),
    ]);

    $service = new YahooFinanceService;
    $results = $service->searchTicker('FR0011871110');

    expect($results)->toBe($expectedData);
});

it('fetches and stores prices for a security', function () {
    $security = Security::factory()->create(['isin' => 'FR0011871110', 'ticker' => 'CW8.PA']);

    Process::fake([
        '*fetch_prices.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
                ['date' => '2026-02-20', 'open' => 103.0, 'high' => 107.0, 'low' => 102.0, 'close' => 106.0, 'volume' => 60000],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
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

    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ],
        ])),
        '*fetch_prices.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [],
        ])),
    ]);

    $service = new YahooFinanceService;
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

    Process::fake([
        '*fetch_prices.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
    $count = $service->fetchAndStorePrices($security);

    expect($count)->toBe(1);
    expect(SecurityPrice::where('security_id', $security->id)->count())->toBe(2);

    Process::assertRan(function ($process) {
        $input = json_decode($process->input, true);

        return ($input['start_date'] ?? null) === '2026-02-19';
    });
});

it('calls fetch_prices.py with 60s timeout', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    Process::fake([
        '*fetch_prices.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [],
        ])),
    ]);

    $service = new YahooFinanceService;
    $service->fetchAndStorePrices($security);

    Process::assertRan(function ($process) {
        return str_contains($process->command, 'fetch_prices.py')
            && $process->timeout === 60;
    });
});
