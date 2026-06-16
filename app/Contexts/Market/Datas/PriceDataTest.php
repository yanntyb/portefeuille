<?php

use App\Contexts\Market\Datas\PriceData;

it('construit depuis un tableau complet', function () {
    $data = PriceData::fromArray([
        'date' => '2026-01-15',
        'close' => 123.45,
        'open' => 120.0,
        'high' => 125.0,
        'low' => 119.0,
        'volume' => 10000,
    ]);

    expect($data->date)->toBe('2026-01-15')
        ->and($data->close)->toBe(123.45)
        ->and($data->open)->toBe(120.0)
        ->and($data->high)->toBe(125.0)
        ->and($data->low)->toBe(119.0)
        ->and($data->volume)->toBe(10000);
});

it('met les champs optionnels à null', function () {
    $data = PriceData::fromArray([
        'date' => '2026-01-15',
        'close' => 100.0,
    ]);

    expect($data->open)->toBeNull()
        ->and($data->high)->toBeNull()
        ->and($data->low)->toBeNull()
        ->and($data->volume)->toBeNull();
});
