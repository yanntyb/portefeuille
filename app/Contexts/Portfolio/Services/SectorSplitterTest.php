<?php

use App\Contexts\Portfolio\Services\SectorSplitter;

it('répartit la valeur d\'un actif entre ses secteurs', function () {
    expect((new SectorSplitter)->split(
        [1 => 1000.0],
        [1 => ['technology' => 0.6, 'health' => 0.4]],
        'other',
    ))->toBe([
        ['sector' => 'technology', 'value' => 600.0, 'pct' => 60.0],
        ['sector' => 'health', 'value' => 400.0, 'pct' => 40.0],
    ]);
});

it('normalise des poids qui ne somment pas à un', function () {
    expect((new SectorSplitter)->split(
        [1 => 1000.0],
        [1 => ['technology' => 1.0, 'health' => 1.0]],
        'other',
    ))->toBe([
        ['sector' => 'technology', 'value' => 500.0, 'pct' => 50.0],
        ['sector' => 'health', 'value' => 500.0, 'pct' => 50.0],
    ]);
});

it('rabat sur le secteur de repli un actif sans poids connu', function () {
    expect((new SectorSplitter)->split([1 => 1000.0], [], 'other'))->toBe([
        ['sector' => 'other', 'value' => 1000.0, 'pct' => 100.0],
    ]);
});

it('rabat sur le secteur de repli un actif dont les poids somment à zéro', function () {
    expect((new SectorSplitter)->split([1 => 1000.0], [1 => ['technology' => 0.0]], 'other'))->toBe([
        ['sector' => 'other', 'value' => 1000.0, 'pct' => 100.0],
    ]);
});

it('trie les secteurs par valeur décroissante', function () {
    $slices = (new SectorSplitter)->split(
        [1 => 100.0, 2 => 900.0],
        [1 => ['health' => 1.0], 2 => ['technology' => 1.0]],
        'other',
    );

    expect(array_column($slices, 'sector'))->toBe(['technology', 'health']);
});

it('rend une liste vide sans aucune valeur', function () {
    expect((new SectorSplitter)->split([], [], 'other'))->toBe([]);
});
