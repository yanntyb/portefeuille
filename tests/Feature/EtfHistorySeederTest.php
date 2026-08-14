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
use Database\Seeders\EtfHistorySeeder;
use Illuminate\Support\Facades\DB;

/**
 * 24 points mensuels (1er du mois, à partir de 2024-01) oscillant autour de 100,
 * pour que le momentum 3 mois soit tantôt positif, tantôt négatif.
 *
 * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
 */
function etfMonthlyRows(): array
{
    $rows = [];
    for ($i = 0; $i < 24; $i++) {
        $year = 2024 + intdiv($i, 12);
        $month = ($i % 12) + 1;
        $close = round(100 + 15 * sin($i * M_PI / 6), 4);
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

/** Nombre de mois investissables : ceux ayant un close 3 mois avant (24 points - 3). */
const INVESTABLE_MONTHS = 21;

function fakeYahoo(PythonResult $result): void
{
    $fake = (new FakePythonRunner)->withResult(YahooScript::Prices->path(), $result);
    app()->instance(PythonRunner::class, $fake);
}

it('creates the four ETFs with their Yahoo price history', function () {
    fakeYahoo(new PythonResult('ok', etfMonthlyRows()));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);

    $etfs = Instrument::query()->where('type', InstrumentType::ETF)->pluck('ticker');

    expect($etfs)->toContain('PE500.PA', 'PUST.PA', 'MEUD.PA', 'AEEM.PA')
        ->and(Price::query()->count())->toBeGreaterThan(0)
        ->and(Price::query()->distinct()->count('date'))->toBeGreaterThan(1);
});

it('invests a 1000 € monthly DCA following the momentum', function () {
    fakeYahoo(new PythonResult('ok', etfMonthlyRows()));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);

    $buys = Transaction::query()->where('type', TransactionType::Buy)->get();
    $months = $buys->map(fn (Transaction $t) => $t->date->format('Y-m'));

    expect($buys)->not->toBeEmpty()
        // au plus un achat par mois
        ->and($months->unique()->count())->toBe($buys->count())
        // frais fixes et budget de 1000 € (quantité = 1000 / cours)
        ->and($buys->every(fn (Transaction $t) => (float) $t->fees === 1.0))->toBeTrue()
        ->and($buys->every(fn (Transaction $t) => (float) $t->quantity === round(1000.0 / (float) $t->unit_price, 4)))->toBeTrue()
        // les mois à momentum négatif sont sautés : moins d'achats que de mois investissables
        ->and($buys->count())->toBeLessThan(INVESTABLE_MONTHS)
        ->and($buys->count())->toBeGreaterThan(0);
});

it('is idempotent', function () {
    fakeYahoo(new PythonResult('ok', etfMonthlyRows()));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);
    $txCount = Transaction::query()->count();

    $this->seed(EtfHistorySeeder::class);

    expect(Transaction::query()->count())->toBe($txCount);
});

it('stores prices with the canonical date format, one row per day', function () {
    fakeYahoo(new PythonResult('ok', etfMonthlyRows()));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);
    $this->seed(EtfHistorySeeder::class);

    $dates = DB::table('asset_prices')->select('asset_id', 'date')->get();
    $days = $dates->map(fn (object $row): string => $row->asset_id.'@'.substr((string) $row->date, 0, 10));

    expect($dates)->not->toBeEmpty()
        ->and($dates->every(fn (object $row): bool => strlen((string) $row->date) === 19))->toBeTrue()
        ->and($days->unique()->count())->toBe($dates->count());
});

it('degrades gracefully when Yahoo returns no data', function () {
    fakeYahoo(new PythonResult('error', error: 'boom'));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);

    expect(Instrument::query()->where('type', InstrumentType::ETF)->count())->toBe(4)
        ->and(Transaction::query()->count())->toBe(0);
});
