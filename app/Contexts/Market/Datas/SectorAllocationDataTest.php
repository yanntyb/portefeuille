<?php

use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\Sector;

it('builds from a string sector value', function () {
    $data = SectorAllocationData::fromArray(['sector' => 'healthcare', 'weight' => '0.42']);

    expect($data->sector)->toBe(Sector::Healthcare)
        ->and($data->weight)->toBe(0.42);
});

it('accepts a Sector instance and casts the weight to float', function () {
    $data = SectorAllocationData::fromArray(['sector' => Sector::Energy, 'weight' => 1]);

    expect($data->sector)->toBe(Sector::Energy)
        ->and($data->weight)->toBe(1.0);
});
