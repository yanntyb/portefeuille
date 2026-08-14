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
use Database\Seeders\BtcDcaSeeder;
use Illuminate\Support\Facades\DB;

/**
 * 36 points mensuels (1er du mois, à partir de 2023-01) autour de 60 000 €,
 * pour exercer la précision 8 décimales d'un DCA de 50 €.
 *
 * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
 */
function btcMonthlyRows(): array
{
    $rows = [];
    for ($i = 0; $i < 36; $i++) {
        $year = 2023 + intdiv($i, 12);
        $month = ($i % 12) + 1;
        $close = round(60000 + 10000 * sin($i * M_PI / 6), 4);
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

/**
 * Le seeder délègue à market:sync-prices : le script Python à simuler est celui du fetch
 * groupé, dont la réponse est indexée par ticker.
 */
function fakeBtcYahoo(PythonResult $result): void
{
    $bulk = $result->ok()
        ? new PythonResult('ok', ['BTC-EUR' => $result->data])
        : $result;

    $fake = (new FakePythonRunner)->withResult(YahooScript::PricesBulk->path(), $bulk);
    app()->instance(PythonRunner::class, $fake);
}

it('creates the BTC instrument with its price history', function () {
    fakeBtcYahoo(new PythonResult('ok', btcMonthlyRows()));
    User::factory()->create();

    $this->seed(BtcDcaSeeder::class);

    $btc = Instrument::query()->where('ticker', 'BTC-EUR')->first();

    expect($btc)->not->toBeNull()
        ->and($btc->type)->toBe(InstrumentType::Crypto)
        ->and($btc->name)->toBe('Bitcoin')
        ->and(Price::query()->where('asset_id', $btc->id)->count())->toBeGreaterThan(0);
});

it('invests a fixed 50 € monthly DCA on BTC', function () {
    fakeBtcYahoo(new PythonResult('ok', btcMonthlyRows()));
    User::factory()->create();

    $this->seed(BtcDcaSeeder::class);

    $buys = Transaction::query()->where('type', TransactionType::Buy)->get();
    $months = $buys->map(fn (Transaction $t) => $t->date->format('Y-m'));

    expect($buys)->not->toBeEmpty()
        // un achat par mois
        ->and($months->unique()->count())->toBe($buys->count())
        // pas de frais, quantité = 50 / cours arrondie à 8 décimales
        ->and($buys->every(fn (Transaction $t) => (float) $t->fees === 0.0))->toBeTrue()
        ->and($buys->every(fn (Transaction $t) => (float) $t->quantity === round(50.0 / (float) $t->unit_price, 8)))->toBeTrue()
        // la précision 8 décimales tient le 50 € (à ε près)
        ->and($buys->every(fn (Transaction $t) => abs((float) $t->quantity * (float) $t->unit_price - 50.0) < 0.01))->toBeTrue();
});

it('is idempotent', function () {
    fakeBtcYahoo(new PythonResult('ok', btcMonthlyRows()));
    User::factory()->create();

    $this->seed(BtcDcaSeeder::class);
    $txCount = Transaction::query()->count();

    $this->seed(BtcDcaSeeder::class);

    expect(Transaction::query()->count())->toBe($txCount);
});

it('stores prices with the canonical date format, one row per day', function () {
    fakeBtcYahoo(new PythonResult('ok', btcMonthlyRows()));
    User::factory()->create();

    $this->seed(BtcDcaSeeder::class);
    $this->seed(BtcDcaSeeder::class);

    $dates = DB::table('asset_prices')->select('asset_id', 'date')->get();
    $days = $dates->map(fn (object $row): string => $row->asset_id.'@'.substr((string) $row->date, 0, 10));

    expect($dates)->not->toBeEmpty()
        ->and($dates->every(fn (object $row): bool => strlen((string) $row->date) === 19))->toBeTrue()
        ->and($days->unique()->count())->toBe($dates->count());
});

it('degrades gracefully when Yahoo returns no data', function () {
    fakeBtcYahoo(new PythonResult('error', error: 'boom'));
    User::factory()->create();

    $this->seed(BtcDcaSeeder::class);

    expect(Instrument::query()->where('ticker', 'BTC-EUR')->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0);
});
