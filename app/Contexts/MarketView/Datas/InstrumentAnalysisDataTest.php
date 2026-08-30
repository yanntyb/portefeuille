<?php

use App\Contexts\MarketView\Datas\InstrumentAnalysisData;

it('sérialise ses onze chiffres dans un ordre stable', function () {
    $data = new InstrumentAnalysisData(
        pru: 80.0,
        pruGapPct: 25.0,
        high52w: 94.1,
        high52wGapPct: -22.2,
        maxDrawdown: 31.4,
        portfolioWeightPct: 4.2,
    );

    expect(array_keys($data->jsonSerialize()))->toBe([
        'pru',
        'pruGapPct',
        'high52w',
        'high52wGapPct',
        'maxDrawdown',
        'portfolioWeightPct',
    ]);
});
