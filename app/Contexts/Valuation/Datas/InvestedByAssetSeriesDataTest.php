<?php

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;

it('serializes an asset invested series', function () {
    $serie = new AssetInvestedSeriesData(assetId: 7, name: 'ACME', invested: [100.0, 250.0]);

    expect($serie->jsonSerialize())->toBe([
        'assetId' => 7,
        'name' => 'ACME',
        'invested' => [100.0, 250.0],
    ]);
});

it('serializes the invested-by-asset wrapper', function () {
    $data = new InvestedByAssetSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        series: [new AssetInvestedSeriesData(7, 'ACME', [100.0, 250.0])],
    );

    $json = $data->jsonSerialize();

    expect($json['labels'])->toBe(['2026-01-01', '2026-02-01']);
    expect($json['series'][0])->toBeInstanceOf(AssetInvestedSeriesData::class);
});

it('builds an empty invested-by-asset series', function () {
    expect(InvestedByAssetSeriesData::empty()->jsonSerialize())->toBe(['labels' => [], 'series' => []]);
});
