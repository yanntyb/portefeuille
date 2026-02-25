<?php

use App\Exceptions\TickerResolutionException;
use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Transaction;
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

it('fetches prices in bulk using a single process', function () {
    $security1 = Security::factory()->create(['ticker' => 'CW8.PA']);
    $security2 = Security::factory()->create(['ticker' => 'AAPL']);

    Process::fake([
        '*fetch_prices_bulk.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                'CW8.PA' => [
                    ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
                ],
                'AAPL' => [
                    ['date' => '2026-02-19', 'open' => 200.0, 'high' => 210.0, 'low' => 195.0, 'close' => 205.0, 'volume' => 80000],
                ],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
    $count = $service->fetchAndStorePricesBulk(Security::whereIn('id', [$security1->id, $security2->id])->get());

    expect($count)->toBe(2);
    expect(SecurityPrice::where('security_id', $security1->id)->count())->toBe(1);
    expect(SecurityPrice::where('security_id', $security2->id)->count())->toBe(1);

    Process::assertRan(function ($process) {
        return str_contains($process->command, 'fetch_prices_bulk.py');
    });
});

it('bulk fetch skips securities that are already up to date', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => today(),
        'close' => 100.0,
    ]);

    Process::fake([
        '*fetch_prices_bulk.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [],
        ])),
    ]);

    $service = new YahooFinanceService;
    $count = $service->fetchAndStorePricesBulk(Security::where('id', $security->id)->get());

    expect($count)->toBe(0);
});

it('bulk fetch resolves tickers for securities without one', function () {
    $security = Security::factory()->create(['ticker' => null, 'isin' => 'FR0011871110']);

    Process::fake([
        '*search_ticker.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ],
        ])),
        '*fetch_prices_bulk.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                'CW8.PA' => [
                    ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
                ],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
    $count = $service->fetchAndStorePricesBulk(Security::where('id', $security->id)->get());

    expect($count)->toBe(1);
    expect($security->fresh()->ticker)->toBe('CW8.PA');
});

it('bulk fetch calls fetch_prices_bulk.py with 120s timeout', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    Process::fake([
        '*fetch_prices_bulk.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [],
        ])),
    ]);

    $service = new YahooFinanceService;
    $service->fetchAndStorePricesBulk(Security::where('id', $security->id)->get());

    Process::assertRan(function ($process) {
        return str_contains($process->command, 'fetch_prices_bulk.py')
            && $process->timeout === 120;
    });
});

it('fetches from earliest transaction date when prices have a gap', function () {
    $security = Security::factory()->create(['ticker' => 'MEUD.PA']);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'date' => '2023-03-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-02-19',
        'close' => 50.0,
    ]);

    Process::fake([
        '*fetch_prices.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                ['date' => '2023-03-15', 'open' => 10.0, 'high' => 11.0, 'low' => 9.0, 'close' => 10.5, 'volume' => 1000],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
    $service->fetchAndStorePrices($security);

    Process::assertRan(function ($process) {
        $input = json_decode($process->input, true);

        return ($input['start_date'] ?? null) === '2023-03-15';
    });
});

it('bulk fetch fills price gaps from earliest transaction date', function () {
    $security = Security::factory()->create(['ticker' => 'MEUD.PA']);

    Transaction::factory()->create([
        'security_id' => $security->id,
        'date' => '2023-03-15',
    ]);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => '2024-02-19',
        'close' => 50.0,
    ]);

    Process::fake([
        '*fetch_prices_bulk.py*' => Process::result(output: json_encode([
            'status' => 'ok',
            'data' => [
                'MEUD.PA' => [
                    ['date' => '2023-03-15', 'open' => 10.0, 'high' => 11.0, 'low' => 9.0, 'close' => 10.5, 'volume' => 1000],
                ],
            ],
        ])),
    ]);

    $service = new YahooFinanceService;
    $service->fetchAndStorePricesBulk(Security::where('id', $security->id)->get());

    Process::assertRan(function ($process) {
        $input = json_decode($process->input, true);
        $tickers = $input['tickers'] ?? [];

        return count($tickers) === 1 && $tickers[0]['start_date'] === '2023-03-15';
    });
});
