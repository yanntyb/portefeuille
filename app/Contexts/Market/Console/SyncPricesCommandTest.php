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
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn(['AAPL' => [
            new PriceData(date: '2026-08-12', close: 10.0),
            new PriceData(date: '2026-08-13', close: 11.0),
        ]]);
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('AAPL : 2 prix')
        ->expectsOutputToContain('DEAD.PA : 0 prix')
        ->expectsOutputToContain('2 instruments, 2 synchronisés, 0 échec')
        ->assertSuccessful();
});

it('warns when no instrument is eligible', function () {
    Instrument::factory()->create(['ticker' => null]);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->never();
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('Aucun instrument à synchroniser.')
        ->assertSuccessful();
});

it('fails when the feed is unreachable', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('boom'));
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('AAPL : échec')
        ->expectsOutputToContain('1 instrument, 0 synchronisé, 1 échec')
        ->assertFailed();
});

it('restricts the sync to the given asset', function () {
    $first = Instrument::factory()->create(['ticker' => 'AAPL']);
    $second = Instrument::factory()->create(['ticker' => 'PE500.PA']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
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
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturnUsing(function (array $requests) use (&$captured) {
            $captured = $requests;

            return [];
        });
    });

    $this->artisan('market:sync-prices', ['--since' => '2020-01-01'])->assertSuccessful();

    expect($captured[0]->startDate)->toBe('2020-01-01');
});
