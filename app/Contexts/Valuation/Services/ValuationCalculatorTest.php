<?php

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

function tx(string $date, int $assetId, bool $isSell, float $qty, float $price, float $fees = 0.0): TransactionRecordData
{
    return new TransactionRecordData(Carbon::parse($date), $assetId, $isSell, $qty, $price, $fees);
}

it('returns an empty series without transactions', function () {
    expect((new ValuationCalculator)->calculate([], []))->toEqual(
        \App\Contexts\Valuation\Datas\ValuationSeriesData::empty()
    );
});

it('values a single buy across two price dates', function () {
    $series = (new ValuationCalculator)->calculate(
        [tx('2026-01-01', 1, false, 10, 100)],
        [new PriceRecordData(1, '2026-01-01', 100), new PriceRecordData(1, '2026-02-01', 120)],
    );

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01'])
        ->and($series->valuations)->toBe([1000.0, 1200.0])
        ->and($series->invested)->toBe([1000.0, 1000.0]);
});

it('reduces value and invested after a sell', function () {
    $series = (new ValuationCalculator)->calculate(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-02-01', 1, true, 4, 150)],
        [new PriceRecordData(1, '2026-01-01', 100), new PriceRecordData(1, '2026-02-01', 150)],
    );

    // day 1: qty 10 @100 = 1000, invested 1000
    // day 2: qty 6 @150 = 900, invested 1000 - (4 * PRU100) = 600
    expect($series->valuations)->toBe([1000.0, 900.0])
        ->and($series->invested)->toBe([1000.0, 600.0]);
});

it('ignores an asset that has no price', function () {
    $series = (new ValuationCalculator)->calculate(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-01-01', 2, false, 5, 50)],
        [new PriceRecordData(1, '2026-01-01', 100)], // only asset 1 priced
    );

    // asset 2 contributes 0 to value (no close), invested still counts both buys
    expect($series->valuations)->toBe([1000.0])
        ->and($series->invested)->toBe([1250.0]);
});

it('keeps every index when the count is within the cap', function () {
    expect(ValuationCalculator::downsampleIndices(5, 200))->toBe([0, 1, 2, 3, 4])
        ->and(ValuationCalculator::downsampleIndices(0, 200))->toBe([]);
});

it('caps a large index set while keeping the first and last', function () {
    $indices = ValuationCalculator::downsampleIndices(1200, 200);

    expect(count($indices))->toBeLessThanOrEqual(200)
        ->and($indices[0])->toBe(0)
        ->and($indices[count($indices) - 1])->toBe(1199)
        // strictement croissant
        ->and(collect($indices)->sliding(2)->every(fn ($pair) => $pair->last() > $pair->first()))->toBeTrue();
});

it('downsamples a long daily series to the point cap', function () {
    $prices = [];
    $day = Carbon::parse('2020-01-01');
    for ($i = 0; $i < 400; $i++) {
        $prices[] = new PriceRecordData(1, $day->copy()->addDays($i)->format('Y-m-d'), 100.0 + $i);
    }

    $series = (new ValuationCalculator)->calculate(
        [tx('2020-01-01', 1, false, 1, 100)],
        $prices,
        200,
    );

    $labels = $series->labels;

    expect(count($labels))->toBeLessThanOrEqual(200)
        ->and(count($labels))->toBeGreaterThan(1)
        ->and($labels[0])->toBe('2020-01-01')
        ->and($labels[count($labels) - 1])->toBe('2021-02-03') // 2020-01-01 + 399 jours
        ->and(count($series->valuations))->toBe(count($labels))
        ->and(count($series->invested))->toBe(count($labels));
});

it('orders same-day buys before sells regardless of input order', function () {
    $prices = [new PriceRecordData(1, '2026-01-01', 150)];
    $buy = tx('2026-01-01', 1, false, 10, 100);
    $sell = tx('2026-01-01', 1, true, 4, 150);

    $sellFirst = (new ValuationCalculator)->calculate([$sell, $buy], $prices);
    $buyFirst = (new ValuationCalculator)->calculate([$buy, $sell], $prices);

    // both orderings must agree: qty 6 @150 = 900 ; invested 1000 - (4 × PRU 100) = 600
    expect($sellFirst->valuations)->toBe([900.0])
        ->and($sellFirst->invested)->toBe([600.0])
        ->and($buyFirst->valuations)->toBe([900.0])
        ->and($buyFirst->invested)->toBe([600.0]);
});

it('accumulates invested per asset on a shared date axis', function () {
    $series = (new ValuationCalculator)->investedByAsset([
        tx('2026-01-01', 1, false, 10, 100),          // asset 1 invests 1000
        tx('2026-02-01', 2, false, 5, 50),            // asset 2 invests 250
        tx('2026-03-01', 1, false, 2, 150),           // asset 1 invests +300 => 1300
    ]);

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01', '2026-03-01']);

    $byId = collect($series->series)->keyBy('assetId');
    // asset 1: 1000 at d1, forward-fill 1000 at d2, 1300 at d3
    expect($byId[1]->invested)->toBe([1000.0, 1000.0, 1300.0]);
    expect($byId[1]->name)->toBe('#1');
    // asset 2: 0 before its first tx, 250 from d2 onward
    expect($byId[2]->invested)->toBe([0.0, 250.0, 250.0]);
});

it('reduces invested by cost basis on a sell (per asset)', function () {
    $series = (new ValuationCalculator)->investedByAsset([
        tx('2026-01-01', 1, false, 10, 100),          // invested 1000, PRU 100
        tx('2026-02-01', 1, true, 4, 150),            // invested -= 4*100 => 600
    ]);

    $byId = collect($series->series)->keyBy('assetId');
    expect($byId[1]->invested)->toBe([1000.0, 600.0]);
});

it('returns an empty invested-by-asset series without transactions', function () {
    expect((new ValuationCalculator)->investedByAsset([]))->toEqual(
        \App\Contexts\Valuation\Datas\InvestedByAssetSeriesData::empty()
    );
});

it('exposes the unit price aligned with the valuation labels', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [
        new PriceRecordData(assetId: 1, date: '2026-01-01', close: 100.0),
        new PriceRecordData(assetId: 1, date: '2026-01-02', close: 110.0),
        new PriceRecordData(assetId: 1, date: '2026-01-03', close: 90.0),
    ];

    $series = (new ValuationCalculator)->calculate($transactions, $prices);

    expect($series->prices)->toBe([100.0, 110.0, 90.0])
        ->and($series->prices)->toHaveCount(count($series->labels))
        ->and($series->valuations)->toBe([1000.0, 1100.0, 900.0]);
});

it('downsamples prices with the same indices as the other series', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [
        new PriceRecordData(assetId: 1, date: '2026-01-01', close: 100.0),
        new PriceRecordData(assetId: 1, date: '2026-01-02', close: 110.0),
        new PriceRecordData(assetId: 1, date: '2026-01-03', close: 90.0),
    ];

    $series = (new ValuationCalculator)->calculate($transactions, $prices, maxPoints: 2);

    expect($series->labels)->toBe(['2026-01-01', '2026-01-03'])
        ->and($series->prices)->toBe([100.0, 90.0]);
});

it('returns an empty prices array for an empty series', function () {
    $series = (new ValuationCalculator)->calculate([], []);

    expect($series->prices)->toBe([]);
});

it('calculateDaily returns one point per price day without downsampling', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [];
    for ($d = 1; $d <= 250; $d++) {
        $prices[] = new PriceRecordData(assetId: 1, date: Carbon::parse('2026-01-01')->addDays($d - 1)->format('Y-m-d'), close: 100.0 + $d);
    }

    $daily = (new ValuationCalculator)->calculateDaily($transactions, $prices);
    $capped = (new ValuationCalculator)->calculate($transactions, $prices, maxPoints: 200);

    expect($daily->labels)->toHaveCount(250)
        ->and($daily->prices)->toHaveCount(250)
        ->and(count($capped->labels))->toBeLessThanOrEqual(200)
        ->and(count($capped->labels))->toBeLessThan(250);
});

it('windows the series to the requested range', function () {
    $labels = [];
    $series = [];
    for ($d = 0; $d < 400; $d++) {
        $labels[] = Carbon::parse('2025-01-01')->addDays($d)->format('Y-m-d');
    }
    $values = array_map(fn (int $i): float => (float) ($i + 1), array_keys($labels));
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData($labels, $values, $values, $values);

    $windowed = (new ValuationCalculator)->windowAndAggregate($daily, ValuationRange::OneMonth, ValuationGranularity::Day);

    $lastDate = Carbon::parse($labels[399]);
    $cutoff = $lastDate->copy()->subMonthsNoOverflow(1)->format('Y-m-d');
    expect($windowed->labels[0])->toBeGreaterThanOrEqual($cutoff)
        ->and($windowed->labels[count($windowed->labels) - 1])->toBe($labels[399])
        ->and(count($windowed->labels))->toBeLessThan(400);
});

it('aggregates by keeping the last point of each month bucket', function () {
    $labels = ['2026-01-10', '2026-01-20', '2026-01-31', '2026-02-05', '2026-02-28'];
    $values = [1.0, 2.0, 3.0, 4.0, 5.0];
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData($labels, $values, $values, $values);

    $monthly = (new ValuationCalculator)->windowAndAggregate($daily, ValuationRange::Max, ValuationGranularity::Month);

    expect($monthly->labels)->toBe(['2026-01-31', '2026-02-28'])
        ->and($monthly->valuations)->toBe([3.0, 5.0])
        ->and($monthly->prices)->toBe([3.0, 5.0]);
});

it('keeps every point when granularity is Day', function () {
    $labels = ['2026-01-10', '2026-01-20', '2026-01-31'];
    $values = [1.0, 2.0, 3.0];
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData($labels, $values, $values, $values);

    $result = (new ValuationCalculator)->windowAndAggregate($daily, ValuationRange::Max, ValuationGranularity::Day);

    expect($result->labels)->toBe($labels);
});

it('computes the window return excluding contributions', function () {
    // Début 1000, apport de 200 pendant la fenêtre, fin 1400 => (1400 - 1000 - 200) / 1000 = +20%.
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 1250.0, 1400.0],
        [1000.0, 1200.0, 1200.0],
        [100.0, 110.0, 120.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01'))->toBe(20.0);
});

it('anchors the window start on the last day at or before the boundary', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-01-01', '2026-01-15', '2026-03-01'],
        [1000.0, 2000.0, 3000.0],
        [1000.0, 1000.0, 1000.0],
        [10.0, 20.0, 30.0],
    );

    // Boundary 2026-02-01 => début pris au 2026-01-15 (valeur 2000) : (3000 - 2000) / 2000 = +50%.
    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-02-01'))->toBe(50.0);
});

it('returns null when the series does not reach the boundary', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-02-01', '2026-03-01'],
        [1000.0, 1200.0],
        [1000.0, 1000.0],
        [100.0, 120.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01'))->toBeNull();
});

it('returns null when the starting value is zero', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-01-01', '2026-02-01'],
        [0.0, 500.0],
        [0.0, 0.0],
        [0.0, 50.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01'))->toBeNull();
});

it('builds trailing performances: YTD, monthly, then one card per full year', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2023-01-01', '2023-07-01', '2024-07-01', '2025-07-01', '2026-01-01', '2026-04-01', '2026-06-01', '2026-07-01'],
        [1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1200.0],
        [1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0],
        [100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 120.0],
    );

    $performances = (new ValuationCalculator)->trailingPerformances($daily);

    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['YTD', '1M', '3M', '6M', '1Y', '2Y', '3Y'])
        ->and(array_map(fn ($perf) => $perf->label, $performances))->toBe(['YTD', '1 mois', '3 mois', '6 mois', '1 an', '2 ans', '3 ans'])
        ->and($performances[0]->pct)->toBe(20.0)
        ->and($performances[6]->pct)->toBe(20.0);
});

it('returns no trailing performances for an empty series', function () {
    expect((new ValuationCalculator)->trailingPerformances(App\Contexts\Valuation\Datas\ValuationSeriesData::empty()))->toBe([]);
});

it('windows and aggregates an invested-by-asset series', function () {
    $series = new App\Contexts\Valuation\Datas\InvestedByAssetSeriesData(
        ['2026-01-10', '2026-01-20', '2026-02-15', '2026-03-01'],
        [new App\Contexts\Valuation\Datas\AssetInvestedSeriesData(1, 'A', [100.0, 200.0, 300.0, 400.0])],
    );

    $result = (new ValuationCalculator)->windowAndAggregateInvested($series, ValuationRange::Max, ValuationGranularity::Month);

    // Buckets mensuels : 2026-01 -> dernier (2026-01-20), 2026-02, 2026-03.
    expect($result->labels)->toBe(['2026-01-20', '2026-02-15', '2026-03-01'])
        ->and($result->series[0]->invested)->toBe([200.0, 300.0, 400.0]);
});

it('windows an invested-by-asset series by range', function () {
    $series = new App\Contexts\Valuation\Datas\InvestedByAssetSeriesData(
        ['2026-01-10', '2026-02-15', '2026-03-01'],
        [new App\Contexts\Valuation\Datas\AssetInvestedSeriesData(1, 'A', [100.0, 200.0, 300.0])],
    );

    // Dernier label 2026-03-01, range 1M => cutoff 2026-02-01 : seuls 2026-02-15 et 2026-03-01 restent.
    $result = (new ValuationCalculator)->windowAndAggregateInvested($series, ValuationRange::OneMonth, ValuationGranularity::Day);

    expect($result->labels)->toBe(['2026-02-15', '2026-03-01'])
        ->and($result->series[0]->invested)->toBe([200.0, 300.0]);
});
