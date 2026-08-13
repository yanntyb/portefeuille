<?php

use App\Contexts\Market\Datas\PriceSyncReportData;

it('is empty by default', function () {
    $report = new PriceSyncReportData;

    expect($report->total())->toBe(0)
        ->and($report->syncedCount())->toBe(0)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('counts synced and failed tickers', function () {
    $report = new PriceSyncReportData(
        synced: ['AAPL' => 253, 'PE500.PA' => 2],
        failed: ['DEAD.PA'],
    );

    expect($report->total())->toBe(3)
        ->and($report->syncedCount())->toBe(2)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('counts a ticker with zero new prices as synced', function () {
    $report = new PriceSyncReportData(synced: ['DEAD.PA' => 0]);

    expect($report->syncedCount())->toBe(1)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('is a total failure when nothing was synced', function () {
    $report = new PriceSyncReportData(failed: ['AAPL', 'PE500.PA']);

    expect($report->total())->toBe(2)
        ->and($report->isTotalFailure())->toBeTrue();
});
