<?php

use App\Contexts\Market\Datas\InstrumentData;
use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;

it('construit depuis un tableau minimal', function () {
    $data = InstrumentData::fromArray([
        'symbol' => 'AAPL',
        'name' => 'Apple',
        'type' => InstrumentType::Stock,
    ]);

    expect($data->symbol)->toBe('AAPL')
        ->and($data->name)->toBe('Apple')
        ->and($data->type)->toBe(InstrumentType::Stock)
        ->and($data->exchange)->toBeNull()
        ->and($data->currency)->toBeNull()
        ->and($data->sectors)->toBe([]);
});

it('mappe les secteurs tableau en SectorAllocationData', function () {
    $data = InstrumentData::fromArray([
        'symbol' => 'AAPL',
        'name' => 'Apple',
        'type' => InstrumentType::Stock,
        'exchange' => 'NASDAQ',
        'sectors' => [
            ['sector' => 'technology', 'weight' => 1.0],
        ],
    ]);

    expect($data->exchange)->toBe('NASDAQ')
        ->and($data->sectors)->toHaveCount(1)
        ->and($data->sectors[0])->toBeInstanceOf(SectorAllocationData::class)
        ->and($data->sectors[0]->sector)->toBe(Sector::Technology)
        ->and($data->sectors[0]->weight)->toBe(1.0);
});

it('laisse passer un SectorAllocationData déjà construit', function () {
    $allocation = new SectorAllocationData(Sector::Energy, 0.5);

    $data = InstrumentData::fromArray([
        'symbol' => 'XOM',
        'name' => 'Exxon',
        'type' => InstrumentType::Stock,
        'sectors' => [$allocation],
    ]);

    expect($data->sectors[0])->toBe($allocation);
});
