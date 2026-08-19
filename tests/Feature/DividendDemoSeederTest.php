<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Shared\Python\FakePythonRunner;
use App\Shared\Python\PythonResult;
use App\Shared\Python\PythonRunner;
use Database\Seeders\DividendDemoSeeder;

const DIVIDEND_DEMO_TICKERS = ['AAPL', 'JNJ'];

/**
 * Douze points mensuels (1er du mois) pour donner un cours à chaque titre.
 *
 * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
 */
function dividendDemoPriceRows(): array
{
    $rows = [];
    for ($i = 0; $i < 12; $i++) {
        $close = round(150 + 10 * sin($i * M_PI / 6), 4);
        $rows[] = [
            'date' => today()->subMonths(11 - $i)->startOfMonth()->format('Y-m-d'),
            'open' => $close,
            'high' => $close,
            'low' => $close,
            'close' => $close,
            'volume' => 1000 + $i,
        ];
    }

    return $rows;
}

/**
 * Trois détachements par titre, deux hors des douze derniers mois et un dedans : la fiche
 * instrument affiche alors un total et un perçu à douze mois qui ne coïncident pas.
 *
 * @return list<array{ex_date: string, amount_per_share: float}>
 */
function dividendDemoRows(): array
{
    return [
        ['ex_date' => today()->subYears(2)->format('Y-m-d'), 'amount_per_share' => 0.22],
        ['ex_date' => today()->subYear()->subMonths(2)->format('Y-m-d'), 'amount_per_share' => 0.23],
        ['ex_date' => today()->subMonths(3)->format('Y-m-d'), 'amount_per_share' => 0.24],
    ];
}

/**
 * Le seeder délègue à market:sync-prices et market:sync-dividends : les scripts Python à
 * simuler sont donc ceux du fetch groupé de chacun.
 */
function fakeDividendDemoYahoo(?PythonResult $prices = null): void
{
    $bulkPrices = $prices ?? new PythonResult('ok', array_fill_keys(DIVIDEND_DEMO_TICKERS, dividendDemoPriceRows()));
    $bulkDividends = new PythonResult('ok', array_fill_keys(DIVIDEND_DEMO_TICKERS, dividendDemoRows()));

    $fake = (new FakePythonRunner)
        ->withResult(YahooScript::PricesBulk->path(), $bulkPrices)
        ->withResult(YahooScript::DividendsBulk->path(), $bulkDividends);

    app()->instance(PythonRunner::class, $fake);
}

it('creates the demo stocks with a position and their dividend history', function () {
    fakeDividendDemoYahoo();
    User::factory()->create();

    $this->seed(DividendDemoSeeder::class);

    $stocks = Instrument::query()->whereIn('ticker', DIVIDEND_DEMO_TICKERS)->get();

    expect($stocks)->toHaveCount(2)
        ->and($stocks->every(fn (Instrument $instrument): bool => $instrument->type === InstrumentType::Stock))->toBeTrue()
        ->and(Transaction::query()->where('type', TransactionType::Buy)->count())->toBe(2)
        ->and(Price::query()->whereIn('asset_id', $stocks->pluck('id'))->count())->toBeGreaterThan(0)
        ->and(Dividend::query()->whereIn('asset_id', $stocks->pluck('id'))->count())->toBe(6);
});

it('exposes a non-empty dividend history through the same action as the instrument sheet', function () {
    fakeDividendDemoYahoo();
    $user = User::factory()->create();

    $this->seed(DividendDemoSeeder::class);

    $aapl = Instrument::query()->where('ticker', 'AAPL')->firstOrFail();

    $history = app(GetAssetDividendHistory::class)($user->id, $aapl->id);

    expect($history->receipts)->not->toBeEmpty()
        ->and($history->totalReceived)->toBeGreaterThan(0.0)
        // deux détachements achetés avant la fenêtre des douze mois, un seul dedans.
        ->and($history->last12Months)->toBeLessThan($history->totalReceived);
});

it('is idempotent', function () {
    fakeDividendDemoYahoo();
    User::factory()->create();

    $this->seed(DividendDemoSeeder::class);
    $txCount = Transaction::query()->count();
    $dividendCount = Dividend::query()->count();

    $this->seed(DividendDemoSeeder::class);

    expect(Transaction::query()->count())->toBe($txCount)
        ->and(Dividend::query()->count())->toBe($dividendCount);
});

it('degrades gracefully when Yahoo returns no data', function () {
    fakeDividendDemoYahoo(new PythonResult('error', error: 'boom'));
    User::factory()->create();

    $this->seed(DividendDemoSeeder::class);

    expect(Instrument::query()->whereIn('ticker', DIVIDEND_DEMO_TICKERS)->count())->toBe(2)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(Dividend::query()->count())->toBe(0);
});
