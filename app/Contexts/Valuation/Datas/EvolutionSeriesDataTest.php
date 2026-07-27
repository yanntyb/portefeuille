<?php

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;

it('serializes the evolution series', function () {
    $data = new EvolutionSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        value: [1000.0, 1200.0],
        totalInvested: [1000.0, 1000.0],
        perAsset: [new AssetInvestedSeriesData(7, 'ACME', [1000.0, 1000.0])],
    );

    $json = $data->jsonSerialize();

    expect($json['labels'])->toBe(['2026-01-01', '2026-02-01'])
        ->and($json['value'])->toBe([1000.0, 1200.0])
        ->and($json['totalInvested'])->toBe([1000.0, 1000.0])
        ->and($json['perAsset'][0])->toBeInstanceOf(AssetInvestedSeriesData::class);
});

it('builds an empty evolution series', function () {
    expect(EvolutionSeriesData::empty()->jsonSerialize())->toBe([
        'labels' => [], 'value' => [], 'totalInvested' => [], 'perAsset' => [],
    ]);
});
