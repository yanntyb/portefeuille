<?php

use App\Contexts\Market\Actions\SyncAssetPrices;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;
use Illuminate\Support\Facades\Log;

/**
 * Bind a feed that supports Stock and ETF, records the requests it receives
 * in $captured, and returns the given prices keyed by ticker.
 *
 * @param  array<string, array<int, PriceData>>  $prices
 * @param  array<int, mixed>  $captured
 */
function fakeFeed(array $prices, array &$captured = []): void
{
    test()->mock(PriceFeedPort::class, function ($mock) use ($prices, &$captured) {
        $mock->shouldReceive('supports')
            ->andReturnUsing(fn (InstrumentType $type): bool => in_array($type, [InstrumentType::Stock, InstrumentType::ETF]));
        $mock->shouldReceive('fetchPrices')
            ->andReturnUsing(function (array $requests) use ($prices, &$captured) {
                $captured = $requests;

                return $prices;
            });
    });
}

it('resumes from the last stored price of each asset', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-08-11']);
    $this->travelTo('2026-08-13 10:00:00');
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('AAPL')
        ->and($captured[0]->startDate)->toBe('2026-08-11')
        ->and($captured[0]->endDate)->toBe('2026-08-13');
});

it('falls back to a twelve month window without any stored price', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->travelTo('2026-08-13 10:00:00');
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)();

    expect($captured[0]->startDate)->toBe('2025-08-13');
});

it('lets an explicit since date win over the stored history', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-08-11']);
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)(null, '2020-01-01');

    expect($captured[0]->startDate)->toBe('2020-01-01');
});

it('skips instruments without a ticker and unsupported types', function () {
    Instrument::factory()->create(['ticker' => null]);
    Instrument::factory()->ofType(InstrumentType::Crypto)->create(['ticker' => 'BTC-EUR']);
    Instrument::factory()->create(['ticker' => 'AAPL']);
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('AAPL');
});

it('writes the fetched prices and reports the count per ticker', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    fakeFeed(['AAPL' => [
        new PriceData(date: '2026-08-12', close: 10.0),
        new PriceData(date: '2026-08-13', close: 11.0),
    ]]);

    $report = app(SyncAssetPrices::class)();

    expect($report->synced)->toBe(['AAPL' => 2])
        ->and($report->failed)->toBe([])
        ->and(Price::query()->where('asset_id', $instrument->id)->count())->toBe(2);
});

it('reports zero prices for a ticker absent from the payload', function () {
    Instrument::factory()->create(['ticker' => 'DEAD.PA']);
    fakeFeed([]);

    $report = app(SyncAssetPrices::class)();

    expect($report->synced)->toBe(['DEAD.PA' => 0])
        ->and($report->isTotalFailure())->toBeFalse();
});

it('marks every ticker as failed when the feed throws', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('boom'));
    });

    $report = app(SyncAssetPrices::class)();

    expect($report->failed)->toBe(['AAPL', 'PE500.PA'])
        ->and($report->synced)->toBe([])
        ->and($report->isTotalFailure())->toBeTrue();
});

it('reports and logs the provider error behind a total failure', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    Log::spy();
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('yfinance rate limited'));
    });

    $report = app(SyncAssetPrices::class)();

    expect($report->error)->toBe('yfinance rate limited');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($context['error'], 'yfinance rate limited')
            && $context['tickers'] === ['AAPL']);
});

it('leaves the report error null on a successful sync', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    fakeFeed(['AAPL' => [new PriceData(date: '2026-08-13', close: 10.0)]]);

    expect(app(SyncAssetPrices::class)()->error)->toBeNull();
});

it('restricts the sync to the given asset', function () {
    $first = Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)($first->id);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('AAPL');
});

it('never calls the feed when no instrument is eligible', function () {
    Instrument::factory()->create(['ticker' => null]);
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->never();
    });

    $report = app(SyncAssetPrices::class)();

    expect($report->total())->toBe(0);
});
