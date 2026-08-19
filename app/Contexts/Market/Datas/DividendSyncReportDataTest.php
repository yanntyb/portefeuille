<?php

use App\Contexts\Market\Datas\DividendSyncReportData;

it('compte les tickers synchronisés et en échec', function () {
    $report = new DividendSyncReportData(synced: ['CW8.PA' => 2], failed: ['DEAD.PA']);

    expect($report->total())->toBe(2)
        ->and($report->syncedCount())->toBe(1)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('ne signale un échec total que si rien n\'a été synchronisé', function () {
    expect((new DividendSyncReportData(failed: ['CW8.PA']))->isTotalFailure())->toBeTrue()
        ->and((new DividendSyncReportData)->isTotalFailure())->toBeFalse();
});
