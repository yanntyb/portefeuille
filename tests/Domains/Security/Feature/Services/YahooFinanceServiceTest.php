<?php

use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Security\Exceptions\TickerResolutionException;
use App\Domains\Security\Models\Security;
use App\Domains\Security\Models\SecurityPrice;
use App\Domains\Security\Services\YahooFinanceClient;
use App\Domains\Security\Services\YahooFinanceService;

it('resolves a ticker from an ISIN', function () {
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->with('FR0011871110', null)
            ->andReturn([
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ]);
    });

    $service = app(YahooFinanceService::class);

    expect($service->resolveTickerFromIsin('FR0011871110'))->toBe('CW8.PA');
});

it('resolves a ticker using name as fallback', function () {
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->with('FR0007005181', 'CM-AM Dynamique International')
            ->andReturn([
                ['symbol' => '0P00000FMT.F', 'name' => 'CM-AM Dynamique International C', 'exchange' => 'Frankfurt', 'type' => 'Fund'],
            ]);
    });

    $service = app(YahooFinanceService::class);

    expect($service->resolveTickerFromIsin('FR0007005181', 'CM-AM Dynamique International'))->toBe('0P00000FMT.F');
});

it('throws TickerResolutionException when no result', function () {
    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->andReturn([]);
    });

    $service = app(YahooFinanceService::class);

    $service->resolveTickerFromIsin('XX0000000000');
})->throws(TickerResolutionException::class);

it('returns search results from searchTicker', function () {
    $expectedData = [
        ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
        ['symbol' => 'CW8.DE', 'name' => 'Amundi MSCI World', 'exchange' => 'Frankfurt', 'type' => 'ETF'],
    ];

    $this->mock(YahooFinanceClient::class, function ($mock) use ($expectedData) {
        $mock->shouldReceive('search')
            ->with('FR0011871110', null)
            ->andReturn($expectedData);
    });

    $service = app(YahooFinanceService::class);
    $results = $service->searchTicker('FR0011871110');

    expect($results)->toBe($expectedData);
});

it('fetches and stores prices for a security', function () {
    $security = Security::factory()->create(['isin' => 'FR0011871110', 'ticker' => 'CW8.PA']);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')
            ->andReturn([
                ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
                ['date' => '2026-02-20', 'open' => 103.0, 'high' => 107.0, 'low' => 102.0, 'close' => 106.0, 'volume' => 60000],
            ]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStorePrices($security);

    expect($count)->toBe(2);
    expect(SecurityPrice::where('security_id', $security->id)->count())->toBe(2);

    $this->assertDatabaseHas('asset_prices', [
        'security_id' => $security->id,
        'date' => '2026-02-19',
        'close' => 103.0,
    ]);
});

it('resolves the ticker if not set and saves it', function () {
    $security = Security::factory()->create(['isin' => 'FR0011871110', 'ticker' => null]);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->withArgs(fn ($query, $fallback) => $query === 'FR0011871110')
            ->andReturn([
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ]);
        $mock->shouldReceive('fetchPrices')
            ->andReturn([]);
    });

    $service = app(YahooFinanceService::class);
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

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')
            ->withArgs(function ($ticker, $startDate) {
                return $ticker === 'CW8.PA' && $startDate === '2026-02-19';
            })
            ->andReturn([
                ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
            ]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStorePrices($security);

    expect($count)->toBe(1);
    expect(SecurityPrice::where('security_id', $security->id)->count())->toBe(2);
});

it('fetches prices in bulk', function () {
    $security1 = Security::factory()->create(['ticker' => 'CW8.PA']);
    $security2 = Security::factory()->create(['ticker' => 'AAPL']);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchPricesBulk')
            ->andReturn([
                'CW8.PA' => [
                    ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
                ],
                'AAPL' => [
                    ['date' => '2026-02-19', 'open' => 200.0, 'high' => 210.0, 'low' => 195.0, 'close' => 205.0, 'volume' => 80000],
                ],
            ]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStorePricesBulk(Security::whereIn('id', [$security1->id, $security2->id])->get());

    expect($count)->toBe(2);
    expect(SecurityPrice::where('security_id', $security1->id)->count())->toBe(1);
    expect(SecurityPrice::where('security_id', $security2->id)->count())->toBe(1);
});

it('bulk fetch skips securities that are already up to date', function () {
    $security = Security::factory()->create(['ticker' => 'CW8.PA']);

    SecurityPrice::factory()->create([
        'security_id' => $security->id,
        'date' => today(),
        'close' => 100.0,
    ]);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchPricesBulk')
            ->andReturn([]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStorePricesBulk(Security::where('id', $security->id)->get());

    expect($count)->toBe(0);
});

it('bulk fetch resolves tickers for securities without one', function () {
    $security = Security::factory()->create(['ticker' => null, 'isin' => 'FR0011871110']);

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('search')
            ->withArgs(fn ($query, $fallback) => $query === 'FR0011871110')
            ->andReturn([
                ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
            ]);
        $mock->shouldReceive('fetchPricesBulk')
            ->andReturn([
                'CW8.PA' => [
                    ['date' => '2026-02-19', 'open' => 100.0, 'high' => 105.0, 'low' => 99.0, 'close' => 103.0, 'volume' => 50000],
                ],
            ]);
    });

    $service = app(YahooFinanceService::class);
    $count = $service->fetchAndStorePricesBulk(Security::where('id', $security->id)->get());

    expect($count)->toBe(1);
    expect($security->fresh()->ticker)->toBe('CW8.PA');
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

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')
            ->withArgs(function ($ticker, $startDate) {
                return $ticker === 'MEUD.PA' && $startDate === '2023-03-15';
            })
            ->andReturn([
                ['date' => '2023-03-15', 'open' => 10.0, 'high' => 11.0, 'low' => 9.0, 'close' => 10.5, 'volume' => 1000],
            ]);
    });

    $service = app(YahooFinanceService::class);
    $service->fetchAndStorePrices($security);
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

    $this->mock(YahooFinanceClient::class, function ($mock) {
        $mock->shouldReceive('fetchPricesBulk')
            ->withArgs(function ($tickers) {
                return count($tickers) === 1 && $tickers[0]['start_date'] === '2023-03-15';
            })
            ->andReturn([
                'MEUD.PA' => [
                    ['date' => '2023-03-15', 'open' => 10.0, 'high' => 11.0, 'low' => 9.0, 'close' => 10.5, 'volume' => 1000],
                ],
            ]);
    });

    $service = app(YahooFinanceService::class);
    $service->fetchAndStorePricesBulk(Security::where('id', $security->id)->get());
});
