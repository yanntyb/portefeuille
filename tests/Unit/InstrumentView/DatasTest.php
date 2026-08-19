<?php

use App\Contexts\InstrumentView\Datas\InstrumentDetailData;
use App\Contexts\InstrumentView\Datas\PositionData;
use App\Contexts\InstrumentView\Datas\PriceHistoryData;
use App\Contexts\InstrumentView\Datas\SectorWeightData;
use App\Contexts\InstrumentView\Datas\TransactionLineData;
use App\Contexts\Market\Enums\InstrumentType;

it('serializes an instrument detail with nested position', function () {
    $detail = new InstrumentDetailData(
        id: 7, name: 'ACME', ticker: 'ACM', isin: 'US0000000001', type: InstrumentType::Stock,
        lastPrice: 100.0, lastPriceDate: '2026-07-01',
        position: new PositionData(10.0, 80.0, 1000.0, 200.0, 25.0),
        transactions: [new TransactionLineData('2026-01-01', false, 'Achat', 10.0, 80.0, 0.0, 800.0)],
        sectors: [new SectorWeightData('Technologie', 0.5)],
    );

    $json = $detail->jsonSerialize();

    expect($json['typeLabel'])->toBe('Action');
    expect($json['position'])->toBeInstanceOf(PositionData::class);
    expect($json['transactions'])->toHaveCount(1);
    expect($json['sectors'][0])->toBeInstanceOf(SectorWeightData::class);
});

it('serializes price history as parallel arrays', function () {
    $history = new PriceHistoryData(labels: ['2026-01-01'], close: [100.0]);

    expect($history->jsonSerialize())->toBe(['labels' => ['2026-01-01'], 'close' => [100.0]]);
});
