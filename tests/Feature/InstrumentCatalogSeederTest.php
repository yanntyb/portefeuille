<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Infrastructure\Python\YahooScript;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Shared\Python\FakePythonRunner;
use App\Shared\Python\PythonResult;
use App\Shared\Python\PythonRunner;
use Database\Seeders\InstrumentCatalogSeeder;

/**
 * 24 points mensuels autour de 100, assez pour qu'une variation et une tendance
 * soient calculables sur toutes les fenêtres du radar.
 *
 * @return list<array{date: string, open: float, high: float, low: float, close: float, volume: int}>
 */
function catalogMonthlyRows(): array
{
    $rows = [];
    for ($i = 0; $i < 24; $i++) {
        $close = round(100 + 12 * sin($i * M_PI / 6), 4);
        $rows[] = [
            'date' => sprintf('%04d-%02d-01', 2024 + intdiv($i, 12), ($i % 12) + 1),
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
 * Le seeder délègue aux commandes market:sync-prices et market:sync-sectors : les scripts
 * Python à simuler sont ceux du fetch groupé et des secteurs.
 */
function fakeCatalogYahoo(PythonResult $prices): void
{
    $tickers = array_column(InstrumentCatalogSeeder::INSTRUMENTS, 'ticker');

    $bulk = $prices->ok()
        ? new PythonResult('ok', array_fill_keys($tickers, $prices->data))
        : $prices;

    $fake = (new FakePythonRunner)
        ->withResult(YahooScript::PricesBulk->path(), $bulk)
        ->withResult(YahooScript::Sectors->path(), new PythonResult('ok', []));

    app()->instance(PythonRunner::class, $fake);
}

it('creates thirty instruments with their price history', function () {
    fakeCatalogYahoo(new PythonResult('ok', catalogMonthlyRows()));

    $this->seed(InstrumentCatalogSeeder::class);

    $instruments = Instrument::query()->get();
    $priced = Price::query()->distinct()->pluck('asset_id');

    expect($instruments)->toHaveCount(30)
        ->and($instruments->pluck('ticker')->unique())->toHaveCount(30)
        ->and($priced)->toHaveCount(30)
        ->and(Price::query()->distinct()->count('date'))->toBeGreaterThan(1);
});

it('attaches no transaction to the catalogued instruments', function () {
    fakeCatalogYahoo(new PythonResult('ok', catalogMonthlyRows()));

    $this->seed(InstrumentCatalogSeeder::class);

    expect(Transaction::query()->count())->toBe(0)
        ->and(Wallet::query()->count())->toBe(0);
});

it('only catalogs types served by the market feed', function () {
    fakeCatalogYahoo(new PythonResult('ok', catalogMonthlyRows()));

    $this->seed(InstrumentCatalogSeeder::class);

    $types = Instrument::query()->pluck('type');

    expect($types->unique()->values()->all())
        ->not->toContain(InstrumentType::Bond)
        ->and($types->unique())->toHaveCount(4);
});

it('is idempotent', function () {
    fakeCatalogYahoo(new PythonResult('ok', catalogMonthlyRows()));

    $this->seed(InstrumentCatalogSeeder::class);
    $priceCount = Price::query()->count();

    $this->seed(InstrumentCatalogSeeder::class);

    expect(Instrument::query()->count())->toBe(30)
        ->and(Price::query()->count())->toBe($priceCount);
});

it('degrades gracefully when Yahoo returns no data', function () {
    fakeCatalogYahoo(new PythonResult('error', error: 'boom'));

    $this->seed(InstrumentCatalogSeeder::class);

    expect(Instrument::query()->count())->toBe(30)
        ->and(Price::query()->count())->toBe(0);
});
