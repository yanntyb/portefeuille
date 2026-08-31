<?php

use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Datas\MarketSyncReportData;
use App\Contexts\Market\Datas\PriceSyncReportData;

it('sums up the three sources in one line', function () {
    $report = new MarketSyncReportData(
        prices: new PriceSyncReportData(synced: ['AAPL' => 12, 'MC.PA' => 8]),
        sectors: ['AAPL' => 11],
        dividends: new DividendSyncReportData(synced: ['MC.PA' => 1]),
    );

    expect($report->summary())->toBe('2 cours, 1 secteur, 1 dividende')
        ->and($report->hasFailure())->toBeFalse();
});

it('reports a failure when a source came back empty-handed', function () {
    $report = new MarketSyncReportData(
        prices: new PriceSyncReportData(failed: ['AAPL'], error: 'boom'),
        sectors: [],
        dividends: new DividendSyncReportData(synced: ['MC.PA' => 1]),
    );

    expect($report->hasFailure())->toBeTrue()
        ->and($report->summary())->toBe('0 cours, 0 secteur, 1 dividende');
});

it('reports no failure when nothing was eligible at all', function () {
    $report = new MarketSyncReportData(
        prices: new PriceSyncReportData,
        sectors: [],
        dividends: new DividendSyncReportData,
    );

    expect($report->hasFailure())->toBeFalse();
});
