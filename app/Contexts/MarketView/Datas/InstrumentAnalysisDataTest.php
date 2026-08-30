<?php

use App\Contexts\MarketView\Datas\InstrumentAnalysisData;

it('sérialise ses onze chiffres dans un ordre stable', function () {
    $data = new InstrumentAnalysisData(
        pru: 80.0,
        pruGapPct: 25.0,
        ma200: 82.4,
        ma200GapPct: -11.2,
        rsi14: 38.0,
        high52w: 94.1,
        high52wGapPct: -22.2,
        atr: 1.9,
        atrPct: 1.9,
        maxDrawdown: 31.4,
        portfolioWeightPct: 4.2,
    );

    expect(array_keys($data->jsonSerialize()))->toBe([
        'pru',
        'pruGapPct',
        'ma200',
        'ma200GapPct',
        'rsi14',
        'high52w',
        'high52wGapPct',
        'atr',
        'atrPct',
        'maxDrawdown',
        'portfolioWeightPct',
    ]);
});
