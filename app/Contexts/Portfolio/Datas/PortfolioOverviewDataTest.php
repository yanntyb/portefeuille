<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;

it('serializes an overview to the expected json shape', function () {
    $overview = new PortfolioOverviewData(
        totalValue: 1000.0,
        totalCost: 800.0,
        totalGain: 200.0,
        totalGainPct: 25.0,
        holdings: [new HoldingLineData(
            assetId: 7,
            assetName: 'ACME',
            ticker: 'ACM',
            type: InstrumentType::Stock,
            quantity: 10.0,
            avgCost: 80.0,
            lastPrice: 100.0,
            marketValue: 1000.0,
            gain: 200.0,
            gainPct: 25.0,
        )],
        allocation: [new AllocationSliceData(label: 'Action', value: 1000.0, pct: 100.0, color: '#4f46e5')],
    );

    $json = $overview->jsonSerialize();

    expect($json['totalValue'])->toBe(1000.0)
        ->and($json['holdings'][0]['assetId'])->toBe(7)
        ->and($json['holdings'][0]['assetName'])->toBe('ACME')
        ->and($json['holdings'][0]['type'])->toBe('stock')
        ->and($json['holdings'][0]['typeLabel'])->toBe('Action')
        ->and($json['allocation'][0]['color'])->toBe('#4f46e5');
});

it('builds an empty overview', function () {
    $overview = PortfolioOverviewData::empty();

    expect($overview->totalValue)->toBe(0.0)
        ->and($overview->holdings)->toBe([])
        ->and($overview->allocation)->toBe([]);
});
