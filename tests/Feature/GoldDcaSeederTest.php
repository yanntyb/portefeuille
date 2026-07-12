<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Shared\Python\FakePythonRunner;
use App\Shared\Python\PythonResult;
use App\Shared\Python\PythonRunner;
use Database\Seeders\GoldDcaSeeder;

/**
 * 36 points mensuels (1er du mois, à partir de 2023-01) autour de 2 500 € l'once,
 * pour exercer la précision 8 décimales d'un DCA de 50 €.
 *
 * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
 */
function goldMonthlyRows(): array
{
    $rows = [];
    for ($i = 0; $i < 36; $i++) {
        $year = 2023 + intdiv($i, 12);
        $month = ($i % 12) + 1;
        $close = round(2500 + 400 * sin($i * M_PI / 6), 4);
        $rows[] = [
            'date' => sprintf('%04d-%02d-01', $year, $month),
            'open' => $close,
            'high' => $close,
            'low' => $close,
            'close' => $close,
            'volume' => 1000 + $i,
        ];
    }

    return $rows;
}

function fakeGoldYahoo(PythonResult $result): void
{
    $fake = (new FakePythonRunner)->withResult(YahooScript::Prices->path(), $result);
    app()->instance(PythonRunner::class, $fake);
}

it('creates the gold instrument with its price history', function () {
    fakeGoldYahoo(new PythonResult('ok', goldMonthlyRows()));
    User::factory()->create();

    $this->seed(GoldDcaSeeder::class);

    $gold = Instrument::query()->where('ticker', '4GLD.DE')->first();

    expect($gold)->not->toBeNull()
        ->and($gold->type)->toBe(InstrumentType::Commodity)
        ->and($gold->name)->toBe('Or (Xetra-Gold)')
        ->and(Price::query()->where('asset_id', $gold->id)->count())->toBeGreaterThan(0);
});

it('invests a fixed 50 € monthly DCA on gold', function () {
    fakeGoldYahoo(new PythonResult('ok', goldMonthlyRows()));
    User::factory()->create();

    $this->seed(GoldDcaSeeder::class);

    $buys = Transaction::query()->where('type', TransactionType::Buy)->get();
    $months = $buys->map(fn (Transaction $t) => $t->date->format('Y-m'));

    expect($buys)->not->toBeEmpty()
        ->and($months->unique()->count())->toBe($buys->count())
        ->and($buys->every(fn (Transaction $t) => (float) $t->fees === 0.0))->toBeTrue()
        ->and($buys->every(fn (Transaction $t) => (float) $t->quantity === round(50.0 / (float) $t->unit_price, 8)))->toBeTrue()
        ->and($buys->every(fn (Transaction $t) => abs((float) $t->quantity * (float) $t->unit_price - 50.0) < 0.01))->toBeTrue();
});

it('is idempotent', function () {
    fakeGoldYahoo(new PythonResult('ok', goldMonthlyRows()));
    User::factory()->create();

    $this->seed(GoldDcaSeeder::class);
    $txCount = Transaction::query()->count();

    $this->seed(GoldDcaSeeder::class);

    expect(Transaction::query()->count())->toBe($txCount);
});

it('degrades gracefully when Yahoo returns no data', function () {
    fakeGoldYahoo(new PythonResult('error', error: 'boom'));
    User::factory()->create();

    $this->seed(GoldDcaSeeder::class);

    expect(Instrument::query()->where('ticker', '4GLD.DE')->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0);
});
