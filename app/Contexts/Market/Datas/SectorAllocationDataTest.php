<?php

use App\Contexts\Market\Datas\SectorAllocationData;
use App\Contexts\Market\Enums\Sector;

it('construit depuis une valeur string de secteur', function () {
    $data = SectorAllocationData::fromArray(['sector' => 'healthcare', 'weight' => '0.42']);

    expect($data->sector)->toBe(Sector::Healthcare)
        ->and($data->weight)->toBe(0.42);
});

it('accepte une instance de Sector et caste le poids en float', function () {
    $data = SectorAllocationData::fromArray(['sector' => Sector::Energy, 'weight' => 1]);

    expect($data->sector)->toBe(Sector::Energy)
        ->and($data->weight)->toBe(1.0);
});
