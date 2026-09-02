<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\PortfolioView\Datas\AnalysisData;
use App\Contexts\PortfolioView\Datas\ClassTransactionLineData;
use App\Contexts\PortfolioView\Datas\ConcentrationData;
use App\Contexts\PortfolioView\Datas\ContributionLineData;
use App\Contexts\PortfolioView\Datas\DrawdownData;
use App\Contexts\PortfolioView\Datas\InstrumentDetailData;
use App\Contexts\PortfolioView\Datas\PositionData;
use App\Contexts\PortfolioView\Datas\PriceHistoryData;
use App\Contexts\PortfolioView\Datas\SectorWeightData;
use App\Contexts\PortfolioView\Datas\TransactionLineData;
use App\Contexts\Portfolio\Datas\ContributionData;
use App\Contexts\Portfolio\Datas\PortfolioAnalysisData;
use App\Contexts\Wealth\Datas\WealthTransactionLineData;

it('serializes an instrument detail with nested position', function () {
    $detail = new InstrumentDetailData(
        id: 7, name: 'ACME', ticker: 'ACM', isin: 'US0000000001', type: InstrumentType::Stock,
        assetClass: AssetClass::Equity,
        lastPrice: 100.0, lastPriceDate: '2026-07-01',
        position: new PositionData(10.0, 80.0, 1000.0, 200.0, 25.0, 40.0),
        transactions: [new TransactionLineData(1, 3, '2026-01-01', false, 'Achat', 'buy', 10.0, 80.0, 0.0, 800.0, false)],
        sectors: [new SectorWeightData('Technologie', 0.5)],
    );

    $json = $detail->jsonSerialize();

    expect($json['typeLabel'])->toBe('Action');
    expect($json['assetClass'])->toBe('equity');
    expect($json['assetClassLabel'])->toBe('Actions');
    expect($json['assetClassHref'])->toBe('/actions');
    expect($json['position'])->toBeInstanceOf(PositionData::class);
    expect($json['transactions'])->toHaveCount(1);
    expect($json['sectors'][0])->toBeInstanceOf(SectorWeightData::class);
});

it('serializes price history as parallel arrays', function () {
    $history = new PriceHistoryData(labels: ['2026-01-01'], close: [100.0]);

    expect($history->jsonSerialize())->toBe(['labels' => ['2026-01-01'], 'close' => [100.0]]);
});

it('reproduit le JSON des analyses de Portfolio à la clé près', function () {
    $twin = new ConcentrationData(25.0, 75.0, 100.0, 0.25);
    $origin = new App\Contexts\Portfolio\Datas\ConcentrationData(25.0, 75.0, 100.0, 0.25);

    expect(json_encode($twin))->toBe(json_encode($origin));
});

it('reproduit le JSON du drawdown de Valuation à la clé près', function () {
    $twin = new DrawdownData(25.0, '2026-02-01', '2026-03-01', 10.0);
    $origin = new App\Contexts\Valuation\Datas\DrawdownData(25.0, '2026-02-01', '2026-03-01', 10.0);

    expect(json_encode($twin))->toBe(json_encode($origin));
});

it('reproduit le JSON d\'une contribution à la clé près', function () {
    $twin = new ContributionLineData(1, 'ACME', 3.6, 30.0);
    $origin = new ContributionData(1, 'ACME', 3.6, 30.0);

    expect(json_encode($twin))->toBe(json_encode($origin));
});

it('reproduit le JSON composite des analyses de Portfolio à la clé et à l\'ordre près', function () {
    $twin = new AnalysisData(
        concentration: new ConcentrationData(25.0, 75.0, 100.0, 0.25),
        contributions: [new ContributionLineData(1, 'ACME', 3.6, 30.0)],
    );
    $origin = new PortfolioAnalysisData(
        concentration: new App\Contexts\Portfolio\Datas\ConcentrationData(25.0, 75.0, 100.0, 0.25),
        contributions: [new ContributionData(1, 'ACME', 3.6, 30.0)],
    );

    expect(json_encode($twin))->toBe(json_encode($origin));
});

it('reproduit le JSON d\'une opération du patrimoine à la clé et à l\'ordre près', function () {
    $twin = new ClassTransactionLineData(1, 3, '2026-01-01', 7, 'ACME', false, 'Achat', 'buy', 10.0, 80.0, 1.0, 801.0, false);
    $origin = new WealthTransactionLineData(1, 3, '2026-01-01', 7, 'ACME', false, 'Achat', 'buy', 10.0, 80.0, 1.0, 801.0, false);

    expect(json_encode($twin))->toBe(json_encode($origin));
});
