<?php

use App\Contexts\Market\Datas\PriceRequestData;

it('holds a ticker and an inclusive date window', function () {
    $request = new PriceRequestData(
        ticker: 'PE500.PA',
        startDate: '2026-08-11',
        endDate: '2026-08-13',
    );

    expect($request->ticker)->toBe('PE500.PA')
        ->and($request->startDate)->toBe('2026-08-11')
        ->and($request->endDate)->toBe('2026-08-13');
});
