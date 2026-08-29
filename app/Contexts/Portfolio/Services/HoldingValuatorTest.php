<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Services\HoldingValuator;

function valuedLine(?float $marketValue, ?float $gain, float $quantity = 1.0, ?float $avgCost = null): HoldingLineData
{
    return new HoldingLineData(
        assetId: 1,
        assetName: 'ACME',
        ticker: 'ACM',
        type: InstrumentType::Stock,
        assetClass: AssetClass::Equity,
        quantity: $quantity,
        avgCost: $avgCost,
        lastPrice: null,
        marketValue: $marketValue,
        gain: $gain,
        gainPct: null,
    );
}

it('valorise une ligne complète', function () {
    expect((new HoldingValuator)->value(10, 80, 100))->toBe([
        'marketValue' => 1000.0,
        'cost' => 800.0,
        'gain' => 200.0,
        'gainPct' => 25.0,
    ]);
});

it('laisse tout à null sans dernier cours', function () {
    expect((new HoldingValuator)->value(10, 80, null))->toBe([
        'marketValue' => null,
        'cost' => null,
        'gain' => null,
        'gainPct' => null,
    ]);
});

it('valorise sans prix de revient mais ne calcule aucun gain', function () {
    expect((new HoldingValuator)->value(10, null, 100))->toBe([
        'marketValue' => 1000.0,
        'cost' => null,
        'gain' => null,
        'gainPct' => null,
    ]);
});

it('rend un pourcentage nul plutôt que zéro sur un coût nul', function () {
    expect((new HoldingValuator)->pct(200.0, 0.0))->toBeNull();
});

it('totalise les lignes en ignorant celles sans valeur', function () {
    $totals = (new HoldingValuator)->totals([
        valuedLine(marketValue: 1000.0, gain: 200.0, quantity: 10, avgCost: 80),
        valuedLine(marketValue: null, gain: null),
    ]);

    expect($totals)->toBe([
        'totalValue' => 1000.0,
        'totalCost' => 800.0,
        'totalGain' => 200.0,
        'totalGainPct' => 25.0,
    ]);
});

it('rend un pourcentage total nul quand aucune ligne n\'a de coût connu', function () {
    $totals = (new HoldingValuator)->totals([valuedLine(marketValue: 1000.0, gain: null)]);

    expect($totals['totalGainPct'])->toBeNull()
        ->and($totals['totalValue'])->toBe(1000.0);
});
