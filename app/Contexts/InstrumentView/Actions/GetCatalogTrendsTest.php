<?php

use App\Contexts\InstrumentView\Actions\GetCatalogTrends;
use App\Contexts\InstrumentView\Datas\CatalogTrendData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Valuation\Enums\ValuationRange;
use Illuminate\Support\Carbon;

/** @param list<CatalogTrendData> $trends */
function trendFor(array $trends, int $assetId): CatalogTrendData
{
    foreach ($trends as $trend) {
        if ($trend->assetId === $assetId) {
            return $trend;
        }
    }

    throw new RuntimeException("No trend for asset {$assetId}.");
}

it('computes the change percentage between the first and the last price of the window', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 120]);

    $trends = app(GetCatalogTrends::class)(ValuationRange::OneMonth);

    expect(trendFor($trends, $asset->id)->changePct)->toBe(20.0);
});

it('ignores the prices older than the range window', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subMonths(6), 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 120]);

    $trends = app(GetCatalogTrends::class)(ValuationRange::OneMonth);

    expect(trendFor($trends, $asset->id)->changePct)->toBe(20.0);
});

it('keeps the whole history when the range is max', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subYears(4), 'close' => 50]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 100]);

    $trends = app(GetCatalogTrends::class)(ValuationRange::Max);

    expect(trendFor($trends, $asset->id)->changePct)->toBe(100.0);
});

it('leaves the change percentage null when the window holds less than two prices', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 100]);

    $trends = app(GetCatalogTrends::class)(ValuationRange::OneMonth);

    expect(trendFor($trends, $asset->id)->changePct)->toBeNull()
        ->and(trendFor($trends, $asset->id)->points)->toBe([100.0]);
});

it('returns a trend for every instrument, even without any price', function () {
    $withPrices = Instrument::factory()->create();
    $withoutPrice = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $withPrices->id, 'date' => Carbon::now(), 'close' => 100]);

    $trends = app(GetCatalogTrends::class)(ValuationRange::Max);

    expect($trends)->toHaveCount(2)
        ->and(trendFor($trends, $withoutPrice->id)->changePct)->toBeNull()
        ->and(trendFor($trends, $withoutPrice->id)->points)->toBe([]);
});

it('downsamples a long history while keeping the first and the last price', function () {
    $asset = Instrument::factory()->create();
    $day = Carbon::now()->subDays(200);
    $close = 100.0;

    while ($day->lte(Carbon::now())) {
        Price::factory()->create(['asset_id' => $asset->id, 'date' => $day->format('Y-m-d'), 'close' => $close]);
        $day = $day->copy()->addDay();
        $close += 1;
    }

    $trend = trendFor(app(GetCatalogTrends::class)(ValuationRange::Max), $asset->id);

    expect(count($trend->points))->toBeLessThanOrEqual(24)
        ->and(count($trend->points))->toBeGreaterThan(1)
        ->and($trend->points[0])->toBe(100.0)
        ->and($trend->points[count($trend->points) - 1])->toBe(300.0);
});
