<?php

use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;

it('serializes the evolution series', function () {
    $data = new EvolutionSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        perAsset: [new AssetSeriesData(7, 'ACME', [1200.0, 1500.0], [1000.0, 1000.0])],
    );

    $json = $data->jsonSerialize();

    expect($json['labels'])->toBe(['2026-01-01', '2026-02-01'])
        ->and($json['perAsset'][0])->toBeInstanceOf(AssetSeriesData::class);
});

it('builds an empty evolution series', function () {
    expect(EvolutionSeriesData::empty()->jsonSerialize())->toBe([
        'labels' => [], 'perAsset' => [], 'hasMore' => false,
    ]);
});

it('reports that the window hides older points', function () {
    $data = new EvolutionSeriesData(labels: ['2026-02-01'], perAsset: [], hasMore: true);

    expect($data->jsonSerialize()['hasMore'])->toBeTrue();
});
