<?php

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
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
