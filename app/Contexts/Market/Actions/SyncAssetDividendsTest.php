<?php

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\DividendFeedException;
use App\Contexts\Market\Ports\DividendFeedPort;
use Illuminate\Support\Facades\Log;

/**
 * Lie un flux qui couvre les actions et les ETF, enregistre les demandes reçues dans $captured
 * et rend les détachements donnés, indexés par ticker.
 *
 * @param  array<string, array<int, DividendData>>  $dividends
 * @param  array<int, mixed>  $captured
 */
function fakeDividendFeed(array $dividends, array &$captured = []): void
{
    test()->mock(DividendFeedPort::class, function ($mock) use ($dividends, &$captured) {
        $mock->shouldReceive('supportsDividendFeed')
            ->andReturnUsing(fn (InstrumentType $type): bool => in_array($type, [InstrumentType::Stock, InstrumentType::ETF]));
        $mock->shouldReceive('fetchDividends')
            ->andReturnUsing(function (array $requests) use ($dividends, &$captured) {
                $captured = $requests;

                return $dividends;
            });
    });
}

it('reprend au dernier détachement connu de chaque actif', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);
    $this->travelTo('2026-08-19 10:00:00');
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('CW8.PA')
        ->and($captured[0]->startDate)->toBe('2026-03-05')
        ->and($captured[0]->endDate)->toBe('2026-08-19');
});

it('retombe sur une fenêtre de soixante mois sans détachement stocké', function () {
    Instrument::factory()->create(['ticker' => 'CW8.PA']);
    $this->travelTo('2026-08-19 10:00:00');
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)();

    expect($captured[0]->startDate)->toBe('2021-08-19');
});

it('laisse une date de début explicite gagner sur l\'historique stocké', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)(null, '2015-01-01');

    expect($captured[0]->startDate)->toBe('2015-01-01');
});

it('écarte les instruments sans ticker et les types sans dividende', function () {
    Instrument::factory()->create(['ticker' => null]);
    Instrument::factory()->ofType(InstrumentType::Crypto)->create(['ticker' => 'BTC-EUR']);
    Instrument::factory()->ofType(InstrumentType::Bond)->create(['ticker' => 'OAT.PA']);
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'CW8.PA']);
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('CW8.PA');
});

it('écrit les détachements récupérés et rapporte leur compte par ticker', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    fakeDividendFeed(['CW8.PA' => [
        new DividendData('2026-03-05', 0.51),
        new DividendData('2026-06-04', 0.62),
    ]]);

    $report = app(SyncAssetDividends::class)();

    expect($report->synced)->toBe(['CW8.PA' => 2])
        ->and($report->failed)->toBe([])
        ->and(Dividend::query()->where('asset_id', $instrument->id)->count())->toBe(2);
});

it('rapporte zéro détachement pour un ticker absent de la charge utile', function () {
    Instrument::factory()->create(['ticker' => 'ACC.PA']);
    fakeDividendFeed([]);

    $report = app(SyncAssetDividends::class)();

    expect($report->synced)->toBe(['ACC.PA' => 0])
        ->and($report->isTotalFailure())->toBeFalse();
});

it('marque tous les tickers en échec et journalise quand le flux lève', function () {
    Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    Log::spy();
    $this->mock(DividendFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->andThrow(DividendFeedException::fetchFailed('yfinance rate limited'));
    });

    $report = app(SyncAssetDividends::class)();

    expect($report->failed)->toBe(['CW8.PA', 'PE500.PA'])
        ->and($report->synced)->toBe([])
        ->and($report->error)->toBe('yfinance rate limited')
        ->and($report->isTotalFailure())->toBeTrue();

    Log::shouldHaveReceived('error')->once();
});

it('restreint la synchronisation à l\'actif demandé', function () {
    $first = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)($first->id);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('CW8.PA');
});

it('n\'appelle jamais le flux quand aucun instrument n\'est éligible', function () {
    Instrument::factory()->create(['ticker' => null]);
    $this->mock(DividendFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->never();
    });

    expect(app(SyncAssetDividends::class)()->total())->toBe(0);
});
