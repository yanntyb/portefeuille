<?php

use App\Contexts\Valuation\Datas\AssetSeriesData;

it('serializes an asset series with value, invested and cash', function () {
    $serie = new AssetSeriesData(assetId: 7, name: 'ACME', value: [1200.0, 1500.0], invested: [1000.0, 1000.0], cash: [0.0, 200.0]);

    expect($serie->jsonSerialize())->toBe([
        'assetId' => 7,
        'name' => 'ACME',
        'value' => [1200.0, 1500.0],
        'invested' => [1000.0, 1000.0],
        'cash' => [0.0, 200.0],
    ]);
});
