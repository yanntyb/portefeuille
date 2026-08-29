<?php

use App\Contexts\Wealth\Services\SeriesAligner;

it('fait l\'union triée de deux jeux de labels sans doublon', function () {
    $aligner = new SeriesAligner;

    expect($aligner->union(['2026-01-05', '2026-01-19'], ['2026-01-12', '2026-01-19']))
        ->toBe(['2026-01-05', '2026-01-12', '2026-01-19']);
});

it('fait l\'union d\'autant de jeux de labels qu\'on lui en donne', function () {
    $aligner = new SeriesAligner;

    expect($aligner->union(['2026-01-05'], ['2026-01-12'], ['2026-01-05', '2026-01-19']))
        ->toBe(['2026-01-05', '2026-01-12', '2026-01-19']);
});

it('rend une union vide sans aucun jeu', function () {
    expect((new SeriesAligner)->union())->toBe([]);
});

it('reporte la dernière valeur connue sur les labels intercalés', function () {
    $aligner = new SeriesAligner;

    $aligned = $aligner->onto(
        ['2026-01-05', '2026-01-12', '2026-01-19'],
        ['2026-01-05', '2026-01-19'],
        [100.0, 300.0],
    );

    /** Le 12 n'existe pas dans la source : il tient la valeur du 5, il ne l'interpole pas. */
    expect($aligned)->toBe([100.0, 100.0, 300.0]);
});

it('vaut zéro avant le premier point de la source', function () {
    $aligner = new SeriesAligner;

    expect($aligner->onto(['2026-01-05', '2026-01-12'], ['2026-01-12'], [50.0]))
        ->toBe([0.0, 50.0]);
});

it('rend une série de zéros quand la source est vide', function () {
    $aligner = new SeriesAligner;

    expect($aligner->onto(['2026-01-05', '2026-01-12'], [], []))->toBe([0.0, 0.0]);
});

it('garde la dernière valeur au-delà du dernier point de la source', function () {
    $aligner = new SeriesAligner;

    expect($aligner->onto(['2026-01-05', '2026-01-12'], ['2026-01-05'], [80.0]))
        ->toBe([80.0, 80.0]);
});

it('empile deux séries index par index', function () {
    expect((new SeriesAligner)->accumulate([[1.0, 2.0], [10.0, 20.0]], 2))->toBe([11.0, 22.0]);
});

it('complète une série plus courte que la grille', function () {
    expect((new SeriesAligner)->accumulate([[1.0]], 3))->toBe([1.0, 0.0, 0.0]);
});

it('rend une grille de zéros sans aucune série', function () {
    expect((new SeriesAligner)->accumulate([], 2))->toBe([0.0, 0.0]);
});

it('arrondit à deux décimales', function () {
    expect((new SeriesAligner)->accumulate([[0.1], [0.2]], 1))->toBe([0.3]);
});
