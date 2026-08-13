<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use Illuminate\Support\Facades\DB;

/**
 * Run the migration that normalises `asset_prices.date`. Migrations live outside the colocated
 * suites, so this one is exercised by requiring its file and calling up() directly — the suite
 * has already applied it to an empty database, which only proves it is harmless there.
 */
function normaliseAssetPricesDates(): void
{
    $migration = require database_path('migrations/2026_08_13_134920_normalise_asset_prices_date_format.php');

    $migration->up();
}

/**
 * Read the date column without Eloquent's casting, which would hide the stored format.
 *
 * @return array<int, string>
 */
function storedPriceDates(): array
{
    return DB::table('asset_prices')->orderBy('id')->pluck('date')->all();
}

beforeEach(function () {
    $this->instrument = Instrument::factory()->create();
});

it('pads a bare date to the format Eloquent writes', function () {
    Price::query()->insert([
        'asset_id' => $this->instrument->id,
        'date' => '2026-01-03',
        'close' => 10.0,
    ]);

    normaliseAssetPricesDates();

    expect(storedPriceDates())->toBe(['2026-01-03 00:00:00']);
});

it('collapses two rows of the same day, keeping the most recently created', function () {
    Price::query()->insert([
        [
            'asset_id' => $this->instrument->id,
            'date' => '2026-01-03 00:00:00',
            'close' => 10.0,
            'created_at' => '2026-01-03 12:00:00',
        ],
        [
            'asset_id' => $this->instrument->id,
            'date' => '2026-01-03',
            'close' => 42.5,
            'created_at' => '2026-01-03 12:36:17',
        ],
    ]);

    normaliseAssetPricesDates();

    expect(storedPriceDates())->toBe(['2026-01-03 00:00:00'])
        ->and((float) Price::query()->sole()->close)->toBe(42.5);
});

it('keeps the days of an asset separate and does not mix assets', function () {
    $other = Instrument::factory()->create();

    Price::query()->insert([
        ['asset_id' => $this->instrument->id, 'date' => '2026-01-03', 'close' => 10.0],
        ['asset_id' => $this->instrument->id, 'date' => '2026-01-04', 'close' => 11.0],
        ['asset_id' => $other->id, 'date' => '2026-01-03', 'close' => 12.0],
    ]);

    normaliseAssetPricesDates();

    expect(storedPriceDates())->toBe([
        '2026-01-03 00:00:00',
        '2026-01-04 00:00:00',
        '2026-01-03 00:00:00',
    ]);
});

it('is idempotent on an already normalised database', function () {
    Price::factory()->create(['asset_id' => $this->instrument->id, 'date' => '2026-01-03']);

    normaliseAssetPricesDates();
    normaliseAssetPricesDates();

    expect(storedPriceDates())->toBe(['2026-01-03 00:00:00']);
});

it('has nothing to do on an empty table', function () {
    normaliseAssetPricesDates();

    expect(Price::query()->count())->toBe(0);
});
