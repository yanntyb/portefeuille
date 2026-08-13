<?php

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;

it('reports the number of prices per ticker and a summary', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'DEAD.PA']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn(['AAPL' => [
            new PriceData(date: '2026-08-12', close: 10.0),
            new PriceData(date: '2026-08-13', close: 11.0),
        ]]);
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('AAPL : 2 prix')
        ->expectsOutputToContain('DEAD.PA : 0 prix')
        ->expectsOutput('2 instruments, 2 synchronisés, 0 échec')
        ->assertSuccessful();
});

it('warns when no instrument is eligible', function () {
    Instrument::factory()->create(['ticker' => null]);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->never();
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('Aucun instrument à synchroniser.')
        ->assertSuccessful();
});

it('fails when the feed is unreachable', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('boom'));
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('AAPL : échec')
        ->expectsOutput('1 instrument, 0 synchronisé, 1 échec')
        ->assertFailed();
});

it('names the provider error behind a total failure', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('yfinance rate limited'));
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('Échec de la récupération auprès du fournisseur : yfinance rate limited')
        ->assertFailed();
});

it('fails on an unknown asset id instead of reporting nothing to sync', function () {
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->never();
    });

    $this->artisan('market:sync-prices', ['--asset' => 999])
        ->expectsOutputToContain('Aucun instrument ne porte l\'identifiant « 999 ».')
        ->doesntExpectOutputToContain('Aucun instrument à synchroniser.')
        ->assertFailed();
});

it('fails on an asset id that is not a number', function () {
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->never();
    });

    $this->artisan('market:sync-prices', ['--asset' => 'abc'])
        ->expectsOutputToContain('Aucun instrument ne porte l\'identifiant « abc ».')
        ->assertFailed();
});

it('fails on a since date that is not a Y-m-d date', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->never();
    });

    $this->artisan('market:sync-prices', ['--since' => '13/08/2026'])
        ->expectsOutputToContain('Date de début invalide : « 13/08/2026 ». Format attendu : AAAA-MM-JJ.')
        ->assertFailed();
});

it('pluralizes the summary when several tickers fail', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'DEAD.PA']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('boom'));
    });

    $this->artisan('market:sync-prices')
        ->expectsOutput('2 instruments, 0 synchronisé, 2 échecs')
        ->assertFailed();
});

it('restricts the sync to the given asset', function () {
    $first = Instrument::factory()->create(['ticker' => 'AAPL']);
    $second = Instrument::factory()->create(['ticker' => 'PE500.PA']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn([
            'AAPL' => [new PriceData(date: '2026-08-13', close: 10.0)],
            'PE500.PA' => [new PriceData(date: '2026-08-13', close: 20.0)],
        ]);
    });

    $this->artisan('market:sync-prices', ['--asset' => $first->id])->assertSuccessful();

    expect(Price::query()->where('asset_id', $first->id)->count())->toBe(1)
        ->and(Price::query()->where('asset_id', $second->id)->count())->toBe(0);
});

it('forwards the since option to the feed window', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    $captured = [];

    $this->mock(PriceFeedPort::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturnUsing(function (array $requests) use (&$captured) {
            $captured = $requests;

            return [];
        });
    });

    $this->artisan('market:sync-prices', ['--since' => '2020-01-01'])->assertSuccessful();

    expect($captured[0]->startDate)->toBe('2020-01-01');
});

it('syncs every asset when the asset option is passed empty', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn([
            'AAPL' => [new PriceData(date: '2026-08-13', close: 10.0)],
            'PE500.PA' => [new PriceData(date: '2026-08-13', close: 20.0)],
        ]);
    });

    $this->artisan('market:sync-prices', ['--asset' => ''])
        ->expectsOutput('2 instruments, 2 synchronisés, 0 échec')
        ->assertSuccessful();
});

it('resumes from the stored history when the since option is passed empty', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-08-11']);
    $captured = [];

    $this->mock(PriceFeedPort::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('supportsPriceFeed')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturnUsing(function (array $requests) use (&$captured) {
            $captured = $requests;

            return [];
        });
    });

    $this->artisan('market:sync-prices', ['--since' => ''])->assertSuccessful();

    expect($captured[0]->startDate)->toBe('2026-08-11');
});
