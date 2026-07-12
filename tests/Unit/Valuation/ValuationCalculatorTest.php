<?php

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

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
