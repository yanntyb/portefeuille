<?php

use App\Contexts\Valuation\Datas\ValuationSeriesData;

it('serializes to the expected json shape', function () {
    $series = new ValuationSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        valuations: [1000.0, 1200.0],
        invested: [1000.0, 1000.0],
        prices: [100.0, 120.0],
    );

    expect($series->jsonSerialize())->toBe([
        'labels' => ['2026-01-01', '2026-02-01'],
        'valuations' => [1000.0, 1200.0],
        'invested' => [1000.0, 1000.0],
        'prices' => [100.0, 120.0],
    ]);
});

it('builds an empty series', function () {
    $series = ValuationSeriesData::empty();

    expect($series->labels)->toBe([])
        ->and($series->valuations)->toBe([])
        ->and($series->invested)->toBe([]);
});
