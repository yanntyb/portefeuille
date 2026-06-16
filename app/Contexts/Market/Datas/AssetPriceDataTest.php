<?php

use App\Contexts\Market\Datas\AssetPriceData;
use App\Contexts\Market\Datas\PriceData;

it('reprend open/high/low du close quand ils sont absents', function () {
    $data = AssetPriceData::fromPriceData(42, new PriceData(date: '2026-01-15', close: 100.0));

    expect($data->assetId)->toBe(42)
        ->and($data->close)->toBe('100')
        ->and($data->open)->toBe('100')
        ->and($data->high)->toBe('100')
        ->and($data->low)->toBe('100')
        ->and($data->volume)->toBe(0);
});

it('conserve les valeurs fournies', function () {
    $price = new PriceData(
        date: '2026-01-15',
        close: 100.0,
        open: 98.0,
        high: 105.0,
        low: 97.0,
        volume: 5000,
    );

    $data = AssetPriceData::fromPriceData(7, $price);

    expect($data->open)->toBe('98')
        ->and($data->high)->toBe('105')
        ->and($data->low)->toBe('97')
        ->and($data->volume)->toBe(5000);
});

it('sérialise en attributs de base de données', function () {
    $array = AssetPriceData::fromPriceData(1, new PriceData(date: '2026-01-15', close: 100.0))->toArray();

    expect($array)->toHaveKeys([
        'asset_id', 'date', 'open', 'high', 'low', 'close', 'volume', 'created_at', 'updated_at',
    ])
        ->and($array['asset_id'])->toBe(1)
        ->and($array['date'])->toBe('2026-01-15');
});
