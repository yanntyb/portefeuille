<?php

use App\Contexts\Portfolio\Datas\PositionLineData;

it('se sérialise avec son identifiant d\'actif en tête', function () {
    $position = new PositionLineData(
        assetId: 7,
        quantity: 10.0,
        avgCost: 80.0,
        marketValue: 1000.0,
        gain: 200.0,
        gainPct: 25.0,
        realizedGain: 0.0,
    );

    expect($position->jsonSerialize())->toBe([
        'assetId' => 7,
        'quantity' => 10.0,
        'avgCost' => 80.0,
        'marketValue' => 1000.0,
        'gain' => 200.0,
        'gainPct' => 25.0,
        'realizedGain' => 0.0,
    ]);
});
