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

/**
 * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
 */
function etfPriceRows(): array
{
    $rows = [];
    for ($i = 0; $i < 12; $i++) {
        $close = 100.0 + $i;
        $rows[] = [
            'date' => sprintf('2021-01-%02d', $i + 1),
            'open' => $close,
            'high' => $close,
            'low' => $close,
            'close' => $close,
            'volume' => 1000 + $i,
        ];
    }

    return $rows;
}

function fakeYahoo(PythonResult $result): void
{
    $fake = (new FakePythonRunner)->withResult(YahooScript::Prices->path(), $result);
    app()->instance(PythonRunner::class, $fake);
}

it('seeds the four ETFs with their Yahoo price history', function () {
    fakeYahoo(new PythonResult('ok', etfPriceRows()));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);

    $etfs = Instrument::query()->where('type', InstrumentType::ETF)->pluck('ticker');

    expect($etfs)->toContain('PE500.PA', 'PUST.PA', 'MEUD.PA', 'AEEM.PA')
        ->and(Price::query()->count())->toBeGreaterThan(0)
        ->and(Price::query()->distinct()->count('date'))->toBeGreaterThan(1);
});

it('records 20 buys valued at the real close with a fixed 1 € fee', function () {
    fakeYahoo(new PythonResult('ok', etfPriceRows()));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);

    $buys = Transaction::query()->where('type', TransactionType::Buy)->get();
    $closes = collect(etfPriceRows())->pluck('close')->map(fn ($c) => (float) $c);

    expect($buys)->toHaveCount(20)
        ->and($buys->every(fn (Transaction $t) => (float) $t->fees === 1.0))->toBeTrue()
        ->and($buys->every(fn (Transaction $t) => $closes->contains((float) $t->unit_price)))->toBeTrue()
        ->and($buys->every(fn (Transaction $t) => (float) $t->quantity === round(500.0 / (float) $t->unit_price, 4)))->toBeTrue();
});

it('is idempotent', function () {
    fakeYahoo(new PythonResult('ok', etfPriceRows()));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);
    $txCount = Transaction::query()->count();

    $this->seed(EtfHistorySeeder::class);

    expect(Transaction::query()->count())->toBe($txCount);
});

it('degrades gracefully when Yahoo returns no data', function () {
    fakeYahoo(new PythonResult('error', error: 'boom'));
    User::factory()->create();

    $this->seed(EtfHistorySeeder::class);

    expect(Instrument::query()->where('type', InstrumentType::ETF)->count())->toBe(4)
        ->and(Transaction::query()->count())->toBe(0);
});
