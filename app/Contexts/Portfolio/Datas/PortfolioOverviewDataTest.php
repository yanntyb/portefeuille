<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
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
            assetClass: AssetClass::Equity,
            quantity: 10.0,
            avgCost: 80.0,
            lastPrice: 100.0,
            marketValue: 1000.0,
            gain: 200.0,
            gainPct: 25.0,
        )],
    );

    $json = $overview->jsonSerialize();

    expect($json['totalValue'])->toBe(1000.0)
        ->and($json['holdings'][0]['assetId'])->toBe(7)
        ->and($json['holdings'][0]['assetName'])->toBe('ACME')
        ->and($json['holdings'][0]['type'])->toBe('stock')
        ->and($json['holdings'][0]['typeLabel'])->toBe('Action')
        ->and($json)->not->toHaveKey('allocation');
});

it('builds an empty overview', function () {
    $overview = PortfolioOverviewData::empty();

    expect($overview->totalValue)->toBe(0.0)
        ->and($overview->holdings)->toBe([])
        ->and($overview->holdings)->toBe([]);
});
